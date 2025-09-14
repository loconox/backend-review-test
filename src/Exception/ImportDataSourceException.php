<?php

namespace App\Exception;

class ImportDataSourceException extends \RuntimeException
{
    public function __construct(?string $message = null, int $code = 0, ?\Throwable $previous = null)
    {
        $message ??= 'Failed to get events from data source.';
        parent::__construct($message, $code, $previous);
    }
}
