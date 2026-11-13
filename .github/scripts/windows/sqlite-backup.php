<?php

use Pdo\Sqlite;

/**
 * Offline SQLite helper; run with Herd PHP 8.5, without booting Laravel.
 * Usage: sqlite-backup.php snapshot <source> <new-destination>
 *        sqlite-backup.php wal <existing-database>
 *        sqlite-backup.php check <existing-database>
 * Snapshot refuses an existing destination and verifies integrity before returning
 * JSON table counts and page_count. WAL is persistent. No restore is automatic.
 */
function openDatabase(string $path, bool $writable = false): PDO
{
    if (! is_file($path) || is_link($path)) {
        throw new RuntimeException('Expected an existing regular SQLite file.');
    }

    $database = new PDO('sqlite:'.$path, options: [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        Sqlite::ATTR_OPEN_FLAGS => $writable ? Sqlite::OPEN_READWRITE : Sqlite::OPEN_READONLY,
    ]);
    $database->exec('PRAGMA busy_timeout=30000');

    return $database;
}

function assertIntegrity(PDO $database): void
{
    $results = $database->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);

    if ($results !== ['ok']) {
        throw new RuntimeException('SQLite integrity check failed.');
    }
}

try {
    $command = $argv[1] ?? '';
    $expectedArguments = $command === 'snapshot' ? 4 : 3;

    if (! in_array($command, ['snapshot', 'wal', 'check'], true) || $argc !== $expectedArguments) {
        throw new InvalidArgumentException('Usage: snapshot <src> <new-dest> | wal <path> | check <path>');
    }

    $database = openDatabase($argv[2], $command === 'wal');

    if ($command === 'wal') {
        $mode = strtolower((string) $database->query('PRAGMA journal_mode=WAL')->fetchColumn());

        if ($mode !== 'wal') {
            throw new RuntimeException('SQLite did not enter WAL mode.');
        }

        echo $mode.PHP_EOL;
    } elseif ($command === 'check') {
        assertIntegrity($database);
        echo 'ok'.PHP_EOL;
    } else {
        $destination = $argv[3];

        if (file_exists($destination) || is_link($destination) || ! is_dir(dirname($destination))) {
            throw new RuntimeException('Snapshot requires a new destination in an existing directory.');
        }

        $database->exec('VACUUM INTO '.$database->quote($destination));
        $snapshot = openDatabase($destination);
        assertIntegrity($snapshot);
        $tables = [];

        foreach ($snapshot->query("SELECT name FROM sqlite_schema WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $identifier = '"'.str_replace('"', '""', $name).'"';
            $tables[$name] = (int) $snapshot->query('SELECT COUNT(*) FROM '.$identifier)->fetchColumn();
        }

        echo json_encode([
            'integrity' => 'ok',
            'tables' => (object) $tables,
            'page_count' => (int) $snapshot->query('PRAGMA page_count')->fetchColumn(),
        ], JSON_THROW_ON_ERROR).PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
