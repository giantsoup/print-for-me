<?php

use Illuminate\Console\Scheduling\Schedule;

test('local private filesystem permissions stay group-shareable for background workers', function () {
    $localDisk = config('filesystems.disks.local');

    expect($localDisk['permissions']['file']['private'])->toBe(0660)
        ->and($localDisk['permissions']['dir']['private'])->toBe(02770);
});

test('workflow php versions stay aligned with composer requirements', function () {
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require']['php'])->toBe('^8.5');

    foreach ([
        '.github/workflows/tests.yml',
        '.github/workflows/lint.yml',
        '.github/workflows/deploy.yml',
    ] as $workflow) {
        expect(file_get_contents(base_path($workflow)))->toContain("php-version: '8.5'");
    }
});

test('node tooling stays aligned to the Node 24 line', function () {
    $package = json_decode(
        file_get_contents(base_path('package.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('24');
    expect(trim(file_get_contents(base_path('.node-version'))))->toBe('24');
    expect($package['engines']['node'])->toBe('^24.14.0');

    foreach ([
        '.github/workflows/tests.yml',
        '.github/workflows/deploy.yml',
    ] as $workflow) {
        expect(file_get_contents(base_path($workflow)))->toContain("node-version: '24'");
    }
});

test('deploy assets exist and runtime artifacts remain ignored', function () {
    expect(file_exists(base_path('.github/scripts/deploy.sh')))->toBeTrue();
    expect(file_exists(base_path('.github/workflows/deploy.yml')))->toBeTrue();

    expect(file_get_contents(base_path('.gitignore')))
        ->toContain('/bootstrap/cache/*.php')
        ->toContain('/storage/framework/cache/data/*')
        ->toContain('/storage/framework/sessions/*')
        ->toContain('/storage/framework/testing/*')
        ->toContain('/storage/framework/views/*')
        ->toContain('/storage/logs/*.log');
});

test('deploy script preserves private uploads across releases', function () {
    $deployScript = file_get_contents(base_path('.github/scripts/deploy.sh'));

    expect($deployScript)
        ->toContain('${shared_dir}/storage/app/private')
        ->toContain('ln -sfn "${shared_dir}/storage/app/private" "${release_dir}/storage/app/private"')
        ->toContain('mkdir -p "${release_dir}/storage/framework/cache/data"')
        ->toContain('rm -f "${release_dir}/bootstrap/cache/"*.php');
});

test('scheduled maintenance commands do not depend on horizon', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command)
        ->filter()
        ->values();

    expect($commands->join("\n"))
        ->toContain('auth:cleanup-magic-tokens')
        ->toContain('auth:purge-stale-magic-tokens')
        ->toContain('prints:warn-soft-deleted')
        ->toContain('prints:purge-completed-files')
        ->toContain('prints:purge-soft-deleted')
        ->not->toContain('horizon:snapshot');
});
