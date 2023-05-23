<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\PluginManagement\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Install, activate, update, and remove plugins
 */
class HandleCommand extends \Symfony\Component\Console\Command\Command
{
    /**
     * @inerhitDoc
     */
    protected static $defaultName = 'netzarbeiter:plugins:handle';

    /**
     * @inerhitDoc
     */
    protected static $defaultDescription = 'Install, activate, update, and remove plugins';

    /**
     * Context
     *
     * @var Context
     */
    protected Context $context;

    /**
     * Style for input/output
     *
     * @var SymfonyStyle
     */
    protected SymfonyStyle $io;

    /**
     * PluginInstallCommand constructor.
     *
     * @param PluginService $pluginService
     * @param PluginLifecycleService $pluginLifecycleService
     * @param EntityRepository $pluginRepository
     */
    public function __construct(
        protected PluginService $pluginService,
        protected PluginLifecycleService $pluginLifecycleService,
        protected EntityRepository $pluginRepository
    ) {
        parent::__construct();

        // Create context.
        $this->context = Context::createDefaultContext();
        $this->context->addState(
            \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry::DISABLE_INDEXING
        );
    }

    /**
     * @inerhitDoc
     */
    protected function configure(): void
    {
        $this
            ->addArgument('plugin-list', InputArgument::REQUIRED, 'Plugin list file')
            ->addOption('dry-run', null, null, 'Dry run, do not install or activate plugins');
    }

    /**
     * @inerhitDoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @inerhitDoc
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Print plugin title.
        $this->io->title(sprintf('%s (%s)', $this->getDescription(), $this->getName()));

        // Read plugin list.
        $pluginList = $this->loadPluginList($input->getArgument('plugin-list'));
        if ($pluginList === null) {
            return self::FAILURE;
        }

        // Validate plugin list.
        if (!$this->validatePluginList($pluginList)) {
            return self::FAILURE;
        }

        // Handle plugins and print output.
        $this->io->section('Handling active plugins');
        $table = new \Symfony\Component\Console\Helper\Table($output->section());
        $table->setHeaders(['Plugin', 'Active?', 'Update?', 'Actions']);
        $table->render();
        foreach ($pluginList as $plugin => $settings) {
            $actions = $this->handlePlugin($plugin, $settings, (bool)$input->getOption('dry-run'));
            $table->appendRow([
                $plugin,
                $settings->active ? 'yes' : 'no',
                is_bool($settings->update) ? ($settings->update ? 'yes' : 'no') : $settings->update,
                empty($actions) ? '-' : implode('|', $actions),
            ]);
        }
        $this->io->writeln('');

        // Uninstall all plugins not in the list.
        $pluginsToUninstall = $this->getPluginsToUninstall($pluginList);
        if (count($pluginsToUninstall) > 0) {
            $this->io->section('Uninstalling remaining plugins');
            $table = new \Symfony\Component\Console\Helper\Table($output->section());
            $table->setHeaders(['Plugin', 'Uninstalled?']);
            $table->render();
            foreach ($pluginsToUninstall as $plugin) {
                $table->appendRow([
                    $plugin->getName(),
                    $this->uninstallPlugin($plugin, (bool)$input->getOption('dry-run')) ? 'yes' : 'no',
                ]);
            }
            $this->io->writeln('');
        }

        return self::SUCCESS;
    }

    /**
     * Handle a plugin action.
     *
     * @param string $pluginName
     * @param \stdClass $settings
     * @param bool $dryRun
     * @return array
     */
    protected function handlePlugin(string $pluginName, \stdClass $settings, bool $dryRun = false): array
    {
        // Fetch plugin.
        try {
            $plugin = $this->pluginService->getPluginByName($pluginName, $this->context);
        } catch (\Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException $e) {
            return ['Plugin missing'];
        }

        // Collect actions performed.
        $actions = [];

        // Install plugin if it isn't.
        if (!$plugin->getInstalledAt()) {
            try {
                $dryRun || $this->pluginLifecycleService->installPlugin($plugin, $this->context);
                $actions[] = 'Installed';
            } catch (\Exception $e) {
                return ['Installation failed'];
            }
        }

        // Activate/deactivate plugin as specified
        if ($settings->active && !$plugin->getActive()) {
            try {
                $dryRun || $this->pluginLifecycleService->activatePlugin($plugin, $this->context);
                $actions[] = 'Activated';
            } catch (\Exception $e) {
                $actions[] = 'Activation failed';
            }
        } elseif (!$settings->active && $plugin->getActive()) {
            try {
                $dryRun || $this->pluginLifecycleService->deactivatePlugin($plugin, $this->context);
                $actions[] = 'Deactivated';
            } catch (\Exception $e) {
                $actions[] = 'Deactivation failed';
            }
        }

        // Update plugin if either we have a new version and update flag is set or if update is forced.
        if ($settings->update === 'force' || ($settings->update && $plugin->getUpgradeVersion())) {
            try {
                $dryRun || $this->pluginLifecycleService->updatePlugin($plugin, $this->context);
                $actions[] = 'Updated';
            } catch (\Exception $e) {
                $actions[] = 'Update failed';
            }
        }

        return $actions;
    }

