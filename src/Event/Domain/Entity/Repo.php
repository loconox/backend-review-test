<?php

declare(strict_types=1);

namespace App\Event\Domain\Entity;

class Repo
{
    private int $id;

    private string $name;

    private string $url;

    public function __construct(int $id, string $name, string $url)
    {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param array{'id': int, 'name': string, 'url': string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            $data['name'],
            $data['url'],
        );
    }
}
