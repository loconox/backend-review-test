<?php

namespace App\Event\Infrastructure\Symfony\DataFixtures;

use App\Event\Domain\Entity\Actor;
use App\Event\Domain\Entity\Event;
use App\Event\Domain\Entity\EventType;
use App\Event\Domain\Entity\Repo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventFixtures extends Fixture
{
    public const EVENT_1_ID = 1;
    public const ACTOR_1_ID = 1;
    public const REPO_1_ID = 1;

    public function load(ObjectManager $manager): void
    {
        $date = new \DateTimeImmutable('2023-10-15 14:00:00', new \DateTimeZone('UTC'));
        $event = new Event(
            self::EVENT_1_ID,
            EventType::COMMENT,
            new Actor(
                self::ACTOR_1_ID,
                'jdoe',
                'https://api.github.com/users/jdoe',
                'https://avatars.githubusercontent.com/u/1?',
            ),
            new Repo(
                self::REPO_1_ID,
                'yousign/test',
                'https://api.github.com/repos/yousign/backend-test',
            ),
            [],
            $date,
            'Test comment initiate by fixture ',
        );

        $manager->persist($event);
        $manager->flush();
    }
}
