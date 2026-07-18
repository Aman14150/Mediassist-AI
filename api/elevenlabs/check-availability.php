<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

run_api(function (): array {
    require_elevenlabs_auth();
    rate_limit('elevenlabs-availability', 120, 60);
    return check_availability(json_input());
});

