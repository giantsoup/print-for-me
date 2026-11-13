<#
.SYNOPSIS
Preview a staging fast-forward deploy; -Yes backs up and applies it.
.DESCRIPTION
Run only from production. Never edit or clean production. Holds the shared deploy
mutex while applying. Failure before up leaves maintenance enabled and tasks removed;
fix forward and rerun -Yes. A health failure after up leaves the instance up.
Ops script updates require a second -Yes run to exercise the new deploy logic.
No automatic rollback, database restore, backup deletion, or Tailscale changes.
Pass the preview's optional -ExpectedSha with -Yes to refuse a changed staging tip.
Restore manually: run artisan.ps1 down, uninstall worker/scheduler, remove DB -wal
and -shm sidecars, copy a verified backup database.sqlite to the production data DB,
run sqlite-backup.php wal on it, and copy storage-app back to storage/app. Restore
production.env from that backup only if needed (it contains the original APP_KEY).
Checking out the backup SHA detached requires owner approval because it breaks the
staging/fast-forward invariant. Return to staging afterwards. Run optimize, install
worker/scheduler, and up. Never run tests against production.
#>
param([switch]$Yes, [ValidatePattern('^[a-fA-F0-9]{40}$')][string]$ExpectedSha)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$mutex=$null; $transcript=$false; $maintenance=$false; $up=$false; $backup=$null; $step='guards'
try {
    . "$PSScriptRoot/common.ps1"
    if ($Yes) { $mutex = Enter-DeployMutex }
    $info = Get-ProductionInfo
    Assert-ProductionGit
    [void](Invoke-Git @('fetch','origin'))
    $incomingSha = Invoke-Git @('rev-parse','origin/staging')
    if ($Yes) { Assert-ExpectedDeploySha $ExpectedSha $incomingSha }
    if ((Invoke-NativeResult 'git.exe' @('merge-base','--is-ancestor','HEAD','origin/staging')).ExitCode -ne 0) { throw 'Cannot fast-forward; staging was rewritten. Investigate, never force.' }
    if ((Invoke-NativeResult 'git.exe' @('merge-base','--is-ancestor','origin/main','origin/staging')).ExitCode -ne 0) { throw 'main has commits not in staging (emergency hotfix?). Restore ancestry before deploying.' }
    Write-Host ('Current SHA: ' + (Invoke-Git @('rev-parse','HEAD')))
    Write-Host ('Incoming staging commits: ' + (Invoke-Git @('log','--oneline','HEAD..origin/staging')))
    Write-Host ('Not promoted to Linode: ' + (Invoke-Git @('log','--oneline','origin/main..origin/staging')))
    $changed = Invoke-Git @('diff','--name-only','--diff-filter=AM','HEAD','origin/staging')
    Write-Host ('Changed migrations: ' + (($changed -split "`n" | Where-Object { $_ -like 'database/migrations/*' }) -join ', '))
    Write-Host ('Changed dependency/ops files: ' + (($changed -split "`n" | Where-Object { $_ -in @('composer.lock','package-lock.json') -or $_ -like '.github/scripts/windows/*' }) -join ', '))
    $oldTemplate = (Read-EnvFile (Join-Path $PSScriptRoot 'production.env.example')).Keys
    $incomingTemplate = Invoke-Git @('show','origin/staging:.github/scripts/windows/production.env.example')
    $newKeys = @([regex]::Matches($incomingTemplate,'(?m)^([A-Z][A-Z0-9_]*)=') | ForEach-Object { $_.Groups[1].Value })
    Write-Host ('New env template keys: ' + (@($newKeys | Where-Object { $_ -notin $oldTemplate }) -join ', '))
    $size = (Get-ChildItem -LiteralPath (Join-Path $info.home 'backups') -File -Recurse -Force | Measure-Object Length -Sum).Sum
    Write-Host "Retained backup bytes: $size"
    try { Test-HerdSite; Test-ProductionHttp } catch { Write-Warning "Current health: $($_.Exception.Message)" }
    if (-not $Yes) { Write-Host "Preview only; deploy this SHA with: & '$($PSCommandPath.Replace("'", "''"))' -Yes -ExpectedSha $incomingSha"; exit 0 }
    Start-Transcript -LiteralPath (Join-Path $info.logs ('deploy-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffffffZ') + '.log')) | Out-Null
    $transcript=$true
    $step='maintenance and drain'
    Invoke-Artisan @('down','--retry=30','--render=errors::503')
    $maintenance=$true
    foreach ($role in @('scheduler','worker')) { Invoke-Services Uninstall $role -Yes }
    Start-Sleep -Seconds 3
    $step='backup'
    $backup = Invoke-ProductionBackup
    $step='fast-forward'
    [void](Invoke-Git @('merge','--ff-only',$incomingSha))
    if ($changed -match '(?m)^\.github/scripts/windows/') { Write-Host 'Ops scripts changed; remaining steps use new services/templates. Re-run deploy.ps1 -Yes after completion to execute new deploy logic end-to-end.' }
    $step='env and build'
    [void](Assert-ProductionEnv)
    Sync-CheckoutEnv
    Sync-UserIni
    Invoke-Composer @('install','--no-dev','--prefer-dist','--no-interaction','--optimize-autoloader')
    Invoke-Npm @('ci')
    Invoke-Npm @('run','build')
    $hot = Join-Path $info.root 'public\hot'
    Assert-PlainPath $hot
    if (Test-Path -LiteralPath $hot) { Remove-Item -LiteralPath $hot -Force }
    Assert-ViteBuild
    $step='migrate and optimize'
    Invoke-Artisan @('migrate','--force')
    Invoke-Artisan @('optimize:clear')
    Invoke-Artisan @('optimize')
    Assert-StorageLink
    $step='Herd and tasks'
    Install-HerdSite
    foreach ($role in @('worker','scheduler')) { Invoke-Services Install $role -Yes }
    Invoke-Artisan @('up')
    $up=$true
    $step='health'
    Test-ProductionHttp
    Test-TailnetHttp
    Assert-ApplicationEnvironment
    Write-Host ('Deployed ' + (Invoke-Git @('rev-parse','--short','HEAD')) + ' to local production.')
} catch {
    [Console]::Error.WriteLine("Failed at ${step}: $($_.Exception.Message)")
    if ($maintenance -and -not $up) {
        foreach ($role in @('scheduler','worker')) {
            try { Invoke-Services Uninstall $role -Yes } catch { [Console]::Error.WriteLine("Could not uninstall ${role}: $($_.Exception.Message)") }
        }
        [Console]::Error.WriteLine('App remains in maintenance. Fix forward, then re-run deploy.ps1 -Yes.')
    } elseif ($up) { [Console]::Error.WriteLine('Health failed after up; instance remains up.') }
    [Console]::Error.WriteLine("Backup: $backup (partial backups may also be in the backups directory). Restore instructions: Get-Help deploy.ps1 -Full.")
    exit 1
} finally {
    if ($transcript) { Stop-Transcript | Out-Null }
    if ($mutex) { $mutex.ReleaseMutex(); $mutex.Dispose() }
}
