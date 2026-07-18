<?php
declare(strict_types=1);

function database_driver(): string
{
    $driver = strtolower(trim(env('DB_CONNECTION', 'sqlite') ?? 'sqlite'));
    if (!in_array($driver, ['sqlite', 'sqlsrv'], true)) {
        throw new RuntimeException('DB_CONNECTION must be sqlite or sqlsrv.');
    }
    return $driver;
}

function database_path(): string
{
    $configured = env('DATABASE_PATH', 'storage/medicare.sqlite') ?? 'storage/medicare.sqlite';
    if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $configured) === 1) {
        return $configured;
    }
    return APP_ROOT . '/' . ltrim(str_replace('\\', '/', $configured), '/');
}

function required_database_setting(string $name): string
{
    $value = trim(env($name, '') ?? '');
    if ($value === '') {
        throw new RuntimeException("Required database setting {$name} is missing.");
    }
    return $value;
}

function connect_sqlite(): PDO
{
    $path = database_path();
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the database directory.');
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < 1) {
        $schema = file_get_contents(APP_ROOT . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Database schema is missing.');
        }
        $pdo->exec($schema);
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    }
    if ($version < 2) {
        $migration = file_get_contents(APP_ROOT . '/database/migrations/002_department_fees.sql');
        if ($migration === false) {
            throw new RuntimeException('Database fee migration is missing.');
        }
        $pdo->exec($migration);
        $version = 2;
    }
    if ($version < 3) {
        $migration = file_get_contents(APP_ROOT . '/database/migrations/003_psychology.sql');
        if ($migration === false) {
            throw new RuntimeException('Database psychology migration is missing.');
        }
        $pdo->exec($migration);
    }
    return $pdo;
}

function run_sql_server_schema(PDO $pdo): void
{
    $schema = file_get_contents(APP_ROOT . '/database/schema.sqlsrv.sql');
    if ($schema === false) {
        throw new RuntimeException('Azure SQL schema is missing.');
    }
    foreach (preg_split('/^[ \t]*GO[ \t]*$/mi', $schema) ?: [] as $batch) {
        if (trim($batch) !== '') {
            $pdo->exec($batch);
        }
    }
}

function sql_server_schema_version(PDO $pdo): int
{
    $exists = (int) $pdo->query("SELECT CASE WHEN OBJECT_ID(N'dbo.app_metadata', N'U') IS NULL THEN 0 ELSE 1 END")->fetchColumn();
    if ($exists === 0) {
        return 0;
    }
    $stmt = $pdo->query("SELECT CAST([value] AS INT) FROM app_metadata WHERE [name] = N'schema_version'");
    return (int) ($stmt->fetchColumn() ?: 0);
}

function connect_sql_server(): PDO
{
    if (!extension_loaded('pdo_sqlsrv')) {
        throw new RuntimeException('The pdo_sqlsrv PHP extension is not installed.');
    }
    $host = required_database_setting('AZURE_SQL_HOST');
    $port = trim(env('AZURE_SQL_PORT', '1433') ?? '1433');
    if (!ctype_digit($port)) {
        throw new RuntimeException('AZURE_SQL_PORT must be numeric.');
    }
    $database = required_database_setting('AZURE_SQL_DATABASE');
    $username = required_database_setting('AZURE_SQL_USERNAME');
    $password = required_database_setting('AZURE_SQL_PASSWORD');
    $dsn = "sqlsrv:Server=tcp:{$host},{$port};Database={$database};Encrypt=yes;TrustServerCertificate=no;LoginTimeout=30";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if (sql_server_schema_version($pdo) < 3) {
        run_sql_server_schema($pdo);
    }
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = database_driver() === 'sqlsrv' ? connect_sql_server() : connect_sqlite();
    return $pdo;
}

function begin_booking_transaction(PDO $pdo): void
{
    if (database_driver() === 'sqlite') {
        $pdo->exec('BEGIN IMMEDIATE');
        return;
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $pdo->beginTransaction();
}

function save_patient(PDO $pdo, string $name, string $phone, ?string $email): int
{
    if (database_driver() === 'sqlsrv') {
        $find = $pdo->prepare('SELECT id FROM patients WITH (UPDLOCK, HOLDLOCK) WHERE phone = :phone');
        $find->execute(['phone' => $phone]);
        $patientId = $find->fetchColumn();
        if ($patientId !== false) {
            $update = $pdo->prepare('UPDATE patients SET full_name = :name, email = COALESCE(:email, email), updated_at = SYSUTCDATETIME() WHERE id = :id');
            $update->execute(['name' => $name, 'email' => $email, 'id' => $patientId]);
            return (int) $patientId;
        }
        $insert = $pdo->prepare('INSERT INTO patients (full_name, phone, email) OUTPUT INSERTED.id VALUES (:name, :phone, :email)');
        $insert->execute(['name' => $name, 'phone' => $phone, 'email' => $email]);
        return (int) $insert->fetchColumn();
    }

    $patient = $pdo->prepare('INSERT INTO patients (full_name, phone, email) VALUES (:name, :phone, :email)
        ON CONFLICT(phone) DO UPDATE SET full_name = excluded.full_name, email = COALESCE(excluded.email, patients.email), updated_at = CURRENT_TIMESTAMP
        RETURNING id');
    $patient->execute(['name' => $name, 'phone' => $phone, 'email' => $email]);
    return (int) $patient->fetchColumn();
}
