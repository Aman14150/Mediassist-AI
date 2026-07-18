<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

run_api(function (): array {
    apply_public_cors();
    rate_limit('public-booking', 8, 600);
    return create_appointment(json_input(), 'website');
});

