<?php

namespace App\Event\Application\Dto;

// @AllowedVendor
use Symfony\Component\Serializer\Attribute\Context;
// @AllowedVendor
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class SearchInput
{
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeImmutable $date;

    public string $keyword;
}
