<?php

declare(strict_types=1);

namespace App\Event\Domain\Entity;

class Repo
{
    private int $id;

    public string $name;

    public string $url;

    public function __construct(int $id, string $name, string $url)
    {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(): string
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
