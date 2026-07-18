<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly string $errorCode = 'request_error')
    {
        parent::__construct($message);
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_input(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new ApiException(405, 'Use POST for this endpoint.', 'method_not_allowed');
    }
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_contains($contentType, 'application/json')) {
        throw new ApiException(415, 'Content-Type must be application/json.', 'unsupported_media_type');
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 16384) {
        throw new ApiException(413, 'Request body is too large.', 'payload_too_large');
    }
    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException(400, 'Request body must contain valid JSON.', 'invalid_json');
    }
    if (!is_array($data)) {
        throw new ApiException(400, 'JSON body must be an object.', 'invalid_json');
    }
    return $data;
}

function run_api(callable $handler): never
{
    try {
        $result = $handler();
        json_response(['ok' => true] + $result);
    } catch (ApiException $error) {
        json_response(['ok' => false, 'error' => $error->errorCode, 'message' => $error->getMessage()], $error->status);
    } catch (PDOException $error) {
        error_log('Database error: ' . $error->getMessage());
        json_response(['ok' => false, 'error' => 'database_error', 'message' => 'The booking service is temporarily unavailable.'], 500);
    } catch (Throwable $error) {
        error_log('Unhandled error: ' . $error->getMessage());
        json_response(['ok' => false, 'error' => 'server_error', 'message' => 'The booking service is temporarily unavailable.'], 500);
    }
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || !is_string($data[$field]) || trim($data[$field]) === '') {
            throw new ApiException(422, "Field '{$field}' is required.", 'validation_error');
        }
    }
}

