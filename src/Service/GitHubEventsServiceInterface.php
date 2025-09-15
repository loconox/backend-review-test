<?php

namespace App\Service;

use App\Entity\Event;

interface GitHubEventsServiceInterface
{
    /**
     * @return \Generator<Event>
     */
    public function getEvents(?\DateTimeImmutable $dateTime = null): \Generator;
}
