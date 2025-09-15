<?php

namespace App\Event\Application\Service;

use App\Event\Application\Repository\WriteEventRepository;
use App\Event\Domain\Entity\Event;

class ImportGitHubEventsService
{
    public const DEFAULT_BATCH_SIZE = 1000;

    public function __construct(
        private GitHubEventsServiceInterface $service,
        private WriteEventRepository $writeEventRepository,
    ) {}

    public function importAll(?\DateTimeImmutable $date = null): void
    {
        $iter = $this->import($date);
        $iter->rewind();
        while ($iter->valid()) {
            $iter->next();
        }
    }

    /**
     * @return \Generator<Event>
     */
    public function import(?\DateTimeImmutable $date = null): \Generator
    {
        $count = 0;

        foreach ($this->service->getEvents($date) as $event) {
            ++$count;
            $this->writeEventRepository->create($event, false);
            if ($count >= self::DEFAULT_BATCH_SIZE) {
                $this->writeEventRepository->flush();
                $count = 0;
            }
            yield $event;
        }
        $this->writeEventRepository->flush();
    }

    public function deleteAll(): void
    {
        $this->writeEventRepository->deleteAll();
    }
}
