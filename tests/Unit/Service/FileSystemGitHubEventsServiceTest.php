<?php

namespace App\Tests\Unit\Service;

use App\Service\FileSystemGitHubEventsService;
use PHPUnit\Framework\TestCase;

class FileSystemGitHubEventsServiceTest extends TestCase
{
    private string $temporaryFile;

    protected function setUp(): void
    {
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'github_events_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }
    }

    public function testGetEventsReturnsEmptyGeneratorIfFileIsEmpty(): void
    {
        file_put_contents($this->temporaryFile, '');

        $service = new FileSystemGitHubEventsService($this->temporaryFile);
        $generator = $service->getEvents();

        $this->assertInstanceOf(\Generator::class, $generator);
        $this->assertCount(0, iterator_to_array($generator));
    }

    public function testGetEventsSkipsInvalidEvents(): void
    {
        $invalidData = [
            '{"invalid": "data"}',
            '{"another": "invalid"}',
        ];

        file_put_contents($this->temporaryFile, implode(PHP_EOL, $invalidData));

        $service = $this->getMockBuilder(FileSystemGitHubEventsService::class)
            ->setConstructorArgs([$this->temporaryFile])
            ->onlyMethods(['denormalize', 'parseEvent'])
            ->getMock();

        $service->method('denormalize')
            ->willReturnCallback(fn($json) => json_decode($json, true));

        $service->method('parseEvent')
            ->willReturn(null);

        $generator = $service->getEvents();

        $this->assertInstanceOf(\Generator::class, $generator);
        $this->assertCount(0, iterator_to_array($generator));
    }

    public function testGetEventsReturnsValidEvents(): void
    {
        $validData = [
            '{"id": 1, "type": "PushEvent"}',
            '{"id": 2, "type": "PullRequestEvent"}',
        ];

        file_put_contents($this->temporaryFile, implode(PHP_EOL, $validData));

        $service = $this->getMockBuilder(FileSystemGitHubEventsService::class)
            ->setConstructorArgs([$this->temporaryFile])
            ->onlyMethods(['denormalize', 'parseEvent'])
            ->getMock();

        $service->method('denormalize')
            ->willReturnCallback(fn($json) => json_decode($json, true));

        $mockEvent1 = $this->createMock(\App\Entity\Event::class);
        $mockEvent2 = $this->createMock(\App\Entity\Event::class);

        $service->method('parseEvent')
            ->willReturnOnConsecutiveCalls($mockEvent1, $mockEvent2);

        $generator = $service->getEvents();

        $this->assertInstanceOf(\Generator::class, $generator);
        $events = iterator_to_array($generator);

        $this->assertCount(2, $events);
        $this->assertSame($mockEvent1, $events[0]);
        $this->assertSame($mockEvent2, $events[1]);
    }
}
