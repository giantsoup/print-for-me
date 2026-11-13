<#
.SYNOPSIS
Preview a manual production backup; -Yes drains tasks and snapshots data.
.DESCRIPTION
Uses the deploy mutex, maintenance mode, SQLite VACUUM INTO, upload copies and a
verified SHA256 manifest. Includes production.env with APP_KEY; backups are private.
Keeps all backups. On failure maintenance stays enabled and tasks stay removed;
fix forward and rerun deploy.ps1 -Yes. Restore steps are in deploy.ps1 help.
#>
param([switch]$Yes)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$mutex=$null; $drained=$false
try {
    . "$PSScriptRoot/common.ps1"
    if ($Yes) { $mutex = Enter-DeployMutex }
    $info = Get-ProductionInfo
    Write-Host "Backup plan: SQLite, private/public uploads and production.env into $($info.home)\backups, with integrity and hashes."
    if (-not $Yes) { Write-Host 'Preview only. Pass -Yes to apply.'; exit 0 }
    Invoke-Artisan @('down','--retry=30','--render=errors::503')
    $drained=$true
    foreach ($role in @('scheduler','worker')) { Invoke-Services Uninstall $role -Yes }
    Start-Sleep -Seconds 3
    $folder = Invoke-ProductionBackup
    foreach ($role in @('worker','scheduler')) { Invoke-Services Install $role -Yes }
    Invoke-Artisan @('up')
    $drained=$false
    Test-ProductionHttp
    Write-Host "Verified backup: $folder"
} catch {
    [Console]::Error.WriteLine($_.Exception.Message)
    if ($drained) {
        foreach ($role in @('scheduler','worker')) {
            try { Invoke-Services Uninstall $role -Yes } catch { [Console]::Error.WriteLine("Task cleanup failed: $role") }
        }
        [Console]::Error.WriteLine('Maintenance remains enabled. Fix forward and rerun deploy.ps1 -Yes.')
    }
    exit 1
} finally { if ($mutex) { $mutex.ReleaseMutex(); $mutex.Dispose() } }
