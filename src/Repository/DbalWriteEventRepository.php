<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\Repo;
use Doctrine\DBAL\Connection;

class DbalWriteEventRepository implements WriteEventRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(EventInput $authorInput, int $id): void
    {
        $sql = <<<SQL
        UPDATE event
        SET comment = :comment
        WHERE id = :id
SQL;

        $this->connection->executeQuery($sql, ['id' => $id, 'comment' => $authorInput->comment]);
    }

    public function create(Event $event): void
    {
        $this->createActors([$event->actor()]);

        $this->createRepos([$event->repo()]);

        $this->createEvents([$event]);
    }

    /**
     * @param Event[] $events
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createEvents(array $events): void
    {
        $eventsToCreate = [];
        foreach ($events as $event) {
            if (!$this->isEventExist($event)) {
                $eventsToCreate[$event->id()] = $event;
            }
        }

        if (empty($eventsToCreate)) {
            return;
        }

        $sql = 'INSERT INTO event (id, type, actor_id, repo_id, count, payload, create_at, comment) VALUES ';
        $valuesSQL = '(?, ?, ?, ?, ?, ?, ?, ?)';

        $values = [];
        foreach ($eventsToCreate as $event) {
            $values[] = $event->id();
            $values[] = $event->type();
            $values[] = $event->actor()->id();
            $values[] = $event->repo()->id();
            $values[] = $event->getCount();
            $values[] = json_encode($event->payload());
            $values[] = $event->createAt()->format('Y-m-d H:i:s');
            $values[] = $event->getComment();
        }
        $sql .= implode(', ', array_fill(0, count($eventsToCreate), $valuesSQL));
        $this->connection->executeQuery($sql, $values);
    }

    /**
     * @param Repo[] $repos
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createRepos(array $repos): void
    {
        $reposToCreate = [];
        foreach ($repos as $repo) {
            if (!$this->isRepoExist($repo)) {
                $reposToCreate[$repo->id()] = $repo;
            }
        }

        if (empty($reposToCreate)) {
            return;
        }

        $sql = 'INSERT INTO repo (id, name, url) VALUES ';
        $valuesSQL = '(?, ?, ?)';

        $values = [];
        foreach ($reposToCreate as $repo) {
            $values[] = $repo->id();
            $values[] = $repo->name();
            $values[] = $repo->url();
        }
        $sql .= implode(', ', array_fill(0, count($reposToCreate), $valuesSQL));
        $this->connection->executeQuery($sql, $values);
    }

    /**
     * @param Actor[] $actors
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createActors(array $actors): void
    {
        $actorsToCreate = [];
        foreach ($actors as $actor) {
            if (!$this->isActorExist($actor)) {
                $actorsToCreate[$actor->id()] = $actor;
            }
        }

        if (empty($actorsToCreate)) {
            return;
        }

        $sql = 'INSERT INTO actor (id, login, url, avatar_url) VALUES ';
        $valuesSQL = '(?, ?, ?, ?)';

        $values = [];
        foreach ($actorsToCreate as $actor) {
            $values[] = $actor->id();
            $values[] = $actor->login();
            $values[] = $actor->url();
            $values[] = $actor->avatarUrl();
        }
        $sql .= implode(', ', array_fill(0, count($actorsToCreate), $valuesSQL));
        $this->connection->executeQuery($sql, $values);
    }

    public function deleteAll(): void
    {
        $this->connection->executeQuery('DELETE FROM event');
        $this->connection->executeQuery('DELETE FROM actor');
        $this->connection->executeQuery('DELETE FROM repo');
    }

    /**
     * @param Event[] $events
     *
     * @throws \Throwable
     */
    public function createBatch(array $events): void
    {
        $actors = [];
        $repos = [];
        $es = [];
        foreach ($events as $event) {
            $actors[$event->actor()->id()] = $event->actor();
            $repos[$event->repo()->id()] = $event->repo();
            $es[$event->id()] = $event;
        }
        $this->createActors($actors);
        $this->createRepos($repos);
        $this->createEvents($es);
    }

    protected function isActorExist(Actor $actor): bool
    {
        $sql = <<<SQL
SELECT 1 FROM actor WHERE id = :id
SQL;
        $result = $this->connection->executeQuery($sql, ['id' => $actor->id()]);

        return false !== $result->fetchOne();
    }

    protected function isRepoExist(Repo $repo): bool
    {
        $sql = <<<SQL
SELECT 1 FROM repo WHERE id = :id
SQL;
        $result = $this->connection->executeQuery($sql, ['id' => $repo->id()]);

        return false !== $result->fetchOne();
    }

    protected function isEventExist(Event $event): bool
    {
        $sql = <<<SQL
SELECT 1 FROM event WHERE id = :id
SQL;
        $result = $this->connection->executeQuery($sql, ['id' => $event->id()]);

        return false !== $result->fetchOne();
    }
}
