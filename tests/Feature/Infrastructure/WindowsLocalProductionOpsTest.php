<?php

use Symfony\Component\Process\Process;

it('binds the managed nginx site only to loopback and denies private files', function (): void {
    $template = file_get_contents(base_path('.github/scripts/windows/templates/print-for-me-production.nginx.conf'));
    preg_match_all('/\blisten\s+([^;]+);/', $template, $listeners);
    expect($listeners[1])->toBe(['127.0.0.1:8030'])
        ->and($template)->toContain('fastcgi_pass 127.0.0.1:9085;', 'deny all;', 'client_max_body_size 64M;', 'location = /index.php', 'return 421;')
        ->toContain('fastcgi_param HTTP_X_FORWARDED_PORT "";', 'fastcgi_param HTTP_X_FORWARDED_PREFIX "";')
        ->not->toContain('0.0.0.0', '$herd_sock');
    expect(file_get_contents(base_path('.github/scripts/windows/templates/user.ini')))
        ->toContain('upload_max_filesize = 64M', 'post_max_size = 64M', 'memory_limit = 256M', 'max_execution_time = 120', 'max_input_time = 120');
});

it('passes the side-effect-free Windows production helper regressions', function (): void {
    $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', base_path('.github/scripts/windows/verify-scripts.ps1')]);
    $process->setTimeout(120);
    $process->run();

    expect($process->getErrorOutput())->toBe('')
        ->and($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('Run light-guard', 'ExpectedSha refusal', 'case-insensitive ownership', 'scheduler drain', 'transcript capture', 'Serve warning', 'No tasks registered.');
})->skip(PHP_OS_FAMILY !== 'Windows', 'Requires Windows PowerShell 5.1 and Windows ACLs.');

it('ships only placeholder private production configuration', function (): void {
    $template = file_get_contents(base_path('.github/scripts/windows/production.env.example'));
    expect($template)->toContain(
        'APP_ENV=production', 'APP_DEBUG=false', 'MAIL_MAILER=log', 'TRUSTED_PROXIES=127.0.0.1',
        'SESSION_SECURE_COOKIE=true', 'LOG_LEVEL=debug', 'APP_KEY=REPLACE_WITH_GENERATED_KEY',
        'ADMIN_EMAIL=REPLACE_WITH_OWNER_EMAIL', 'SESSION_COOKIE=print_for_me_local_production_session',
    )->not->toMatch('/base64:[A-Za-z0-9+\/]{43}=/');
    expect(file_get_contents(base_path('config/trustedproxy.php')))->toContain("env('TRUSTED_PROXIES')");
    expect(file_get_contents(base_path('.env.example')))->toContain('# TRUSTED_PROXIES=');
});

it('guards fast-forward deployments backup and destructive commands', function (): void {
    $common = file_get_contents(base_path('.github/scripts/windows/common.ps1'));
    expect($common)->toContain(
        '$script:ProductionBranch = \'staging\'',
        '$script:ExpectedOrigin = \'https://github.com/giantsoup/print-for-me.git\'',
        "'Production\\print-for-me'", "'test','migrate:fresh','migrate:refresh','migrate:reset','migrate:rollback','db:wipe','db:seed','key:generate'",
        'EnvironmentVariables.Remove', 'Assert-PrivatePath', 'Assert-ProductionLocation',
    );
    $deploy = file_get_contents(base_path('.github/scripts/windows/deploy.ps1'));
    expect($deploy)->toContain(
        "'merge','--ff-only'", "'--no-dev'", "'migrate','--force'", "'down'", "'origin/main','origin/staging'",
        'Invoke-ProductionBackup', 'Enter-DeployMutex', 'Preview only', 'Test-TailnetHttp',
    )->not->toContain('reset --hard', 'clean -', 'serve reset');
    expect(file_get_contents(base_path('.github/scripts/windows/services.ps1')))
        ->toContain('LeastPrivilege', 'InteractiveToken', 'queue:restart', 'schedule:interrupt', 'CreationDate', 'Get-OwnedProcesses');
});

it('contains no hard-coded admin address or unsafe Tailscale mutation in scripts', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('.github/scripts/windows'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file->isFile() || $file->getFilename() === 'production.env.example') {
            continue;
        }
        expect(file_get_contents($file->getPathname()))
            ->not->toMatch('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/i')
            ->not->toContain('serve reset', 'funnel');
    }
    expect(file_get_contents(base_path('.github/scripts/windows/setup-production.ps1')))
        ->toContain('[string]$AdminEmail', "Read-Host 'Admin email", 'REPLACE_WITH_OWNER_EMAIL', 'setup-state.json');
});

it('provides top-level help and strict error handling for every PowerShell entrypoint', function (): void {
    $files = glob(base_path('.github/scripts/windows/*.ps1'));
    expect($files)->toHaveCount(9);
    foreach ($files as $file) {
        $source = ltrim(file_get_contents($file), "\xEF\xBB\xBF");
        expect($source)->toStartWith('<#')->toContain('.SYNOPSIS', 'Set-StrictMode -Version Latest');
        if (basename($file) !== 'common.ps1') {
            expect($source)->toContain('[Console]::Error.WriteLine', 'exit 1');
        }
    }
});

it('snapshots a WAL database and refuses overwrites missing files and corrupt input', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pfm-sqlite-'.bin2hex(random_bytes(12));
    mkdir($directory);
    $source = $directory.DIRECTORY_SEPARATOR.'source.sqlite';
    $snapshot = $directory.DIRECTORY_SEPARATOR.'snapshot.sqlite';
    $helper = base_path('.github/scripts/windows/sqlite-backup.php');
    $run = function (array $arguments) use ($helper): Process {
        $process = new Process([PHP_BINARY, $helper, ...$arguments]);
        $process->run();

        return $process;
    };

    try {
        $database = new PDO('sqlite:'.$source);
        $database->exec('CREATE TABLE "odd""name" (id INTEGER); INSERT INTO "odd""name" VALUES (1), (2)');
        $wal = $run(['wal', $source]);
        expect($wal->getExitCode())->toBe(0)->and(trim($wal->getOutput()))->toBe('wal');
        $database->exec('INSERT INTO "odd""name" VALUES (3)');
        $result = $run(['snapshot', $source, $snapshot]);
        expect($result->getExitCode())->toBe(0)->and($result->getErrorOutput())->toBe('');
        $summary = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($summary['integrity'])->toBe('ok')->and($summary['tables']['odd"name'])->toBe(3)
            ->and($summary['page_count'])->toBeGreaterThan(0);
        $hash = hash_file('sha256', $snapshot);
        expect($run(['snapshot', $source, $snapshot])->getExitCode())->toBe(1)
            ->and(hash_file('sha256', $snapshot))->toBe($hash);
        $check = $run(['check', $snapshot]);
        expect($check->getExitCode())->toBe(0)->and(trim($check->getOutput()))->toBe('ok');
        $missing = $directory.DIRECTORY_SEPARATOR.'missing.sqlite';
        expect($run(['wal', $missing])->getExitCode())->toBe(1)->and(file_exists($missing))->toBeFalse();
        $corrupt = $directory.DIRECTORY_SEPARATOR.'corrupt.sqlite';
        file_put_contents($corrupt, 'this is not SQLite');
        expect($run(['check', $corrupt])->getExitCode())->toBe(1)
            ->and($run(['invalid', $source])->getExitCode())->toBe(1);
    } finally {
        $database = null;
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});
