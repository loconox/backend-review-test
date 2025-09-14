<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Entity\Event;

interface WriteEventRepository
{
    public function update(EventInput $authorInput, int $id): void;

    public function create(Event $event, bool $flush = true): void;

    public function deleteAll(): void;

    public function flush(): void;
}
