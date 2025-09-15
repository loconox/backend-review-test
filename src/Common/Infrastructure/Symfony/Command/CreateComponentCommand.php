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

namespace App\Common\Infrastructure\Symfony\Command;

use App\Common\Application\Service\CreateComponentService;
use App\Common\Infrastructure\Symfony\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:component:create', description: 'Create a new component in the application.')]
class CreateComponentCommand extends Command
{
    private CreateComponentService $service;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $root, private Kernel $kernel)
    {
        parent::__construct();
        $this->service = new CreateComponentService($root . '/src');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Name of the new component.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create component command');

        if (!$name = $input->getArgument('name')) {
            $name = $io->ask('Name of the component?', null, function (string $name): string {
                if (empty($name)) {
                    throw new \RuntimeException('Name of the component must not be empty.');
                }

                return $name;
            });
        }

        if (!$io->confirm("Are you sure you want to create a component name <error>$name</error>?", true)) {
            $io->info('Nothing created');

            return Command::FAILURE;
        }

        $this->service->createComponent($name);
        $this->enableComponent($this->service->getComponentClass());

        $io->success('Done');

        return Command::SUCCESS;
    }

    private function enableComponent(string $class): void
    {
        $registered = $this->configureComponents([$class => ['all']]);
        $this->dumpComponentsFile($this->kernel->getComponentsConfigFilePath(), $registered);
    }

    /**
     * @param array<string, array<string>> $components
     *
     * @return array<string, array<string, bool>>
     */
    private function configureComponents(array $components, bool $resetEnvironments = false): array
    {
        $configFile = $this->kernel->getComponentsConfigFilePath();
        $registered = $this->loadComponentsFile($configFile);
        $classes = $this->prepareComponents($components);
        foreach ($classes as $class => $envs) {
            // do not override existing configured envs for a component
            if (!isset($registered[$class]) || $resetEnvironments) {
                if ($resetEnvironments) {
                    // used during calculating an "upgrade"
                    // here, we want to "undo" the component's configuration entirely
                    // then re-add it fresh, in case some environments have been
                    // removed in an updated version of the recipe
                    $registered[$class] = [];
                }

                foreach ($envs as $env) {
                    $registered[$class][$env] = true;
                }
            }
        }

        return $registered;
    }

    /**
     * @param array<string, array<string>> $components
     *
     * @return array<string, array<string>>
     */
    private function prepareComponents(array $components): array
    {
        foreach ($components as $class => $envs) {
            $components[ltrim($class, '\\')] = $envs;
        }

        return $components;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function loadComponentsFile(string $file): array
    {
        $components = file_exists($file) ? (require $file) : [];
        if (!\is_array($components)) {
            $components = [];
        }

        return $components;
    }

    /**
     * @param array<string, array<string, bool>> $components
     */
    private function dumpComponentsFile(string $file, array $components): void
    {
        $contents = $this->buildContents($components);

        if (!is_dir(\dirname($file))) {
            if (!mkdir($concurrentDirectory = \dirname($file), 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        file_put_contents($file, $contents);

        if (\function_exists('opcache_invalidate')) {
            opcache_invalidate($file);
        }
    }

    /**
     * @param array<string, array<string, bool>> $components
     */
    private function buildContents(array $components): string
    {
        $contents = "<?php\ndeclare(strict_types=1);\nreturn [\n";
        foreach ($components as $class => $envs) {
            $contents .= "    $class::class => [";
            foreach ($envs as $env => $value) {
                $booleanValue = var_export($value, true);
                $contents .= "'$env' => $booleanValue, ";
            }
            $contents = substr($contents, 0, -2) . "],\n";
        }
        $contents .= "];\n";

        return $contents;
    }
}
