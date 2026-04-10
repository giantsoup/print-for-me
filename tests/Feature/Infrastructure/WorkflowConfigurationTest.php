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
        ->toContain('APP_GROUP="${APP_GROUP:-pfm}"')
        ->toContain('WEB_USER="${WEB_USER:-www-data}"')
        ->toContain('${shared_dir}/storage/app/private')
        ->toContain('ln -sfn "${shared_dir}/storage/app/private" "${release_dir}/storage/app/private"')
        ->toContain('chgrp "${APP_GROUP}" "${shared_dir}/.env"')
        ->toContain('chmod 640 "${shared_dir}/.env"')
        ->toContain('chmod o-rwx "${shared_sensitive_paths[@]}"')
        ->toContain('chmod 2750 "${runtime_boundary_paths[@]}"')
        ->toContain('chmod o-rwx "${release_writable_paths[@]}"')
        ->toContain('setfacl -m "u:${WEB_USER}:rx" "${DEPLOY_PATH}" "${DEPLOY_PATH}/releases" "${release_dir}"')
        ->toContain('setfacl -R -m "u:${WEB_USER}:rX" "${release_dir}/public"')
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
