<?php

declare(strict_types=1);

/*
 * Copyright (c) egerie.eu - All Rights Reserved.
 *
 * This file is proprietary and confidential and a part of the Egerie API.
 * It may not be copied, distributed, or modified without explicit permission from Egerie.
 *
 * Unauthorized access or usage is strictly prohibited.
 */

namespace App\Common\Infrastructure\Symfony\Component;

use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class AbstractComponent extends AbstractBundle
{
    public function getPath(): string
    {
        if (null === $this->path) {
            $reflected = new \ReflectionObject($this);
            $path = $reflected->getFileName();
            if (false === $path) {
                throw new \RuntimeException('Path cannot be false');
            }
            $this->path = \dirname($path, 1);
        }

        return $this->path;
    }

    public function build(ContainerBuilder $container): void
    {
        // Add directory into container to allow automatic config parsing when files are modified
        $componentPath = $this->getPath();
        $paths = [
            $componentPath . '/../Doctrine/Mapping/',
        ];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $container->addResource(new DirectoryResource($path, '/\.(xml|ya?ml|php)$/'));
            }
        }
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $componentPath = $this->getPath();
        $servicesPath = $componentPath . '/config/services.yaml';
        if (is_file($servicesPath)) {
            $container->import($servicesPath);
        }
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        // Charger depuis un fichier YAML
        $routes->import(__DIR__ . '/Resources/config/routes.yaml');
    }
}
