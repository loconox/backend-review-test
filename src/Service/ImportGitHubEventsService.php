<?php

namespace App\Service;

use App\Entity\Event;
use App\Repository\WriteEventRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ImportGitHubEventsService
{
    public const DEFAULT_BATCH_SIZE = 1000;

    public function __construct(
        #[Autowire(service: GHArchiveGitHubEventsService::class)]
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
        $batch = [];
        $count = 0;

        foreach ($this->service->getEvents($date) as $event) {
            $batch[] = $event;
            ++$count;
            if ($count >= self::DEFAULT_BATCH_SIZE) {
                $this->writeEventRepository->createBatch($batch);
                yield from $batch;
                $batch = [];
                $count = 0;
            }
        }
        if ($count > 0) {
            $this->writeEventRepository->createBatch($batch);
            yield from $batch;
        }
    }

    public function deleteAll(): void
    {
        $this->writeEventRepository->deleteAll();
    }
}
