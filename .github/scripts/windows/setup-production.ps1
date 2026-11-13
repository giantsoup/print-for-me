<#
.SYNOPSIS
Prepare owner-only Windows production; preview by default, -Yes applies.
.DESCRIPTION
Requires Herd running, PHP 8.5, pinned Node, Composer, npm, Git and Tailscale.
Creates a separate staging clone and private state. Never edit or clean production.
Reruns resume safely; existing production.env and APP_KEY are never overwritten.
Provide -AdminEmail or answer the interactive prompt. No email is sent.
No Tailscale configuration is changed: define/approve svc:print-for-me separately.
The instance must remain owner-only until the deferred source-preview SSRF is fixed.
.EXAMPLE
powershell -File setup-production.ps1
.EXAMPLE
powershell -File setup-production.ps1 -Yes -AdminEmail <owner-email>
#>
param([string]$AdminEmail, [string]$AdminName, [switch]$Yes)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$mutex = $null
try {
    . "$PSScriptRoot/common.ps1"
    Assert-Preflight
    $root = Get-ProductionRoot
    $homePath = Get-ProductionHome
    Write-Host "Plan: staging clone at $root; private SQLite/env/backups at $homePath; Herd on loopback 8030; log mail; worker and scheduler logon tasks."
    if (-not $Yes) { Write-Host 'Preview only; pass -Yes to apply. AdminEmail is required when applying.'; exit 0 }
    if (-not $AdminEmail) {
        if (-not [Environment]::UserInteractive -or [Console]::IsInputRedirected) { throw '-AdminEmail is required.' }
        $AdminEmail = Read-Host 'Admin email (same as the Linode admin)'
    }
    $AdminEmail = $AdminEmail.Trim().ToLowerInvariant()
    if ($AdminEmail -notmatch '^[^@\s]+@[^@\s]+\.[^@\s]+$' -or $AdminEmail -match '["''$\\#`]') { throw 'Invalid -AdminEmail.' }
    if (-not (Test-Path -LiteralPath $root)) {
        Invoke-Checked 'git.exe' @('clone','--no-hardlinks','--branch','staging','--single-branch',$script:ExpectedOrigin,$root)
    }
    if ((Get-RepositoryRoot) -ne $root) {
        $arguments = @('-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $root '.github\scripts\windows\setup-production.ps1'),'-Yes','-AdminEmail',$AdminEmail)
        if ($AdminName) { $arguments += @('-AdminName',$AdminName) }
        Invoke-Checked 'powershell.exe' $arguments
        exit 0
    }
    $mutex = Enter-DeployMutex
    Assert-ProductionGit
    $fetchSpecs = Invoke-Git @('config','--get-all','remote.origin.fetch')
    if ($fetchSpecs -notmatch 'refs/heads/(main|\*)') { [void](Invoke-Git @('config','--add','remote.origin.fetch','+refs/heads/main:refs/remotes/origin/main')) }
    foreach ($path in @($homePath,(Join-Path $homePath 'data'),(Join-Path $homePath 'backups'),(Join-Path $homePath 'logs'),(Join-Path $homePath 'nginx'))) { New-PrivateDirectory $path }
    $canonical = Get-ProductionEnv
    if (-not (Test-Path -LiteralPath $canonical)) {
        $bytes = New-Object byte[] 32
        $random = [Security.Cryptography.RandomNumberGenerator]::Create()
        try { $random.GetBytes($bytes) } finally { $random.Dispose() }
        $key = 'base64:' + [Convert]::ToBase64String($bytes)
        $text = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'production.env.example'))
        $text = $text.Replace('REPLACE_WITH_GENERATED_KEY',$key).Replace('C:/Users/REPLACE_USER/AppData/Local/PrintForMe/production/data/database.sqlite',((Get-ProductionDatabase).Replace('\','/'))).Replace('REPLACE_WITH_OWNER_EMAIL',$AdminEmail).Replace('https://print-for-me.chimp-hamal.ts.net',(Get-TailnetUrl))
        Write-Utf8File $canonical $text
        Protect-PrivatePath $canonical
    }
    $values = Assert-ProductionEnv
    if ($values.ADMIN_EMAIL -cne $AdminEmail) { throw 'Existing ADMIN_EMAIL differs; production.env was not changed.' }
    $statePath = Join-Path $homePath 'setup-state.json'
    if (Test-Path -LiteralPath $statePath) {
        $state = [IO.File]::ReadAllText($statePath) | ConvertFrom-Json
        if ($state.complete) {
            [void](Get-ProductionInfo)
            Invoke-Checked 'powershell.exe' @('-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'status.ps1'),'-AllowMissingServe')
            Write-Host 'OK: setup already complete; use deploy.ps1'
            exit 0
        }
    }
    $checkpoint = @{complete=$false; completed_steps=@(); checkout=$root; app_url=$values.APP_URL}
    function Save-SetupCheckpoint {
        param([string]$Step)
        $checkpoint.completed_steps += $Step
        Write-Utf8File $statePath ($checkpoint | ConvertTo-Json -Depth 4)
    }
    Save-SetupCheckpoint 'private-env'
    $exclude = Join-Path $root '.git\info\exclude'
    Assert-PlainPath $exclude
    if ([IO.File]::ReadAllText($exclude) -notmatch '(?m)^/public/\.user\.ini\r?$') { Add-Content -LiteralPath $exclude -Value '/public/.user.ini' }
    Sync-UserIni
    Sync-CheckoutEnv
    foreach ($relative in @('storage\framework\cache\data','storage\framework\sessions','storage\framework\views','storage\logs','storage\app\private','storage\app\public','bootstrap\cache')) {
        $path = Join-Path $root $relative
        Assert-PlainPath $path
        [void][IO.Directory]::CreateDirectory($path)
    }
    Protect-PrivatePath (Join-Path $root 'storage')
    Protect-PrivatePath (Join-Path $root 'bootstrap\cache')
    Save-SetupCheckpoint 'checkout-files'
    Invoke-Composer @('install','--no-dev','--prefer-dist','--no-interaction','--optimize-autoloader')
    Invoke-Npm @('ci')
    Invoke-Npm @('run','build')
    $hot = Join-Path $root 'public\hot'
    Assert-PlainPath $hot
    if (Test-Path -LiteralPath $hot) { Remove-Item -LiteralPath $hot -Force }
    Assert-ViteBuild
    Save-SetupCheckpoint 'dependencies-build'
    $db = Get-ProductionDatabase
    Assert-PlainPath $db
    if (-not (Test-Path -LiteralPath $db)) { [IO.File]::WriteAllBytes($db, (New-Object byte[] 0)) }
    $wal = Invoke-NativeResult (Get-HerdPhp) @((Join-Path $PSScriptRoot 'sqlite-backup.php'),'wal',$db) $canonical
    if ($wal.ExitCode -ne 0 -or $wal.Output.Trim() -ne 'wal') { throw 'Could not enable SQLite WAL.' }
    Invoke-Artisan @('optimize:clear')
    Invoke-Artisan @('migrate','--force')
    Invoke-Artisan @('optimize')
    Assert-StorageLink
    Save-SetupCheckpoint 'database-cache'
    Install-HerdSite
    Save-SetupCheckpoint 'herd-site'
    Invoke-Artisan @('auth:make-admin',$AdminEmail,'--quiet',"--name=$AdminName")
    Write-Host "Admin: $AdminEmail"
    foreach ($role in @('worker','scheduler')) { Invoke-Services Install $role -Yes }
    Save-SetupCheckpoint 'admin-tasks'
    Invoke-Artisan @('up')
    Test-ProductionHttp
    Assert-ApplicationEnvironment
    Write-Utf8File $statePath (@{complete=$true; checkout=$root; origin=$script:ExpectedOrigin; port=8030; db=$db; app_url=$values.APP_URL; sha=(Invoke-Git @('rev-parse','HEAD')); completed_at=[DateTime]::UtcNow.ToString('o')} | ConvertTo-Json)
    Write-Host "Next, after defining/approving the Service: & '$env:ProgramFiles\Tailscale\tailscale.exe' serve --service=svc:print-for-me --https=443 --bg http://127.0.0.1:8030"
    Write-Host 'Then run login-link.ps1 -Email <owner-email> and open the link on your device within 10 minutes.'
} catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
finally { if ($mutex) { $mutex.ReleaseMutex(); $mutex.Dispose() } }
