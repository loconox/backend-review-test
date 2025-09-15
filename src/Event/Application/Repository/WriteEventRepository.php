<?php

namespace App\Event\Application\Repository;

use App\Event\Application\Dto\EventInput;
use App\Event\Domain\Entity\Event;

interface WriteEventRepository
{
    public function update(EventInput $authorInput, int $id): void;

    public function create(Event $event, bool $flush = true): void;

    public function deleteAll(): void;

    public function flush(): void;
}
