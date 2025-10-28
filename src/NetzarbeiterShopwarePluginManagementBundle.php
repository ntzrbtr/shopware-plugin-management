<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\PluginManagement;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Bundle class
 */
class NetzarbeiterShopwarePluginManagementBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerContainerFile($container);
    }

    /**
     * Looks for service definition files inside the `Resources/config` directory and loads either xml or yml files.
     *
     * @see \Shopware\Core\Framework\Bundle::registerContainerFile()
     */
    protected function registerContainerFile(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator($this->getPath());
        $loaderResolver = new LoaderResolver([
            new XmlFileLoader($container, $fileLocator),
            new YamlFileLoader($container, $fileLocator),
            new PhpFileLoader($container, $fileLocator),
        ]);
        $delegatingLoader = new DelegatingLoader($loaderResolver);

        foreach ($this->getServicesFilePathArray($this->getPath() . '/Resources/config/services.*') as $path) {
            $delegatingLoader->load($path);
        }

        if ($container->getParameter('kernel.environment') === 'test') {
            foreach ($this->getServicesFilePathArray($this->getPath() . '/Resources/config/services_test.*') as $testPath) {
                $delegatingLoader->load($testPath);
            }
        }
    }

    /**
     * Find all files matching the given path pattern.
     *
     * @see \Shopware\Core\Framework\Bundle::getServicesFilePathArray()
     *
     * @return string[]
     */
    protected function getServicesFilePathArray(string $path): array
    {
        $pathArray = glob($path);

        if ($pathArray === false) {
            return [];
        }

        return $pathArray;
    }
}
