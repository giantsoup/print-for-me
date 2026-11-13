<#
.SYNOPSIS
Manage per-user production worker, scheduler, or optional fallback web tasks.
.DESCRIPTION
Install and Uninstall preview by default; pass -Yes to apply. Tasks run at logon,
with least privilege and hidden conhost. Herd must remain running for normal web
serving. Scheduler defines ARTISAN_BINARY so its schedule:run children retain the
absolute checkout path for ownership checks. The web role is fallback only: remove
the managed Herd site and restart nginx before installing it. Stop drains owned children for up to 60 seconds.
.EXAMPLE
powershell -File services.ps1 -Operation Install -Role worker -Yes
#>
param(
    [Parameter(Mandatory=$true,Position=0)][ValidateSet('Install','Uninstall','Status','Run')][string]$Operation,
    [Parameter(Mandatory=$true)][ValidateSet('worker','scheduler','web')][string]$Role,
    [switch]$Yes
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Test-OwnedProcess {
    param($Process)
    $php = Join-Path $env:USERPROFILE '.config\herd\bin\php85\php.exe'
    $artisan = '"' + (Join-Path (Get-ProductionRoot) 'artisan') + '"'
    if (-not $Process -or $Process.ExecutablePath -ne $php -or -not $Process.CommandLine) { return $false }
    if ($Role -eq 'web') {
        return ($Process.CommandLine.IndexOf('"-S" "127.0.0.1:8030"', [StringComparison]::OrdinalIgnoreCase) -ge 0 -and $Process.CommandLine.IndexOf('"' + (Join-Path (Get-ProductionRoot) 'public') + '"', [StringComparison]::OrdinalIgnoreCase) -ge 0 -and $Process.CommandLine.IndexOf((Join-Path (Get-ProductionRoot) 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'), [StringComparison]::OrdinalIgnoreCase) -ge 0)
    }
    $rolePattern = if ($Role -eq 'worker') { '(?:^|\s)"?queue:work"?(?=\s|$)' } else { '(?:^|\s)"?schedule:(work|run)"?(?=\s|$)' }
    return ($Process.CommandLine.IndexOf($artisan, [StringComparison]::OrdinalIgnoreCase) -ge 0 -and $Process.CommandLine -match $rolePattern)
}

function Get-OwnedProcesses {
    return @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { Test-OwnedProcess $_ })
}

function Get-DrainingProcesses {
    return @(Get-OwnedProcesses | Where-Object { $Role -ne 'scheduler' -or $_.CommandLine -match '(?:^|\s)"?schedule:run"?(?=\s|$)' })
}

function Assert-ServicePort {
    if ($Role -ne 'web') { return }
    if (Test-Path -LiteralPath (Get-HerdSiteConfPath)) { throw 'Remove the managed Herd site and restart nginx before using fallback web.' }
    foreach ($listener in @(Get-NetTCPConnection -State Listen -LocalPort 8030 -ErrorAction SilentlyContinue)) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
        if ($listener.LocalAddress -ne '127.0.0.1' -or -not (Test-OwnedProcess $process)) { throw 'Port 8030 has an unidentified listener; refusing to stop it.' }
    }
}

function Assert-ServiceTask {
    param($Task)
    if (-not $Task) { return }
    $owner = [string]$Task.Principal.UserId
    if ($owner -notmatch '^S-1-') { $owner = (New-Object Security.Principal.NTAccount($owner)).Translate([Security.Principal.SecurityIdentifier]).Value }
    if ($owner -ne [Security.Principal.WindowsIdentity]::GetCurrent().User.Value -or @($Task.Actions).Count -ne 1 -or
        $Task.Actions[0].Execute -ne (Join-Path $env:SystemRoot 'System32\conhost.exe') -or
        $Task.Actions[0].Arguments.IndexOf(('"' + $PSCommandPath + '"'), [StringComparison]::OrdinalIgnoreCase) -lt 0 -or
        $Task.Actions[0].Arguments -notmatch ('Run -Role ' + $Role + '$')) { throw 'Task is not owned by this checkout/user/role.' }
}

function Test-SupervisorCommandLine {
    param([string]$CommandLine)
    $pattern = '(?i)\s-File\s+"?' + [regex]::Escape($PSCommandPath) + '"?\s+Run\s+-Role\s+' + [regex]::Escape($Role) + '(\s|$)'
    return [bool]($CommandLine -match $pattern)
}

function Stop-OwnedService {
    param($Task)
    Assert-ServicePort
    Assert-ServiceTask $Task
    try {
        if ($Role -eq 'worker') { Invoke-Artisan @('queue:restart') }
        elseif ($Role -eq 'scheduler') { Invoke-Artisan @('schedule:interrupt') }
    } catch { Write-Warning 'Graceful signal failed; continuing the scoped task/process stop.' }
    if ($Task) { Stop-ScheduledTask -InputObject $Task }
    foreach ($process in @(Get-CimInstance Win32_Process -Filter "Name='powershell.exe'")) {
        if ($process.ProcessId -ne $PID -and $process.ExecutablePath -eq (Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe') -and
            $process.CommandLine -and (Test-SupervisorCommandLine $process.CommandLine)) {
            $current = Get-CimInstance Win32_Process -Filter "ProcessId=$($process.ProcessId)"
            if ($current -and $current.ExecutablePath -eq $process.ExecutablePath -and $current.CommandLine -ceq $process.CommandLine -and $current.CreationDate -eq $process.CreationDate) { Stop-Process -Id $process.ProcessId -Force }
        }
    }
    if ($Role -eq 'scheduler') {
        foreach ($process in @(Get-OwnedProcesses | Where-Object { $_.CommandLine -match '(?:^|\s)"?schedule:work"?(?=\s|$)' })) {
            Stop-OwnedPhpProcess $process
        }
    }
    foreach ($attempt in 1..60) {
        if (-not @(Get-DrainingProcesses).Count) { break }
        Start-Sleep -Seconds 1
    }
    foreach ($process in @(Get-DrainingProcesses)) {
        Stop-OwnedPhpProcess $process
    }
}

function Stop-OwnedPhpProcess {
    param($Process)
    $current = Get-CimInstance Win32_Process -Filter "ProcessId=$($Process.ProcessId)"
    if ((Test-OwnedProcess $current) -and $current.CreationDate -eq $Process.CreationDate) { Stop-Process -Id $Process.ProcessId -Force }
}

function Invoke-ServiceLoop {
    do {
        try {
            $info = Get-ServiceProductionInfo
            Assert-ServicePort
            $arguments = Get-ServiceArguments
            $start = New-ChildStartInfo (Get-HerdPhp) $arguments (Get-ProductionEnv)
            $code = Invoke-LoggedChild $start (Join-Path $info.logs "$Role.log")
            Add-Content -LiteralPath (Join-Path $info.logs "$Role-loop.log") -Value "$([DateTime]::UtcNow.ToString('o')) exited $code"
        } catch { Write-Warning "Service not ready; retrying in 3 seconds: $($_.Exception.Message)" }
        Start-Sleep -Seconds 3
    } while ($true)
}

function Get-ServiceTaskXml {
    param([string]$TaskRole = $Role, [string]$ScriptPath = $PSCommandPath)
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    $conhost = [Security.SecurityElement]::Escape((Join-Path $env:SystemRoot 'System32\conhost.exe'))
    $powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $arguments = [Security.SecurityElement]::Escape("--headless `"$powershell`" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$ScriptPath`" Run -Role $TaskRole")
    $cwd = [Security.SecurityElement]::Escape((Get-RepositoryRoot))
    return @"
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
<RegistrationInfo><Description>PrintForMe production $TaskRole.</Description></RegistrationInfo>
<Triggers><LogonTrigger><Enabled>true</Enabled><UserId>$sid</UserId></LogonTrigger></Triggers>
<Principals><Principal id="Owner"><UserId>$sid</UserId><LogonType>InteractiveToken</LogonType><RunLevel>LeastPrivilege</RunLevel></Principal></Principals>
<Settings><MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy><DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries><StopIfGoingOnBatteries>false</StopIfGoingOnBatteries><StartWhenAvailable>true</StartWhenAvailable><RestartOnFailure><Interval>PT1M</Interval><Count>999</Count></RestartOnFailure><ExecutionTimeLimit>PT0S</ExecutionTimeLimit></Settings>
<Actions Context="Owner"><Exec><Command>$conhost</Command><Arguments>$arguments</Arguments><WorkingDirectory>$cwd</WorkingDirectory></Exec></Actions>
</Task>
"@
}

function Get-ServiceArguments {
    param([string]$TaskRole = $Role)
    $root = Get-ProductionRoot
    $artisan = Join-Path $root 'artisan'
    if ($TaskRole -eq 'worker') { return @($artisan,'queue:work','database','--queue=default','--sleep=3','--tries=3','--max-time=3600','--no-interaction') }
    if ($TaskRole -eq 'scheduler') {
        $bootstrap = 'define("ARTISAN_BINARY", $argv[1]); array_shift($argv); $_SERVER["argv"] = $argv; $_SERVER["argc"] = count($argv); require $argv[0];'
        return @('-r',$bootstrap,$artisan,'schedule:work','--no-interaction')
    }
    return @('-d','upload_max_filesize=64M','-d','post_max_size=64M','-d','memory_limit=256M','-d','max_execution_time=120','-S','127.0.0.1:8030','-t',(Join-Path $root 'public'),(Join-Path $root 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'))
}

try {
    . "$PSScriptRoot/common.ps1"
    if ($Operation -ne 'Run') { [void](Get-ProductionInfo -AllowIncomplete -SkipWebInfrastructure) }
    $name = Get-TaskName $Role
    $task = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
    Assert-ServiceTask $task
    if ($Operation -eq 'Status') {
        if (-not $task) { throw "Task $name is not installed." }
        $last = Get-ScheduledTaskInfo -InputObject $task
        Write-Host "$name state: $($task.State); last result: $($last.LastTaskResult); last run: $($last.LastRunTime)"
        if ($task.State -ne 'Running' -or -not @(Get-OwnedProcesses).Count) { throw "Task $name is not healthy." }
    } elseif ($Operation -eq 'Run') {
        $mutex = New-Object Threading.Mutex($false, "Local\PrintForMe-Production-$Role")
        $owned = $false
        try {
            try { $owned = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $owned=$true }
            if (-not $owned) { throw 'Another service loop owns this role.' }
            Invoke-ServiceLoop
        } finally { if ($owned) { $mutex.ReleaseMutex() }; $mutex.Dispose() }
    } else {
        Assert-ServicePort
        Write-Host "$Operation task: $name"
        if (-not $Yes) { Write-Host 'Preview only. Pass -Yes to apply.'; exit 0 }
        Stop-OwnedService $task
        if ($Operation -eq 'Uninstall') {
            if ($task) { Unregister-ScheduledTask -InputObject $task -Confirm:$false }
        } else {
            Register-ScheduledTask -TaskName $name -Xml (Get-ServiceTaskXml) -Force | Out-Null
            Start-ScheduledTask -TaskName $name
            $ready = $false
            foreach ($attempt in 1..30) {
                Start-Sleep -Milliseconds 500
                if ($Role -eq 'web') {
                    try { $ready = @(Get-OwnedProcesses).Count -gt 0 -and (Get-HttpResult 'http://127.0.0.1:8030/up').Status -eq 200 } catch { $ready=$false }
                } else { $ready = @(Get-OwnedProcesses).Count -gt 0 }
                if ($ready) { break }
            }
            if (-not $ready) { throw "Task $name did not become ready; inspect private logs." }
        }
        Write-Host "OK: $Operation $name"
    }
} catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
