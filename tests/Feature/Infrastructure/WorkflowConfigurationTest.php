<?php

use Illuminate\Console\Scheduling\Schedule;
use Symfony\Component\Yaml\Yaml;

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

test('node and npm tooling stay aligned across local pins dependencies and workflows', function () {
    $package = json_decode(
        file_get_contents(base_path('package.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $lock = json_decode(
        file_get_contents(base_path('package-lock.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $nodeVersion = trim(file_get_contents(base_path('.node-version')));
    $nodeMajor = explode('.', $nodeVersion)[0];
    $npmVersion = substr($package['packageManager'], strlen('npm@'));

    expect($nodeVersion)->toMatch('/^26\.\d+\.\d+$/')
        ->and(trim(file_get_contents(base_path('.nvmrc'))))->toBe($nodeVersion)
        ->and($package['engines']['node'])->toBe('^'.$nodeVersion)
        ->and($package['packageManager'])->toMatch('/^npm@12\.\d+\.\d+$/')
        ->and($package['engines']['npm'])->toBe('^'.$npmVersion)
        ->and($package['devDependencies']['@types/node'])->toStartWith('^'.$nodeMajor.'.')
        ->and($lock['packages']['']['engines'])->toBe($package['engines'])
        ->and($lock['packages']['node_modules/@types/node']['version'])->toStartWith($nodeMajor.'.');

    foreach ([
        '.github/workflows/tests.yml',
        '.github/workflows/lint.yml',
        '.github/workflows/deploy.yml',
    ] as $workflow) {
        $configuration = Yaml::parseFile(base_path($workflow));
        $nodeJobs = 0;

        foreach ($configuration['jobs'] as $job) {
            $steps = collect($job['steps']);
            $setupNode = $steps->first(fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'actions/setup-node@'));

            if ($setupNode === null) {
                continue;
            }

            $nodeJobs++;
            $npmInstallation = $steps->search(fn (array $step): bool => str_contains($step['run'] ?? '', 'npm install --global')
                && str_contains($step['run'], "require('./package.json').packageManager"));
            $dependencyInstallation = $steps->search(fn (array $step): bool => str_contains($step['run'] ?? '', 'npm ci'));

            expect($setupNode['with']['node-version-file'] ?? null)->toBe('.node-version')
                ->and($npmInstallation)->toBeInt()
                ->and($dependencyInstallation)->toBeInt()
                ->and($npmInstallation)->toBeLessThan($dependencyInstallation);
        }

        expect($nodeJobs)->toBeGreaterThan(0);
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
