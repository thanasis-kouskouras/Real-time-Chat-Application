<?php
/* GUID ERROR HANDLER

Handles GUID-related errors across API endpoints, returning appropriate HTTP status codes and messages. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/guid-utilities.php';

class GuidErrorHandler
{
    //Handle invalid GUID format error
    public static function handleInvalidGuidFormat(string $invalidGuid, string $context = '', string $fieldName = 'ID'): void
    {
        $message = "Invalid $fieldName format. Expected UUID format (e.g., 550e8400-e29b-41d4-a716-446655440000)";

        $errors = [
            'field' => $fieldName,
            'provided_value' => $invalidGuid,
            'expected_format' => 'UUID v4 (8-4-4-4-12 hexadecimal digits)',
            'example' => '550e8400-e29b-41d4-a716-446655440000'
        ];

        if ($context) {
            $errors['context'] = $context;
        }

        app_log("Invalid GUID format in $context: $invalidGuid for field $fieldName");

        sendError($message, 400, $errors);
    }

    //Handle GUID not found error
    public static function handleGuidNotFound(string $guid, string $entityType = 'resource', string $context = ''): void
    {
        $message = ucfirst($entityType) . " not found";

        $errors = [
            'entity_type' => $entityType,
            'guid' => $guid,
            'reason' => 'The specified ' . $entityType . ' does not exist or has been deleted'
        ];

        if ($context) {
            $errors['context'] = $context;
        }

        app_log("$entityType not found in $context: $guid");

        sendError($message, 404, $errors);
    }

    //Handle GUID generation failure
    public static function handleGuidGenerationFailure(string $context = '', ?Exception $originalException = null): void
    {
        $message = "Failed to generate unique identifier";

        $errors = [
            'reason' => 'System error during ID generation',
            'suggestion' => 'Please try again. If the problem persists, contact support.'
        ];

        if ($context) {
            $errors['context'] = $context;
        }

        $logMessage = "GUID generation failed in $context";
        if ($originalException) {
            $logMessage .= ": " . $originalException->getMessage();
        }
        app_log($logMessage);

        sendError($message, 500, $errors);
    }

    //Handle ID mapping failure
    public static function handleIdMappingFailure(string $fromType, string $toType, string $value, string $context = ''): void
    {
        $message = "Failed to process identifier";

        $errors = [
            'from_type' => $fromType,
            'to_type' => $toType,
            'value' => $value,
            'reason' => 'ID mapping failed - the identifier may not exist or migration may be incomplete'
        ];

        if ($context) {
            $errors['context'] = $context;
        }

        app_log("ID mapping failed in $context: $fromType($value) -> $toType");

        sendError($message, 500, $errors);
    }

    //Handle generic GUID-related exceptions
    public static function handleGuidException(Exception $exception, string $context = ''): void
    {
        if ($exception instanceof GuidValidationException) {
            self::handleInvalidGuidFormat(
                $exception->getMessage(),
                $exception->getContext() ?: $context,
                'GUID'
            );
        } elseif ($exception instanceof GuidGenerationException) {
            self::handleGuidGenerationFailure($context, $exception);
        } elseif ($exception instanceof IdMappingException) {
            self::handleIdMappingFailure('unknown', 'unknown', 'unknown', $context);
        } elseif ($exception instanceof InvalidArgumentException) {
            self::handleInvalidGuidFormat($exception->getMessage(), $context);
        } else {
            $errors = [
                'reason' => 'System error during ID processing',
                'suggestion' => 'Please try again. If the problem persists, contact support.'
            ];

            if ($context) {
                $errors['context'] = $context;
            }

            app_log("GUID exception in $context: " . $exception->getMessage());

            sendError("An error occurred while processing identifiers", 500, $errors);
        }
    }
}