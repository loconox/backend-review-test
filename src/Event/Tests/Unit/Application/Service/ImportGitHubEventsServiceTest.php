<?php

namespace App\Event\Tests\Unit\Application\Service;

use App\Event\Application\Repository\WriteEventRepository;
use App\Event\Application\Service\GitHubEventsServiceInterface;
use App\Event\Application\Service\ImportGitHubEventsService;
use App\Event\Domain\Entity\Actor;
use App\Event\Domain\Entity\Event;
use App\Event\Domain\Entity\EventType;
use App\Event\Domain\Entity\Repo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImportGitHubEventsServiceTest extends TestCase
{
    private GitHubEventsServiceInterface&MockObject $gitHubEventsService;
    private WriteEventRepository&MockObject $writeEventRepository;
    private ImportGitHubEventsService $importService;

    protected function setUp(): void
    {
        $this->gitHubEventsService = $this->createMock(GitHubEventsServiceInterface::class);
        $this->writeEventRepository = $this->createMock(WriteEventRepository::class);

        $this->importService = new ImportGitHubEventsService(
            $this->gitHubEventsService,
            $this->writeEventRepository,
        );
    }

    public function testImportAllWithDate(): void
    {
        $date = new \DateTimeImmutable('2023-10-15');

        $importService = $this->getMockBuilder(ImportGitHubEventsService::class)
            ->setConstructorArgs([$this->gitHubEventsService, $this->writeEventRepository])
            ->onlyMethods(['import'])
            ->getMock();

        $importService
            ->expects($this->once())
            ->method('import')
            ->with($this->equalTo($date))
            ->willReturn((function () {
                yield;
            })());

        $importService->importAll($date);
    }

    public function testImportAllWithNullDate(): void
    {
        $importService = $this->getMockBuilder(ImportGitHubEventsService::class)
            ->setConstructorArgs([$this->gitHubEventsService, $this->writeEventRepository])
            ->onlyMethods(['import'])
            ->getMock();

        $importService
            ->expects($this->once())
            ->method('import')
            ->willReturn((function () {
                yield;
            })());

        $importService->importAll();
    }

    public function testImportReturnsGeneratorWithEvents(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15');
        $events = $this->createTestEvents(2);

        $this->gitHubEventsService
            ->expects($this->once())
            ->method('getEvents')
            ->with($date)
            ->willReturn((function () use ($events) {
                foreach ($events as $event) {
                    yield $event;
                }
            })());

        $this->writeEventRepository
            ->expects($this->exactly(2))
            ->method('create');

        $this->writeEventRepository
            ->expects($this->atLeastOnce())
            ->method('flush');

        // Act
        $generator = $this->importService->import($date);
        $result = iterator_to_array($generator);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals($events[0], $result[0]);
        $this->assertEquals($events[1], $result[1]);
    }

    public function testImportBatchesEventsCorrectly(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15');
        $events = $this->createTestEvents(2500); // Plus que la taille de batch par défaut

        $this->gitHubEventsService
            ->expects($this->once())
            ->method('getEvents')
            ->with($date)
            ->willReturn((function () use ($events) {
                foreach ($events as $event) {
                    yield $event;
                }
            })());

        // On s'attend à 2 appels : un pour 1000 événements, un autre pour 1000, et le dernier batch reste dans le buffer
        $this->writeEventRepository
            ->expects($this->exactly(2500))
            ->method('create');

        $this->writeEventRepository
            ->expects($this->exactly(3))
            ->method('flush');

        // Act
        $generator = $this->importService->import($date);
        iterator_to_array($generator); // Force l'exécution du générateur
    }

    public function testImportWithEmptyEventStream(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15');

        $this->gitHubEventsService
            ->expects($this->once())
            ->method('getEvents')
            ->with($date)
            ->willReturn((function (): \Generator {
                /* @phpstan-ignore return.empty */
                return;
                /* @phpstan-ignore deadCode.unreachable */
                yield;
            })());

        $this->writeEventRepository
            ->expects($this->never())
            ->method('create');

        // Act
        $generator = $this->importService->import($date);
        $result = iterator_to_array($generator);

        // Assert
        $this->assertEmpty($result);
    }

    public function testDeleteAllCallsRepositoryDeleteAll(): void
    {
        // Arrange
        $this->writeEventRepository
            ->expects($this->once())
            ->method('deleteAll');

        // Act
        $this->importService->deleteAll();
    }

    /**
     * Crée un tableau d'événements de test.
     *
     * @param int $count Nombre d'événements à créer
     *
     * @return Event[]
     */
    private function createTestEvents(int $count): array
    {
        $events = [];

        for ($i = 1; $i <= $count; ++$i) {
            $actor = new Actor($i, "actor{$i}", "https://github.com/actor{$i}", "https://avatar{$i}.png");
            $repo = new Repo($i, "repo{$i}", "https://github.com/repo{$i}");

            $minute = $i % 60;
            $events[] = new Event(
                id: $i,
                type: EventType::COMMIT,
                actor: $actor,
                repo: $repo,
                payload: ['size' => 1],
                createAt: new \DateTimeImmutable("2023-10-15 10:{$minute}:00"),
                comment: "Test comment {$i}",
            );
        }

        return $events;
    }
}
