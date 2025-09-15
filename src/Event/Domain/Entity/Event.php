<?php

declare(strict_types=1);

namespace App\Event\Domain\Entity;

class Event
{
    private int $id;

    private string $type;

    private int $count = 1;

    private Actor $actor;

    private Repo $repo;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private \DateTimeImmutable $createAt;

    private ?string $comment;

    /**
     * @param array<mixed> $payload
     */
    public function __construct(int $id, string $type, Actor $actor, Repo $repo, array $payload, \DateTimeImmutable $createAt, ?string $comment)
    {
        $this->id = $id;
        EventType::assertValidChoice($type);
        $this->type = $type;
        $this->actor = $actor;
        $this->repo = $repo;
        $this->payload = $payload;
        $this->createAt = $createAt;
        $this->comment = $comment;

        if (EventType::COMMIT === $type) {
            $this->count = $payload['size'] ?? 1;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getActor(): Actor
    {
        return $this->actor;
    }

    public function setActor(Actor $actor): void
    {
        $this->actor = $actor;
    }

    public function getRepo(): Repo
    {
        return $this->repo;
    }

    public function setRepo(Repo $repo): void
    {
        $this->repo = $repo;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreateAt(): \DateTimeImmutable
    {
        return $this->createAt;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
