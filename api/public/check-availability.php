<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

run_api(function (): array {
    apply_public_cors();
    rate_limit('public-availability', 30, 60);
    return check_availability(json_input());
});

