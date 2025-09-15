<?php

namespace App\Common\Infrastructure\Symfony;

use App\Common\Infrastructure\Symfony\Component\AbstractComponent;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        registerBundles as registerBundlesTrait;
        registerContainerConfiguration as registerContainerConfigurationTrait;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $this->registerContainerConfigurationTrait($loader);
        $loader->load(__DIR__ . '/config/services.yaml');
    }

    public function registerBundles(): iterable
    {
        // Register bundles from Trait
        yield from $this->registerBundlesTrait();
        // Add custom app component bundles
        yield from $this->registerComponents();
    }

    /**
     * @return AbstractComponent[]
     */
    private function registerComponents(): iterable
    {
        $componentConfigFile = $this->getComponentsConfigFilePath();
        if (file_exists($componentConfigFile)) {
            $contents = require $componentConfigFile;
            /**
             * @var class-string<AbstractComponent> $class
             * @var string[]                        $envs
             */
            foreach ($contents as $class => $envs) {
                if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                    yield new $class();
                }
            }
        }
    }

    public function getComponentsConfigFilePath(): string
    {
        return __DIR__ . '/config/components.php';
    }
}
