<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

run_api(function (): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw new ApiException(405, 'Use GET for this endpoint.', 'method_not_allowed');
    }
    db()->query('SELECT 1')->fetchColumn();
    return ['service' => 'medicare-booking', 'status' => 'ready', 'database' => database_driver(), 'timezone' => date_default_timezone_get()];
});
