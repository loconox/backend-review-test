<?php

namespace App\Event\Application\Service;

use App\Event\Domain\Entity\Event;

interface GitHubEventsServiceInterface
{
    /**
     * @return \Generator<Event>
     */
    public function getEvents(?\DateTimeImmutable $dateTime = null): \Generator;
}
