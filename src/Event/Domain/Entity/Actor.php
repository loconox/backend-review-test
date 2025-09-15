<?php

declare(strict_types=1);

namespace App\Event\Domain\Entity;

class Actor
{
    public int $id;

    public string $login;

    public string $url;

    public string $avatarUrl;

    public function __construct(int $id, string $login, string $url, string $avatarUrl)
    {
        $this->id = $id;
        $this->login = $login;
        $this->url = $url;
        $this->avatarUrl = $avatarUrl;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function login(): string
    {
        return $this->login;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function avatarUrl(): string
    {
        return $this->avatarUrl;
    }

    /**
     * @param array{'id': int, 'login': string, 'url': string, 'avatar_url': string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            $data['login'],
            $data['url'],
            $data['avatar_url'],
        );
    }
}
