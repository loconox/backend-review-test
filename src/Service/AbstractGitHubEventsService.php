<?php

namespace App\Service;

use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Repo;

abstract class AbstractGitHubEventsService implements GitHubEventsServiceInterface
{
    /**
     * @return  array{
     *         'id': int,
     *         'type': string,
     *         'repo': array{'id': int, 'name': string, 'url': string},
     *         'actor': array{'id': int, 'login': string, 'url': string, 'avatar_url': string},
     *         'payload': array<mixed>,
     *         'created_at': string,
     *         'comment'?: array{'body': string}
     *         }
     *
     * @throws \JsonException
     */
    protected function denormalize(string $json): array
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array{
     *     'id': int,
     *     'type': string,
     *     'repo': array{'id': int, 'name': string, 'url': string},
     *     'actor': array{'id': int, 'login': string, 'url': string, 'avatar_url': string},
     *     'payload': array<mixed>,
     *     'created_at': string,
     *     'comment'?: array{'body': string}
     *     } $data
     */
    protected function parseEvent(array $data): ?Event
    {
        $type = match ($data['type']) {
            'PushEvent' => EventType::COMMIT,
            'PullRequestEvent' => EventType::PULL_REQUEST,
            'IssueCommentEvent', 'CommitCommentEvent' => EventType::COMMENT,
            default => null,
        };
        if (null === $type) {
            return null;
        }

        $repo = $this->createRepo($data['repo']);

        $actor = $this->createActor($data['actor']);

        return new Event(
            id: $data['id'],
            type: $type,
            actor: $actor,
            repo: $repo,
            payload: $data['payload'],
            createAt: new \DateTimeImmutable($data['created_at']),
            comment: $data['payload']['comment']['body'] ?? null,
        );
    }

    /**
     * @param array{'id': int, 'login': string, 'url': string, 'avatar_url': string} $data
     */
    protected function createActor(array $data): Actor
    {
        return Actor::fromArray($data);
    }

    /**
     * @param array{'id': int, 'name': string, 'url': string} $data
     */
    protected function createRepo(array $data): Repo
    {
        return Repo::fromArray($data);
    }
}
