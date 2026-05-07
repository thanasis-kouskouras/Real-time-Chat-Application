<?php
/* PROCESSING RESULT

Simple result object for media processing operations, returned by MediaFileProcessor. */

class ProcessingResult
{
    public bool $success;
    public int $originalSize;
    public int $processedSize;
    public float $compressionRatio;
    public array $errors;

    public function __construct(
        bool $success,
        int $originalSize = 0,
        int $processedSize = 0,
        array $errors = []
    ) {
        $this->success = $success;
        $this->originalSize = $originalSize;
        $this->processedSize  = $processedSize;
        $this->compressionRatio = $originalSize > 0 ? ($processedSize / $originalSize) : 0;
        $this->errors = $errors;
    }

    //Create successful result
    public static function success(int $originalSize, int $processedSize): self
    {
        return new self(true, $originalSize, $processedSize);
    }

    //Create failed result
    public static function failure(array $errors): self
    {
        return new self(false, 0, 0, $errors);
    }
}