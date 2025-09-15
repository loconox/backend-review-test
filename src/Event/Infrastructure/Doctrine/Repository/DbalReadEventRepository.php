<?php

namespace App\Event\Infrastructure\Doctrine\Repository;

use App\Event\Application\Dto\SearchInput;
use App\Event\Application\Repository\ReadEventRepository;
use App\Event\Domain\Entity\EventType;
use Doctrine\DBAL\Connection;

class DbalReadEventRepository implements ReadEventRepository
{
    public function __construct(protected Connection $connection) {}

    public function countAll(SearchInput $searchInput): int
    {
        $sql = <<<SQL
        SELECT sum(count) as count
        FROM event
        WHERE date(create_at) = :date
        AND comment like '%$searchInput->keyword%'
SQL;

        return (int) $this->connection->fetchOne($sql, [
            'date' => $searchInput->date->format('Y-m-d'),
        ]);
    }

    public function countByType(SearchInput $searchInput): array
    {
        $sql = <<<SQL
            SELECT type, sum(count) as count
            FROM event
            WHERE date(create_at) = :date
            AND comment like '%$searchInput->keyword%'
            GROUP BY type
SQL;

        return $this->connection->fetchAllKeyValue($sql, [
            'date' => $searchInput->date->format('Y-m-d'),
        ]);
    }

    public function statsByTypePerHour(SearchInput $searchInput): array
    {
        $sql = <<<SQL
            SELECT extract(hour from create_at) as hour, type, sum(count) as count
            FROM event
            WHERE date(create_at) = :date
            AND comment like '%$searchInput->keyword%'
            GROUP BY TYPE, EXTRACT(hour from create_at)
SQL;

        $stats = $this->connection->fetchAllAssociative($sql, [
            'date' => $searchInput->date->format('Y-m-d'),
        ]);

        $data = array_fill(0, 24, [EventType::COMMIT => 0, EventType::PULL_REQUEST => 0, EventType::COMMENT => 0]);

        foreach ($stats as $stat) {
            $data[(int) $stat['hour']][$stat['type']] = $stat['count'];
        }

        return $data;
    }

    public function getLatest(SearchInput $searchInput): array
    {
        $sql = <<<SQL
            SELECT type, repo.name as repo, comment
            FROM event
            JOIN repo ON repo.id = event.repo_id
            WHERE date(create_at) = :date
            AND comment like '%$searchInput->keyword%'
SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'date' => $searchInput->date->format('Y-m-d'),
        ]);

        return $result;
    }

    public function exist(int $id): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM event
            WHERE id = :id
        SQL;

        $result = $this->connection->fetchOne($sql, [
            'id' => $id,
        ]);

        return (bool) $result;
    }
}
