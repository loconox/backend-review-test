<?php

namespace App\Event\Tests\Unit\Infrastructure\Symfony\Service;

use App\Event\Domain\Entity\Event;
use App\Event\Domain\Exception\ImportDataSourceException;
use App\Event\Infrastructure\Symfony\Service\GHArchiveGitHubEventsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class GHArchiveGitHubEventsServiceTest extends TestCase
{
    private ClientInterface|MockObject $client;
    private RequestFactoryInterface|MockObject $requestFactory;
    private RequestInterface|MockObject $request;
    private ResponseInterface|MockObject $response;
    private StreamInterface|MockObject $stream;
    private GHArchiveGitHubEventsService $service;
    private string $baseUrl = 'https://data.gharchive.org';

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream = $this->createMock(StreamInterface::class);

        $this->service = new GHArchiveGitHubEventsService(
            $this->client,
            $this->requestFactory,
            $this->baseUrl,
        );
    }

    public function testGetEventsWithSpecificDate(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $expectedUrl = 'https://data.gharchive.org/2023-10-15-14.json.gz';
        $gzipData = $this->createGzipJsonData([
            $this->createValidEventData(1, 'PushEvent'),
            $this->createValidEventData(2, 'PullRequestEvent'),
        ]);

        $this->setupSuccessfulHttpCall($expectedUrl, $gzipData);

        // Act
        $generator = $this->service->getEvents($date);
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(2, $events);
        $this->assertInstanceOf(Event::class, $events[0]);
        $this->assertInstanceOf(Event::class, $events[1]);
        $this->assertEquals(1, $events[0]->getId());
        $this->assertEquals(2, $events[1]->getId());
    }

    public function testGetEventsWithNullDateUsesYesterday(): void
    {
        // Arrange
        $yesterday = new \DateTimeImmutable('yesterday');
        $expectedUrl = 'https://data.gharchive.org/' . $yesterday->format('Y-m-d-G') . '.json.gz';
        $gzipData = $this->createGzipJsonData([
            $this->createValidEventData(1, 'PushEvent'),
        ]);

        $this->setupSuccessfulHttpCall($expectedUrl, $gzipData);

        // Act
        $generator = $this->service->getEvents();
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(1, $events);
    }

    public function testGetEventsHandlesBaseUrlWithTrailingSlash(): void
    {
        // Arrange
        $serviceWithTrailingSlash = new GHArchiveGitHubEventsService(
            $this->client,
            $this->requestFactory,
            'https://data.gharchive.org/',  // Avec slash de fin
        );

        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $expectedUrl = 'https://data.gharchive.org/2023-10-15-14.json.gz';
        $gzipData = $this->createGzipJsonData([
            $this->createValidEventData(1, 'PushEvent'),
        ]);

        $this->setupSuccessfulHttpCall($expectedUrl, $gzipData);

        // Act
        $generator = $serviceWithTrailingSlash->getEvents($date);
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(1, $events);
    }

    public function testGetEventsThrowsImportDataSourceExceptionOnHttpClientError(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $expectedUrl = 'https://data.gharchive.org/2023-10-15-14.json.gz';

        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $expectedUrl)
            ->willReturn($this->request);

        $clientException = new class extends \Exception implements ClientExceptionInterface {};

        $this->client
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willThrowException($clientException);

        // Act & Assert
        $this->expectException(ImportDataSourceException::class);

        $generator = $this->service->getEvents($date);
        iterator_to_array($generator); // Force l'exécution
    }

    public function testGetEventsThrowsImportDataSourceExceptionOnNon200Response(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $expectedUrl = 'https://data.gharchive.org/2023-10-15-14.json.gz';

        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $expectedUrl)
            ->willReturn($this->request);

        $this->client
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willReturn($this->response);

        $this->response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(404);

        // Act & Assert
        $this->expectException(ImportDataSourceException::class);

        $generator = $this->service->getEvents($date);
        iterator_to_array($generator);
    }

    public function testGetEventsFiltersOutInvalidEventTypes(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $gzipData = $this->createGzipJsonData([
            $this->createValidEventData(1, 'PushEvent'),
            $this->createValidEventData(2, 'InvalidEventType'), // Sera filtré
            $this->createValidEventData(3, 'PullRequestEvent'),
        ]);

        $this->setupSuccessfulHttpCall(
            'https://data.gharchive.org/2023-10-15-14.json.gz',
            $gzipData,
        );

        // Act
        $generator = $this->service->getEvents($date);
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(2, $events); // Seuls les événements valides
        $this->assertEquals(1, $events[0]->getId());
        $this->assertEquals(3, $events[1]->getId());
    }

    public function testStreamGzipJsonLinesHandlesEmptyChunks(): void
    {
        // Arrange
        $gzipData = $this->createGzipJsonData([
            $this->createValidEventData(1, 'PushEvent'),
        ]);

        // Simule des chunks avec des chaînes vides entremêlées
        $this->stream
            ->expects($this->exactly(4))
            ->method('eof')
            ->willReturnOnConsecutiveCalls(false, false, false, true);

        $this->stream
            ->expects($this->exactly(3))
            ->method('read')
            ->with(8192)
            ->willReturnOnConsecutiveCalls('', $gzipData, '');

        $this->setupSuccessfulHttpCall(
            'https://data.gharchive.org/2023-10-15-14.json.gz',
            $gzipData,
            false, // Ne pas setup le stream, on le fait manuellement
        );

        $this->response
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        // Act
        $generator = $this->service->getEvents(new \DateTimeImmutable('2023-10-15 14:00:00'));
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(1, $events);
    }

    public function testReadLineHandlesEmptyLines(): void
    {
        // Arrange
        $gzipData = $this->createGzipJsonData([
            $this->createValidEventData(1, 'PushEvent'),
        ], true); // Avec lignes vides

        $this->setupSuccessfulHttpCall(
            'https://data.gharchive.org/2023-10-15-14.json.gz',
            $gzipData,
        );

        // Act
        $generator = $this->service->getEvents(new \DateTimeImmutable('2023-10-15 14:00:00'));
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(1, $events);
    }

    public function testGetEventsHandlesJsonException(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $invalidJson = gzencode('{"invalid": json}');

        $this->setupSuccessfulHttpCall(
            'https://data.gharchive.org/2023-10-15-14.json.gz',
            $invalidJson,
        );

        // Act & Assert
        $this->expectException(\JsonException::class);

        $generator = $this->service->getEvents($date);
        iterator_to_array($generator);
    }

    /**
     * Configure une requête HTTP réussie.
     */
    private function setupSuccessfulHttpCall(string $expectedUrl, string $gzipData, bool $setupStream = true): void
    {
        $eofCount = 0;
        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $expectedUrl)
            ->willReturn($this->request);

        $this->client
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willReturn($this->response);

        $this->response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        if ($setupStream) {
            $this->response
                ->expects($this->once())
                ->method('getBody')
                ->willReturn($this->stream);

            $this->stream
                ->expects($this->atLeastOnce())
                ->method('eof')
                ->willReturnCallback(function () use ($gzipData, &$eofCount) {
                    // @phpstan-ignore greater.alwaysFalse
                    return $eofCount * 8192 > strlen($gzipData);
                });

            $this->stream
                ->expects($this->atLeastOnce())
                ->method('read')
                ->with(8192)
                ->willReturnCallback(function ($size) use ($gzipData, &$eofCount) {
                    $ret = substr($gzipData, $eofCount * $size, $size);
                    ++$eofCount;

                    return $ret;
                });
        }
    }

    /**
     * @param array<int, array{
     *          'id': int,
     *          'type': string,
     *          'repo': array{'id': int, 'name': string, 'url': string},
     *          'actor': array{'id': int, 'login': string, 'url': string, 'avatar_url': string},
     *          'payload': array<mixed>,
     *          'created_at': string,
     *          'comment'?: array{'body': string}
     *          }> $events
     */
    private function createGzipJsonData(array $events, bool $withEmptyLines = false): string
    {
        $jsonLines = [];

        foreach ($events as $event) {
            $jsonLines[] = json_encode($event);
            if ($withEmptyLines) {
                $jsonLines[] = ''; // Ligne vide
            }
        }

        return gzencode(implode("\n", $jsonLines));
    }

    /**
     * @return array{
     *          'id': int,
     *          'type': string,
     *          'repo': array{'id': int, 'name': string, 'url': string},
     *          'actor': array{'id': int, 'login': string, 'url': string, 'avatar_url': string},
     *          'payload': array<mixed>,
     *          'created_at': string,
     *          'comment'?: array{'body': string}
     *          }
     */
    private function createValidEventData(int $id, string $type): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'repo' => [
                'id' => $id + 1000,
                'name' => "repo-{$id}",
                'url' => "https://github.com/repo-{$id}",
            ],
            'actor' => [
                'id' => $id + 2000,
                'login' => "actor-{$id}",
                'url' => "https://github.com/actor-{$id}",
                'avatar_url' => "https://avatar-{$id}.png",
            ],
            'payload' => [
                'size' => 1,
                'comment' => [
                    'body' => "Test comment {$id}",
                ],
            ],
            'created_at' => '2023-10-15T14:30:00Z',
        ];
    }

    /**
     * @dataProvider readLinesProvider
     *
     * @param string[] $expected
     *
     * @throws \ReflectionException
     */
    public function testReadLines(string $buffer, array $expected, string $remaining): void
    {
        $reflection = new \ReflectionClass(GHArchiveGitHubEventsService::class);
        $method = $reflection->getMethod('readLines');
        $lines = iterator_to_array($method->invokeArgs($this->service, [&$buffer]));
        $this->assertEquals($expected, $lines);
        $this->assertEquals($remaining, $buffer);
    }

    /**
     * @return array<array{string, array<int, string>, string}>
     */
    public function readLinesProvider(): array
    {
        return [
            ["line1\nline2\nline3", ['line1', 'line2'], 'line3'],
            ["line1\nline2\nline3\n", ['line1', 'line2', 'line3'], ''],
        ];
    }

    public function testGetEventsWithBigEvent(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2023-10-15 14:00:00');
        $expectedUrl = 'https://data.gharchive.org/2023-10-15-14.json.gz';
        $gzipData = $this->createGzipJsonData(events: [
            [
                'id' => 42,
                'type' => 'PushEvent',
                'repo' => [
                    'id' => 1000,
                    'name' => 'repo',
                    'url' => 'https://github.com/repo',
                ],
                'actor' => [
                    'id' => 2000,
                    'login' => 'actor}',
                    'url' => 'https://github.com/actor',
                    'avatar_url' => 'https://avatar.png',
                ],
                'payload' => [
                    'size' => 1,
                    'comment' => [
                        'body' => $this->randomString(11000),
                    ],
                ],
                'created_at' => '2023-10-15T14:30:00Z',
            ],
            $this->createValidEventData(1, 'PushEvent'),
        ]);

        $this->setupSuccessfulHttpCall($expectedUrl, $gzipData);

        // Act
        $generator = $this->service->getEvents($date);
        $events = iterator_to_array($generator);

        // Assert
        $this->assertCount(2, $events);
    }

    protected function randomString(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', (int) ceil($length / strlen($x)))), 1, $length);
    }
}
