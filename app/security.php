<?php
declare(strict_types=1);

function bearer_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
        return '';
    }
    return trim($matches[1]);
}

function require_elevenlabs_auth(): void
{
    $expected = env('ELEVENLABS_WEBHOOK_SECRET', '') ?? '';
    if (strlen($expected) < 32) {
        throw new ApiException(503, 'Webhook authentication is not configured.', 'configuration_error');
    }
    $provided = bearer_token();
    if ($provided === '' || !hash_equals($expected, $provided)) {
        throw new ApiException(401, 'Invalid webhook credentials.', 'unauthorized');
    }
}

function request_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
}

function rate_limit(string $bucket, int $limit, int $windowSeconds): void
{
    $key = hash('sha256', $bucket . '|' . request_ip());
    $now = time();
    if (database_driver() === 'sqlsrv') {
        $stmt = db()->prepare('MERGE rate_limits WITH (HOLDLOCK) AS target
            USING (SELECT :key AS rate_key, :now AS current_epoch, :cutoff AS cutoff_epoch) AS source
            ON target.[key] = source.rate_key
            WHEN MATCHED THEN UPDATE SET
                window_started_at = CASE WHEN target.window_started_at <= source.cutoff_epoch THEN source.current_epoch ELSE target.window_started_at END,
                request_count = CASE WHEN target.window_started_at <= source.cutoff_epoch THEN 1 ELSE target.request_count + 1 END
            WHEN NOT MATCHED THEN INSERT ([key], window_started_at, request_count)
                VALUES (source.rate_key, source.current_epoch, 1)
            OUTPUT INSERTED.window_started_at, INSERTED.request_count;');
    } else {
        $stmt = db()->prepare('INSERT INTO rate_limits (key, window_started_at, request_count) VALUES (:key, :now, 1)
            ON CONFLICT(key) DO UPDATE SET
                window_started_at = CASE WHEN window_started_at <= :cutoff THEN :now ELSE window_started_at END,
                request_count = CASE WHEN window_started_at <= :cutoff THEN 1 ELSE request_count + 1 END
            RETURNING window_started_at, request_count');
    }
    $stmt->execute(['key' => $key, 'now' => $now, 'cutoff' => $now - $windowSeconds]);
    $row = $stmt->fetch();
    if ($row && (int) $row['request_count'] > $limit) {
        throw new ApiException(429, 'Too many requests. Please wait and try again.', 'rate_limited');
    }
}

function slot_token(int $doctorId, string $date, string $time): string
{
    $secret = env('SLOT_TOKEN_SECRET', '') ?? '';
    if (strlen($secret) < 32) {
        throw new ApiException(503, 'Slot signing is not configured.', 'configuration_error');
    }
    $expires = time() + 600;
    $payload = implode('|', [$doctorId, $date, $time, $expires]);
    $signature = hash_hmac('sha256', $payload, $secret);
    return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
}

function verify_slot_token(string $token, int $doctorId, string $date, string $time): void
{
    $decoded = base64_decode(strtr($token, '-_', '+/'), true);
    if ($decoded === false) {
        throw new ApiException(422, 'The slot token is invalid. Check availability again.', 'invalid_slot_token');
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 5) {
        throw new ApiException(422, 'The slot token is invalid. Check availability again.', 'invalid_slot_token');
    }
    [$tokenDoctor, $tokenDate, $tokenTime, $expires, $signature] = $parts;
    $payload = implode('|', array_slice($parts, 0, 4));
    $expected = hash_hmac('sha256', $payload, env('SLOT_TOKEN_SECRET', '') ?? '');
    if (!hash_equals($expected, $signature) || (int) $tokenDoctor !== $doctorId || $tokenDate !== $date || $tokenTime !== $time || (int) $expires < time()) {
        throw new ApiException(422, 'The slot token expired or does not match. Check availability again.', 'invalid_slot_token');
    }
}

function apply_public_cors(): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') {
        return;
    }
    $allowed = array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS', '') ?? '')));
    if (!in_array($origin, $allowed, true)) {
        throw new ApiException(403, 'This website origin is not allowed.', 'origin_not_allowed');
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
