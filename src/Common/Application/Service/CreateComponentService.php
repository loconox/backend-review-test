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

namespace App\Common\Application\Service;

/* @AllowedVendor */
use Symfony\Component\Filesystem\Filesystem;
/* @AllowedVendor */
use Symfony\Component\Filesystem\Path;

class CreateComponentService
{
    private const DOT_GITIGNORE_FILE = '/.gitkeep';

    private string $root;

    private string $name;

    private Filesystem $filesystem;

    /** @var string[] */
    private array $files = [
        'Infrastructure/ApiPlatform/config/resources.yaml' => <<<EOF
properties:
  App\%name%\Domain\Entity\%name%: ~

resources:
  App\%name%\Domain\Entity\%name%: ~
  # TODO declare operations
EOF,
        'Infrastructure/Doctrine/Mapping/%name%Entity.orm.xml' => <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                          https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="App\%name%\Infrastructure\Doctrine\Entity\%name%Entity" table="%name%" repository-class="App\%name%\Infrastructure\Doctrine\Repository\%name%Repository">
       <!-- TODO Add fields here -->
    </entity>
</doctrine-mapping>
EOF,
        'Domain/Entity/%name%.php' => <<<EOF
<?php

declare(strict_types=1);

namespace App\%name%\Domain\Entity;

class %name%
{
    // TODO add fields
}

EOF,
        'Infrastructure/Symfony/%name%Component.php' => <<<EOF
<?php

declare(strict_types=1);

namespace App\%name%\Infrastructure\Symfony;

use App\Common\Infrastructure\Symfony\Component\AbstractComponent;

class %name%Component extends AbstractComponent
{
}

EOF,
        'Domain/Repository/%name%RepositoryInterface.php' => <<<EOF
<?php

declare(strict_types=1);

namespace App\%name%\Domain\Repository;

use App\%name%\Domain\Entity\%name%;

interface %name%RepositoryInterface
{
    public function find%name%ByName(string \$name): %name%;
    // TODO add command and query use cases
}

EOF,
        'Infrastructure/Doctrine/Entity/%name%Entity.php' => <<<EOF
<?php

declare(strict_types=1);

namespace App\%name%\Infrastructure\Doctrine\Entity;

use App\%name%\Domain\Entity\%name%;

class %name%Entity
{
    // TODO add fields
    public static function fromDomain(%name% $%name%): %name%Entity
    {
        // TODO
    }
    
    public function toDomain(): %name%
    {
        // TODO
    }
}

EOF,
        'Infrastructure/Doctrine/Repository/%name%Repository.php' => <<<EOF
<?php

declare(strict_types=1);

namespace App\%name%\Infrastructure\Doctrine\Repository;

use App\%name%\Domain\Entity\%name%;
use App\%name%\Domain\Repository\%name%RepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('doctrine.repository_service')]
class %name%Repository extends ServiceEntityRepository implements %name%RepositoryInterface
{
    public function __construct(ManagerRegistry \$registry)
    {
        parent::__construct(\$registry, %name%::class);
    }

    public function find%name%ByName(string \$name): %name%
    {
        return \$this->findOneBy(['name' => \$name]);
    }
    
    // TODO
}

EOF,
        'Infrastructure/Symfony/config/services.yaml' => <<<EOF
parameters:

services:
    # default configuration for services in *this* file
    _defaults:
        autowire: true      # Automatically injects dependencies in your services.
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.

    App\%name%\Infrastructure\:
        resource: '../../'

    App\%name%\Application\Command\:
        resource: '../../../Application/Command'

    App\%name%\Application\Query\:
        resource: '../../../Application/Query'

    _instanceof:
        App\Common\Application\Query\QueryHandlerInterface:
            tags:
                - { name: 'messenger.message_handler', bus: 'query.bus' }

        App\Common\Application\Command\CommandHandlerInterface:
            tags:
                - { name: 'messenger.message_handler', bus: 'command.bus' }
    
    App\%name%\Domain\Repository\%name%RepositoryInterface:
        alias: App\%name%\Infrastructure\Doctrine\Repository\%name%Repository

EOF,
    ];

    /** @var string[] */
    private $dirs = [
        'Application/Command',
        'Application/Query',
        'Application/Service',
        'Domain/Entity',
        'Domain/Repository',
        'Infrastructure/ApiPlatform/Provider',
        'Infrastructure/ApiPlatform/Processor',
        'Infrastructure/Doctrine/Mapping',
    ];

    /** @var string[] */
    private $symlinks = [
        'Infrastructure/Symfony/config/api_resources.yaml' => '../../ApiPlatform/config/resources.yaml',
    ];

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->filesystem = new Filesystem();
    }

    public function createComponent(string $name): void
    {
        if (!$this->checkNameIsValid($name)) {
            throw new \RuntimeException('Invalid component name');
        }
        if (!$this->checkNameIsAvailable($name)) {
            throw new \RuntimeException('Component name not available');
        }
        $this->name = $name;

        // Create dirs hierarchy
        foreach ($this->dirs as $dir) {
            $this->createDir($dir, true);
        }

        // Create files
        foreach ($this->files as $file => $content) {
            $this->createFile($file, $content);
        }

        // Create symlinks
        foreach ($this->symlinks as $file => $target) {
            $this->createSymlink($file, $target);
        }
    }

    private function checkNameIsAvailable(string $name): bool
    {
        if ($this->filesystem->exists($this->root . '/' . $name)) {
            return false;
        }

        return true;
    }

    private function checkNameIsValid(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_]*$/', $name) > 0;
    }

    private function createDir(string $dirname, bool $gitKeep = false): void
    {
        $dirname = Path::makeAbsolute($dirname, $this->root . '/' . $this->name);
        $this->filesystem->mkdir($dirname, 0744);

        if ($gitKeep) {
            $this->createFile($dirname . self::DOT_GITIGNORE_FILE);
        }
    }

    private function createFile(string $filename, ?string $content = null): void
    {
        $filename = $this->fixFilename($filename);
        $this->createFilenameParentDirectories($filename);
        $this->filesystem->touch($filename);
        if ($content) {
            $content = str_replace('%name%', $this->name, $content);
            $this->filesystem->appendToFile($filename, $content);
        }
    }

    private function createSymlink(string $filename, string $target): void
    {
        $filename = $this->fixFilename($filename);
        $this->createFilenameParentDirectories($filename);
        $this->filesystem->symlink($target, $filename);
    }

    private function createFilenameParentDirectories(string $filename): void
    {
        $info = pathinfo($filename);
        if (!isset($info['dirname'])) {
            return;
        }
        if (!$this->filesystem->exists($info['dirname'])) {
            $this->filesystem->mkdir($info['dirname']);
        }
        if ($this->filesystem->exists($info['dirname'] . self::DOT_GITIGNORE_FILE)) {
            $this->filesystem->remove($info['dirname'] . self::DOT_GITIGNORE_FILE);
        }
    }

    private function fixFilename(string $filename): string
    {
        $filename = Path::makeAbsolute($filename, $this->root . '/' . $this->name);

        return str_replace('%name%', $this->name, $filename);
    }

    public function getComponentClass(): string
    {
        return 'App\\' . $this->name . '\\Infrastructure\\Symfony\\' . $this->name . 'Component';
    }
}
