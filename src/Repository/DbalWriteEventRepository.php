<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\Repo;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DbalWriteEventRepository implements WriteEventRepository
{
    /**
     * @var EntityRepository<Event>
     */
    private EntityRepository $eventRepository;

    /**
     * @var EntityRepository<Actor>
     */
    private EntityRepository $actorRepository;

    /**
     * @var EntityRepository<Repo>
     */
    private EntityRepository $repoRepository;

    public function __construct(protected EntityManagerInterface $entityManager)
    {
        $this->eventRepository = $this->entityManager->getRepository(Event::class);
        $this->actorRepository = $this->entityManager->getRepository(Actor::class);
        $this->repoRepository = $this->entityManager->getRepository(Repo::class);
    }

    public function update(EventInput $authorInput, int $id): void
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            throw new NotFoundHttpException();
        }
        $event->setComment($authorInput->comment);
        $this->entityManager->flush();
    }

    public function create(Event $event, bool $flush = true): void
    {
        if (null !== $this->findEvent($event)) {
            return;
        }

        if (null !== $actor = $this->findActor($event->getActor())) {
            $event->setActor($actor);
        }

        if (null !== $repo = $this->findRepo($event->getRepo())) {
            $event->setRepo($repo);
        }

        $this->entityManager->persist($event);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function deleteAll(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery('DELETE FROM event');
        $connection->executeQuery('DELETE FROM actor');
        $connection->executeQuery('DELETE FROM repo');
    }

    protected function findActor(Actor $actor): ?Actor
    {
        return $this->actorRepository->find($actor->id());
    }

    protected function findRepo(Repo $repo): ?Repo
    {
        return $this->repoRepository->find($repo->id());
    }

    protected function findEvent(Event $event): ?Event
    {
        return $this->eventRepository->find($event->getId());
    }

    public function flush(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
