<?php

namespace App\Service;

use App\Exception\ImportDataSourceException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GHArchiveGitHubEventsService extends AbstractGitHubEventsService
{
    public function __construct(
        protected ClientInterface $client,
        protected RequestFactoryInterface $requestFactory,
        #[Autowire(param: 'GHArchiveEndpoint')]
        protected string $baseUrl,
    ) {}

    public function getEvents(?\DateTimeImmutable $dateTime = null): \Generator
    {
        if (null === $dateTime) {
            $dateTime = new \DateTimeImmutable('yesterday');
        }
        $request = $this->requestFactory->createRequest('GET', rtrim($this->baseUrl, '/') . '/' . $dateTime->format('Y-m-d-G') . '.json.gz');
        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new ImportDataSourceException(null, $e->getCode(), $e);
        }

        if (200 !== $statusCode = $response->getStatusCode()) {
            throw new ImportDataSourceException(null, $statusCode);
        }

        $stream = $response->getBody();

        foreach ($this->streamGzipJsonLines($stream) as $line) {
            $data = $this->denormalize($line);
            if (null !== $event = $this->parseEvent($data)) {
                yield $event;
            }
        }
    }

    /**
     * @return \Generator<string>
     */
    protected function readLines(string &$buffer): \Generator
    {
        // read a line
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);

            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            yield $line;
        }
        if (!empty($buffer)) {
            yield $buffer;
            $buffer = '';
        }
    }

    /**
     * @return \Generator<string>
     */
    protected function streamGzipJsonLines(StreamInterface $stream): \Generator
    {
        $inflate = inflate_init(ZLIB_ENCODING_GZIP);

        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ('' === $chunk) {
                continue;
            }

            $decompressed = inflate_add($inflate, $chunk, ZLIB_NO_FLUSH);

            $buffer .= $decompressed;

            yield from $this->readLines($buffer);
        }

        // End of stream flux → flush
        $decompressed = inflate_add($inflate, '', ZLIB_FINISH);
        $buffer .= $decompressed;

        yield from $this->readLines($buffer);
    }
}
