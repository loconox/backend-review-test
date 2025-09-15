<?php

namespace App\Event\Tests\Unit\Application\Service;

use App\Event\Application\Service\AbstractGitHubEventsService;
use App\Event\Domain\Entity\Actor;
use App\Event\Domain\Entity\Event;
use App\Event\Domain\Entity\EventType;
use App\Event\Domain\Entity\Repo;
use PHPUnit\Framework\TestCase;

class AbstractGitHubEventsServiceTest extends TestCase
{
    /**
     * @description Tests that parseEvent correctly parses PushEvent into an Event object
     */
    public function testParseEventWithPushEvent(): void
    {
        $service = $this->getMockBuilder(AbstractGitHubEventsService::class)
            ->onlyMethods(['createRepo', 'createActor'])
            ->getMockForAbstractClass();

        $data = [
            'id' => 12345,
            'type' => 'PushEvent',
            'repo' => ['id' => 1, 'name' => 'test/repo', 'url' => 'http://example.com/repo'],
            'actor' => ['id' => 1, 'login' => 'testuser', 'url' => 'http://example.com/user', 'avatar_url' => 'http://example.com/avatar'],
            'payload' => [],
            'created_at' => '2025-09-14T12:34:56Z',
        ];

        $service->expects($this->any())
            ->method('createRepo')
            ->willReturn(Repo::fromArray($data['repo']));

        $service->expects($this->any())
            ->method('createActor')
            ->willReturn(Actor::fromArray($data['actor']));

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseEvent');
        $event = $method->invoke($service, $data);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(12345, $event->getId());
        $this->assertEquals(EventType::COMMIT, $event->getType());
        $this->assertInstanceOf(Actor::class, $event->getActor());
        $this->assertInstanceOf(Repo::class, $event->getRepo());
        $this->assertEquals(new \DateTimeImmutable('2025-09-14T12:34:56Z'), $event->getCreateAt());
        $this->assertNull($event->getComment());
    }

    /**
     * @description Tests that parseEvent correctly parses PullRequestEvent into an Event object
     */
    public function testParseEventWithPullRequestEvent(): void
    {
        $service = $this->getMockBuilder(AbstractGitHubEventsService::class)
            ->onlyMethods(['createRepo', 'createActor'])
            ->getMockForAbstractClass();

        $data = [
            'id' => 67890,
            'type' => 'PullRequestEvent',
            'repo' => ['id' => 2, 'name' => 'example/repo', 'url' => 'http://example.com/repo2'],
            'actor' => ['id' => 2, 'login' => 'anotheruser', 'url' => 'http://example.com/user2', 'avatar_url' => 'http://example.com/avatar2'],
            'payload' => [],
            'created_at' => '2025-09-14T13:00:00Z',
        ];

        $service->expects($this->any())
            ->method('createRepo')
            ->willReturn(Repo::fromArray($data['repo']));

        $service->expects($this->any())
            ->method('createActor')
            ->willReturn(Actor::fromArray($data['actor']));

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseEvent');
        $event = $method->invoke($service, $data);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(67890, $event->getId());
        $this->assertEquals(EventType::PULL_REQUEST, $event->getType());
        $this->assertInstanceOf(Actor::class, $event->getActor());
        $this->assertInstanceOf(Repo::class, $event->getRepo());
        $this->assertEquals(new \DateTimeImmutable('2025-09-14T13:00:00Z'), $event->getCreateAt());
    }

    /**
     * @description Tests that parseEvent correctly parses CommentEvent into an Event object
     */
    public function testParseEventWithCommentEvent(): void
    {
        $service = $this->getMockBuilder(AbstractGitHubEventsService::class)
            ->onlyMethods(['createRepo', 'createActor'])
            ->getMockForAbstractClass();

        $data = [
            'id' => 11223,
            'type' => 'IssueCommentEvent',
            'repo' => ['id' => 3, 'name' => 'some/repo', 'url' => 'http://example.com/repo3'],
            'actor' => ['id' => 2, 'login' => 'anotheruser', 'url' => 'http://example.com/user2', 'avatar_url' => 'http://example.com/avatar2'],
            'payload' => ['comment' => ['body' => 'This is a test comment']],
            'created_at' => '2025-09-14T14:00:00Z',
        ];

        $service->expects($this->any())
            ->method('createRepo')
            ->willReturn(Repo::fromArray($data['repo']));

        $service->expects($this->any())
            ->method('createActor')
            ->willReturn(Actor::fromArray($data['actor']));

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseEvent');
        $event = $method->invoke($service, $data);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(11223, $event->getId());
        $this->assertEquals(EventType::COMMENT, $event->getType());
        $this->assertEquals('This is a test comment', $event->getComment());
    }

    /**
     * @description Tests that parseEvent returns null for an unsupported event type
     */
    public function testParseEventWithUnsupportedEventType(): void
    {
        $service = $this->getMockBuilder(AbstractGitHubEventsService::class)
            ->onlyMethods(['createRepo', 'createActor'])
            ->getMockForAbstractClass();

        $data = [
            'id' => 44556,
            'type' => 'UnknownEvent',
            'repo' => ['id' => 4, 'name' => 'unknown/repo', 'url' => 'http://example.com/repo4'],
            'actor' => ['id' => 3, 'login' => 'unknownuser', 'url' => 'http://example.com/user3', 'avatar_url' => 'http://example.com/avatar3'],
            'payload' => [],
            'created_at' => '2025-09-14T15:00:00Z',
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseEvent');
        $event = $method->invoke($service, $data);

        $this->assertNull($event);
    }

    /**
     * @description Tests that createActor creates an Actor entity from valid data
     */
    public function testCreateActorWithValidData(): void
    {
        $service = $this->getMockBuilder(AbstractGitHubEventsService::class)
            ->getMockForAbstractClass();

        $data = [
            'id' => 1,
            'login' => 'testuser',
            'url' => 'http://example.com/user',
            'avatar_url' => 'http://example.com/avatar',
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createActor');

        $actor = $method->invoke($service, $data);

        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals(1, $actor->getId());
        $this->assertEquals('testuser', $actor->getLogin());
        $this->assertEquals('http://example.com/user', $actor->getUrl());
        $this->assertEquals('http://example.com/avatar', $actor->getAvatarUrl());
    }

    /**
     * @description Tests that createRepo creates a Repo entity from valid data
     */
    public function testCreateRepoWithValidData(): void
    {
        $service = $this->getMockBuilder(AbstractGitHubEventsService::class)
            ->getMockForAbstractClass();

        $data = [
            'id' => 101,
            'name' => 'sample/repo',
            'url' => 'http://example.com/repo',
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createRepo');

        $repo = $method->invoke($service, $data);

        $this->assertInstanceOf(Repo::class, $repo);
        $this->assertEquals(101, $repo->getId());
        $this->assertEquals('sample/repo', $repo->getName());
        $this->assertEquals('http://example.com/repo', $repo->getUrl());
    }
}
