<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Entity\Event;

interface WriteEventRepository
{
    public function update(EventInput $authorInput, int $id): void;

    public function create(Event $event): void;

    /**
     * @param Event[] $events
     */
    public function createBatch(array $events): void;

    public function deleteAll(): void;
}
