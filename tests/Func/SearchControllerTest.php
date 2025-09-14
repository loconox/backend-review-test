<?php

namespace App\Tests\Func;

use App\DataFixtures\EventFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class SearchControllerTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures(
            [EventFixtures::class],
        );
    }

    public function testSearchCommits(): void
    {
        $date = new \DateTimeImmutable();
        $keyword = 'Test';

        $this->client->request(
            'GET',
            sprintf('/api/search?date=%s&keyword=%s', $date->format('Y-m-d'), $keyword),
        );

        $expectedJson = <<<JSON
              {
                "meta": {
                  "totalEvents": 1,
                  "totalCommits": 0,
                  "totalPullRequests": 0,
                  "totalComments": 1
                },
                "data":{
                  "events": [
                      {
                        "type":"MSG",
                        "repo":"yousign\/test",
                        "comment":"Test comment initiate by fixture "
                      }
                    ],
                  "stats": [
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":1},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0}
                  ]
                }
              }
            JSON;
        self::assertResponseStatusCodeSame(200);
        self::assertJsonStringEqualsJsonString($expectedJson, $this->client->getResponse()->getContent());
    }

    public function testSearchCommitsEmptyResponse(): void
    {
        $date = new \DateTimeImmutable();
        $keyword = 'foo';

        $this->client->request(
            'GET',
            sprintf('/api/search?date=%s&keyword=%s', $date->format('Y-m-d'), $keyword),
        );

        $expectedJson = <<<JSON
              {
                "meta": {
                  "totalEvents": 0,
                  "totalCommits": 0,
                  "totalPullRequests": 0,
                  "totalComments": 0
                },
                "data":{
                  "events": [],
                  "stats": [
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0},
                    {"COM":0,"PR":0,"MSG":0}
                  ]
                }
              }
            JSON;
        self::assertResponseStatusCodeSame(200);
        self::assertJsonStringEqualsJsonString($expectedJson, $this->client->getResponse()->getContent());
    }

    public function testSearchCommitsMalformedDate(): void
    {
        $date = new \DateTimeImmutable();
        $keyword = 'foo';

        $this->client->request(
            'GET',
            sprintf('/api/search?date=%s&keyword=%s', $date->format('Ymd'), $keyword),
        );


        self::assertResponseStatusCodeSame(400);
    }
}
