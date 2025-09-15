<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FileSystemGitHubEventsService extends AbstractGitHubEventsService
{
    public function __construct(#[Autowire(param: 'testGitHubEventsFile')] public string $filename) {}

    public function getEvents(?\DateTimeImmutable $dateTime = null): \Generator
    {
        $f = $this->openResource();
        try {
            while ($line = fgets($f)) {
                $data = $this->denormalize($line);
                $event = $this->parseEvent($data);
                if (null === $event) {
                    continue;
                }
                yield $event;
            }
        } finally {
            fclose($f);
        }
    }

    /**
     * @return resource|false
     */
    protected function openResource(): mixed
    {
        return fopen($this->filename, 'rb');
    }
}
