<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

run_api(function (): array {
    rate_limit('elevenlabs-booking', 30, 60);
    return create_appointment(json_input(), 'elevenlabs');
});
