<?php
/* DATABASE RESULT

Simple result object for database operations, returned by FileDatabase. */

class DatabaseResult
{
    public bool $success;
    public string $fileGuid;
    public array $metadata;
    public string $errorMessage;

    public function __construct(
        bool $success,
        string $fileGuid = '',
        array $metadata = [],
        string $errorMessage = ''
    ) {
        $this->success = $success;
        $this->fileGuid = $fileGuid;
        $this->metadata = $metadata;
        $this->errorMessage = $errorMessage;
    }

    //Create successful result
    public static function success(string $fileGuid, array $metadata = []): self
    {
        return new self(true, $fileGuid, $metadata);
    }

    //Create failed result
    public static function failure(string $errorMessage): self
    {
        return new self(false, '', [], $errorMessage);
    }
}