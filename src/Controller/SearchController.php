<?php

namespace App\Controller;

use App\Dto\SearchInput;
use App\Entity\EventType;
use App\Repository\ReadEventRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class SearchController
{
    private ReadEventRepository $repository;
    private SerializerInterface&DenormalizerInterface $serializer;

    public function __construct(
        ReadEventRepository $repository,
        SerializerInterface&DenormalizerInterface $serializer,
    ) {
        $this->repository = $repository;
        $this->serializer = $serializer;
    }

    #[Route(path: '/api/search', name: 'api_search', methods: ['GET'])]
    public function searchCommits(Request $request): JsonResponse
    {
        try {
            $searchInput = $this->serializer->denormalize($request->query->all(), SearchInput::class);
        } catch (NotNormalizableValueException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $countByType = $this->repository->countByType($searchInput);

        $data = [
            'meta' => [
                'totalEvents' => $this->repository->countAll($searchInput),
                'totalPullRequests' => $countByType[EventType::PULL_REQUEST] ?? 0,
                'totalCommits' => $countByType[EventType::COMMIT] ?? 0,
                'totalComments' => $countByType[EventType::COMMENT] ?? 0,
            ],
            'data' => [
                'events' => $this->repository->getLatest($searchInput),
                'stats' => $this->repository->statsByTypePerHour($searchInput),
            ],
        ];

        return new JsonResponse($data);
    }
}
