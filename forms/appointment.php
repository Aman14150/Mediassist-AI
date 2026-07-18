<?php
declare(strict_types=1);

// Retained only so old bookmarks fail safely. The email-only template handler
// cannot enforce live availability or patient confirmation.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => false,
    'error' => 'endpoint_retired',
    'message' => 'Use the live booking form at ../appointment.html.',
]);