    /**
     * Get list of all plugins to uninstall.
     *
     * @param \stdClass $pluginList
     * @return array
     */
    protected function getPluginsToUninstall(\stdClass $pluginList): array
    {
        // Fetch all installed plugins.
        $plugins = $this->pluginRepository
            ->search(
                (new Criteria())
                    ->addFilter(new NotFilter('and', [new EqualsFilter('installedAt', null)])),
                $this->context
            )
            ->getEntities();

        // Get names of plugins.
        $pluginNames = $plugins->map(
            static function (PluginEntity $plugin) {
                return $plugin->getName();
            }
        );

        // Get diff of plugin names.
        $pluginNamesToUninstall = array_diff($pluginNames, array_keys(get_object_vars($pluginList)));

        return $plugins
            ->filter(
                static function (PluginEntity $plugin) use ($pluginNamesToUninstall) {
                    return in_array($plugin->getName(), $pluginNamesToUninstall, true);
                }
            )
            ->getElements();
    }

    /**
     * Uninstall plugin.
     *
     * @param PluginEntity $plugin
     * @param bool $dryRun
     * @return bool
     */
    protected function uninstallPlugin(PluginEntity $plugin, bool $dryRun = false): bool
    {
        try {
            $dryRun || $this->pluginLifecycleService->uninstallPlugin($plugin, $this->context);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Load plugin list from file.
     *
     * @param string $file
     * @return \stdClass|null
     */
    protected function loadPluginList(string $file): ?\stdClass
    {
        try {
            $pluginList = json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->io->error(sprintf('Error parsing plugin list file "%s": %s', $file, $e->getMessage()));
            return null;
        }

        return $pluginList;
    }

    /**
     * Validate plugin list using JSON schema.
     *
     * @param \stdClass $pluginList
     * @return bool
     */
    protected function validatePluginList(\stdClass $pluginList): bool
    {
        // Check path for schema file.
        $file = realpath(__DIR__ . '/../../shopware.plugin-management.json');
        if (!$file) {
            $this->io->error('Could not find schema file');
            return false;
        }

        // Load schema file.
        try {
            $json = json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->io->error(sprintf('Error parsing schema file "%s": %s', $file, $e->getMessage()));
            return false;
        }

        // Set up schema.
        try {
            $schema = \Swaggest\JsonSchema\Schema::import($json);
        } catch (\Exception $e) {
            $this->io->error(sprintf('Error importing schema: %s', $e->getMessage()));
            return false;
        }

        // Validate plugin list.
        try {
            $schema->in($pluginList);
        } catch (\Swaggest\JsonSchema\InvalidValue $e) {
            $this->io->error(sprintf('Error validating plugin list: %s', $e->getMessage()));
            return false;
        }

        return true;
    }
}