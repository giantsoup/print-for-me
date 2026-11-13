<#
.SYNOPSIS
Offline self-check: parse scripts and exercise helpers using disposable synthetic data.
.DESCRIPTION
Runs under Windows PowerShell 5.1. Never reads production secrets, contacts Tailscale,
changes Herd, registers tasks, or starts production processes. Checks ACLs, child env,
quoting, stdout/stderr, streaming logs, task XML, ownership, templates and SQLite backups.
Only its uniquely named temporary directory is created and removed.
#>
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$scratch=$null
function Assert-SelfCheckFailure {
    param([scriptblock]$Action, [string]$Message)
    $failure=$null
    try { & $Action } catch { $failure=$_.Exception.Message }
    if (-not $failure -or ($Message -and $failure -notlike "*$Message*")) { throw "Expected refusal: $Message; received: $failure" }
}
try {
    . "$PSScriptRoot/common.ps1"
    $count=0
    foreach ($file in Get-ChildItem -LiteralPath $PSScriptRoot -Filter '*.ps1') {
        $tokens=$null; $parseErrors=$null
        [void][System.Management.Automation.Language.Parser]::ParseFile($file.FullName,[ref]$tokens,[ref]$parseErrors)
        if ($parseErrors.Count) { throw "Parse errors in $($file.Name): $($parseErrors.Message -join ', ')" }
        $count++
    }
    $scratch=Join-Path ([IO.Path]::GetTempPath()) ('print-for-me-ps-check-' + [Guid]::NewGuid().ToString('N'))
    New-PrivateDirectory $scratch
    $syntheticEnv=Join-Path $scratch 'synthetic.env'
    Write-Utf8File $syntheticEnv "CUSTOM_TEST_KEY=synthetic`nAPP_ENV=production`nDB_DATABASE=synthetic`nQUOTED=`"a b`"`n"
    Protect-PrivatePath $syntheticEnv
    Assert-PrivatePath $syntheticEnv
    if ((Read-EnvFile $syntheticEnv).QUOTED -ne 'a b') { throw 'Env quote parsing failed.' }
    $original=@{}
    $keys=@('APP_ENV','DB_DATABASE','CUSTOM_TEST_KEY','NODE_ENV','LARAVEL_STORAGE_PATH','PHP_INI_SCAN_DIR','DB_URL','XDEBUG_MODE')
    try {
        foreach ($key in $keys) { $original[$key]=[Environment]::GetEnvironmentVariable($key,'Process'); [Environment]::SetEnvironmentVariable($key,'synthetic-parent','Process') }
        $start=New-ChildStartInfo 'powershell.exe' @('-NoProfile','-NonInteractive') $syntheticEnv
        foreach ($key in $keys) { if ($start.EnvironmentVariables.ContainsKey($key)) { throw "Ambient env was not stripped: $key" } }
        if (-not $start.CreateNoWindow -or $start.UseShellExecute) { throw 'Child must be hidden and use direct process execution.' }
    } finally { foreach ($key in $keys) { [Environment]::SetEnvironmentVariable($key,$original[$key],'Process') } }
    $result=Invoke-NativeResult 'powershell.exe' @('-NoProfile','-NonInteractive','-Command','[Console]::Out.Write("out"); [Console]::Error.Write("err"); exit 7') $syntheticEnv
    if ($result.ExitCode -ne 7 -or $result.Output -ne 'out' -or $result.Error -ne 'err') { throw 'Native stdout/stderr/exit capture failed.' }
    $php=Join-Path $env:USERPROFILE '.config\herd\bin\php85\php.exe'
    $expected='space "quote" trailing\'
    $quoted=Invoke-NativeResult $php @('-r','echo $argv[1];',$expected) $syntheticEnv
    if ($quoted.ExitCode -ne 0 -or $quoted.Output -cne $expected) { throw 'Native argument quoting failed.' }
    $cmd=Join-Path $scratch 'synthetic.cmd'
    Write-Utf8File $cmd "@echo off`r`necho cmd-ok`r`nexit /b 3`r`n"
    $cmdResult=Invoke-NativeResult $cmd @() $syntheticEnv
    if ($cmdResult.ExitCode -ne 3 -or $cmdResult.Output.Trim() -ne 'cmd-ok') { throw 'cmd wrapper failed.' }
    $log=Join-Path $scratch 'child.log'
    $logged=New-ChildStartInfo $php @('-r','fwrite(STDOUT,"logged-out\n"); fwrite(STDERR,"logged-err\n"); exit(4);') $syntheticEnv
    if ((Invoke-LoggedChild $logged $log) -ne 4 -or [IO.File]::ReadAllText($log) -notmatch 'logged-out' -or [IO.File]::ReadAllText($log) -notmatch 'logged-err') { throw 'Streaming log capture failed.' }
    $transcriptPath=Join-Path $scratch 'deploy-transcript.log'
    Start-Transcript -LiteralPath $transcriptPath | Out-Null
    try {
        Assert-SelfCheckFailure { Invoke-Checked $php @('-r','fwrite(STDOUT,"transcript-out"); fwrite(STDERR,"transcript-err"); exit(7);') $syntheticEnv } 'failed (exit 7)'
    } finally { Stop-Transcript | Out-Null }
    $transcriptText=[IO.File]::ReadAllText($transcriptPath)
    if ($transcriptText -notmatch 'transcript-out' -or $transcriptText -notmatch 'transcript-err') { throw 'PS 5.1 transcript lost native stdout/stderr.' }
    $tokens=$null; $parseErrors=$null
    $ast=[System.Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'services.ps1'),[ref]$tokens,[ref]$parseErrors)
    $functionDefinitions=@()
    foreach ($functionName in @('Get-ServiceTaskXml','Test-OwnedProcess','Get-ServiceArguments','Stop-OwnedService','Assert-ServiceTask','Stop-OwnedPhpProcess','Get-OwnedProcesses','Get-DrainingProcesses','Invoke-ServiceLoop')) {
        $definition=$ast.Find({param($node) $node -is [Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq $functionName},$true)
        $functionDefinitions += $definition.Extent.Text
    }
    $serviceFunctions=Join-Path $scratch 'service-functions.ps1'
    Write-Utf8File $serviceFunctions ($functionDefinitions -join [Environment]::NewLine)
    . $serviceFunctions
    foreach ($Role in @('worker','scheduler','web')) {
        $scriptPath=Join-Path $PSScriptRoot 'services.ps1'
        $xml=[xml](Get-ServiceTaskXml $Role $scriptPath)
        if ($xml.Task.Principals.Principal.RunLevel -ne 'LeastPrivilege' -or $xml.Task.Principals.Principal.LogonType -ne 'InteractiveToken' -or
            $xml.Task.Settings.MultipleInstancesPolicy -ne 'IgnoreNew' -or $xml.Task.Settings.ExecutionTimeLimit -ne 'PT0S' -or
            $xml.Task.Actions.Exec.Arguments -notmatch ('--headless.+-File.+Run -Role ' + $Role + '$')) { throw "Task XML contract failed: $Role" }
        $artisan='"' + (Join-Path (Get-ProductionRoot) 'artisan') + '"'
        $command = if ($Role -eq 'worker') { $artisan + ' "queue:work"' } elseif ($Role -eq 'scheduler') { $artisan + ' schedule:run' } else { '"-S" "127.0.0.1:8030" "-t" "' + (Join-Path (Get-ProductionRoot) 'public') + '" "' + (Join-Path (Get-ProductionRoot) 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php') + '"' }
        $process=[pscustomobject]@{ExecutablePath=$php; CommandLine=$command}
        if (-not (Test-OwnedProcess $process)) { throw "Owned process rejected: $Role" }
        $process.ExecutablePath=$php.ToUpperInvariant(); $process.CommandLine=$command.ToUpperInvariant()
        if (-not (Test-OwnedProcess $process)) { throw "Case-insensitive owned process rejected: $Role" }
        $process.ExecutablePath='C:\unrelated\php.exe'
        if (Test-OwnedProcess $process) { throw "Unrelated executable accepted: $Role" }
        $process.ExecutablePath=$php; $process.CommandLine=$command.Replace((Get-ProductionRoot),'C:\unrelated')
        if (Test-OwnedProcess $process) { throw "Unrelated checkout accepted: $Role" }
        $task=[pscustomobject]@{
            Principal=[pscustomobject]@{UserId=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value}
            Actions=@([pscustomobject]@{Execute=(Join-Path $env:SystemRoot 'System32\conhost.exe').ToUpperInvariant(); Arguments='--headless powershell.exe -File "' + $serviceFunctions.ToUpperInvariant() + '" Run -Role ' + $Role})
        }
        Assert-ServiceTask $task
        $task.Actions[0].Arguments='--headless powershell.exe -File "C:\unrelated\services.ps1" Run -Role ' + $Role
        Assert-SelfCheckFailure { Assert-ServiceTask $task } 'Task is not owned'
    }
    & {
        $fixture=@{Repository=(Join-Path $scratch 'service-checkout'); Home=(Join-Path $scratch 'service-home')}
        function Get-RepositoryRoot { return $fixture.Repository }
        function Get-ProductionRoot { return (Join-Path $scratch 'service-checkout') }
        function Get-ProductionHome { return $fixture.Home }
        function Get-ProductionInfo { throw 'Full preflight must not run at logon.' }
        function Assert-Preflight { throw 'Full preflight must not run at logon.' }
        function Get-TailnetUrl { throw 'Tailscale must not be queried at logon.' }
        function Invoke-NativeResult { throw 'No native prerequisite commands are allowed in the Run guard.' }
        foreach ($directory in @((Get-ProductionRoot),(Get-ProductionHome),(Join-Path (Get-ProductionRoot) 'storage'),(Join-Path (Get-ProductionRoot) 'bootstrap\cache'))) { New-PrivateDirectory $directory }
        foreach ($directory in @('data','backups','logs','nginx')) { New-PrivateDirectory (Join-Path (Get-ProductionHome) $directory) }
        $statePath=Join-Path (Get-ProductionHome) 'setup-state.json'
        Write-Utf8File $statePath '{"complete":false,"app_url":"https://print-for-me.example.ts.net"}'
        $canonical=Get-ProductionEnv
        $checkoutEnv=Join-Path (Get-ProductionRoot) '.env'
        foreach ($file in @($canonical,$checkoutEnv)) { Write-Utf8File $file 'APP_URL=https://print-for-me.example.ts.net'; Protect-PrivatePath $file }
        if ((Get-ServiceProductionInfo).logs -ne (Join-Path (Get-ProductionHome) 'logs')) { throw 'Run light-guard rejected ready synthetic paths.' }
        $runGuard=$ast.Find({param($node) $node -is [Management.Automation.Language.IfStatementAst] -and $node.Extent.Text.StartsWith('if ($Operation -ne ''Run'')')},$true)
        if (-not $runGuard) { throw 'Run preflight routing guard is missing.' }
        $Operation='Run'
        & ([scriptblock]::Create($runGuard.Extent.Text))
        $Operation='Install'
        Assert-SelfCheckFailure { & ([scriptblock]::Create($runGuard.Extent.Text)) } 'Full preflight must not run'
        Write-Utf8File $canonical 'APP_URL=https://wrong.example.ts.net'
        Assert-SelfCheckFailure { Get-ServiceProductionInfo } 'APP_URL does not match'
        Write-Utf8File $canonical 'APP_URL=https://print-for-me.example.ts.net'
        Write-Utf8File $checkoutEnv 'APP_URL=https://wrong.example.ts.net'
        Assert-SelfCheckFailure { Get-ServiceProductionInfo } 'APP_URL does not match'
        Write-Utf8File $checkoutEnv 'APP_URL=https://print-for-me.example.ts.net'
        $acl=Get-Acl -LiteralPath $canonical
        $acl.SetAccessRuleProtection($false,$true)
        (Get-Item -LiteralPath $canonical).SetAccessControl($acl)
        Assert-SelfCheckFailure { Get-ServiceProductionInfo } 'protected user-only ACL'
        Protect-PrivatePath $canonical
        Remove-Item -LiteralPath $canonical -Force
        Assert-SelfCheckFailure { Get-ServiceProductionInfo }
        Write-Utf8File $canonical 'APP_URL=https://print-for-me.example.ts.net'; Protect-PrivatePath $canonical
        Write-Utf8File $statePath '{"complete":false,"app_url":""}'
        Assert-SelfCheckFailure { Get-ServiceProductionInfo } 'APP_URL does not match'
        $fixture.Repository=$scratch
        Assert-SelfCheckFailure { Get-ServiceProductionInfo } 'Run only from'
    }
    & {
        $Role='worker'
        $fixture=@{Attempts=0; Sleeps=0; Children=0}
        function Get-ServiceProductionInfo {
            $fixture.Attempts++
            if ($fixture.Attempts -eq 1) { throw 'synthetic app not ready' }
            return [pscustomobject]@{logs=$scratch}
        }
        function Get-ProductionInfo { throw 'Full preflight must not run in the retry loop.' }
        function Assert-ServicePort { }
        function Get-HerdPhp { if ($fixture.Attempts -eq 2) { throw 'synthetic PHP not ready' }; return $php }
        function Get-ProductionEnv { return $syntheticEnv }
        function New-ChildStartInfo { return (New-Object Diagnostics.ProcessStartInfo) }
        function Invoke-LoggedChild {
            $fixture.Children++
            if ($fixture.Attempts -eq 3) { throw 'synthetic child launch failure' }
            if ($fixture.Attempts -eq 4) { return 9 }
            return 0
        }
        function Start-Sleep {
            param($Seconds)
            if ($Seconds -ne 3) { throw 'Wrong service retry delay.' }
            $fixture.Sleeps++
            if ($fixture.Sleeps -eq 5) { throw 'synthetic-loop-finished' }
        }
        Assert-SelfCheckFailure { Invoke-ServiceLoop 3>$null } 'synthetic-loop-finished'
        $loopLog=[IO.File]::ReadAllText((Join-Path $scratch 'worker-loop.log'))
        if ($fixture.Attempts -ne 5 -or $fixture.Children -ne 3 -or $loopLog -notmatch 'exited 9' -or $loopLog -notmatch 'exited 0') { throw 'Service loop did not recover and restart after startup failures/exits.' }
    }
    & {
        $Role='worker'; $script:SelfCheckStopped=$false
        function Assert-ServicePort { }
        function Assert-ServiceTask { param($Task) }
        function Invoke-Artisan { param($Arguments) throw 'synthetic application boot failure' }
        function Stop-ScheduledTask { param($InputObject) $script:SelfCheckStopped=$true }
        function Get-CimInstance { param($ClassName, $Filter) return @() }
        function Get-OwnedProcesses { return @() }
        Stop-OwnedService ([pscustomobject]@{Name='synthetic'}) 3>$null
        if (-not $script:SelfCheckStopped) { throw 'Broken application prevented task cleanup.' }
    }
    foreach ($drainMode in @('complete','timeout')) {
        & {
            $Role='scheduler'
            $fixture=@{Waits=0; Signal=$false; TaskStopped=$false; Stopped=@(); Processes=@{}}
            $creation=[DateTime]::UtcNow
            $artisan='"' + (Join-Path (Get-ProductionRoot) 'artisan').ToUpperInvariant() + '"'
            $fixture.Processes[1001]=[pscustomobject]@{ProcessId=1001; ExecutablePath=$php.ToUpperInvariant(); CommandLine=$artisan + ' schedule:work'; CreationDate=$creation}
            $fixture.Processes[1002]=[pscustomobject]@{ProcessId=1002; ExecutablePath=$php.ToUpperInvariant(); CommandLine=$artisan + ' schedule:run'; CreationDate=$creation}
            $fixture.Processes[1003]=[pscustomobject]@{ProcessId=1003; ExecutablePath=$php; CommandLine='"C:\unrelated\artisan" schedule:run'; CreationDate=$creation}
            $supervisor=[pscustomobject]@{ProcessId=1004; ExecutablePath=(Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'); CommandLine='powershell.exe -File "' + $serviceFunctions.ToUpperInvariant() + '" Run -Role scheduler'; CreationDate=$creation}
            function Assert-ServicePort { }
            function Assert-ServiceTask { param($Task) }
            function Invoke-Artisan {
                param($Arguments)
                if ($Arguments[0] -ne 'schedule:interrupt') { throw 'Wrong scheduler shutdown signal.' }
                $fixture.Signal=$true
            }
            function Stop-ScheduledTask { param($InputObject) $fixture.TaskStopped=$true }
            function Get-CimInstance {
                param($ClassName, $Filter)
                if ($Filter -eq "Name='powershell.exe'") { return $supervisor }
                if ($Filter -eq "Name='php.exe'") { return @($fixture.Processes.Values) }
                if ($Filter -eq 'ProcessId=1004') { return $supervisor }
                if ($Filter -match '^ProcessId=(\d+)$') { return $fixture.Processes[[int]$Matches[1]] }
                throw "Unexpected synthetic process query: $Filter"
            }
            function Stop-Process {
                param($Id, [switch]$Force)
                if (-not $fixture.Signal -or -not $fixture.TaskStopped) { throw 'Stopped a process before signalling/stopping the supervisor task.' }
                if ($Id -eq 1001 -and $fixture.Waits -ne 0) { throw 'schedule:work was not stopped immediately.' }
                if ($Id -eq 1002 -and $fixture.Waits -lt 60) { throw 'In-flight schedule:run was killed before its drain timeout.' }
                if ($Id -eq 1003) { throw 'Unrelated scheduler process was stopped.' }
                $fixture.Stopped += $Id
                $fixture.Processes.Remove([int]$Id)
            }
            function Start-Sleep {
                param($Seconds)
                if ($Seconds -ne 1 -or $fixture.Processes.ContainsKey(1001)) { throw 'Scheduler parent was not stopped before draining children.' }
                $fixture.Waits++
                if ($drainMode -eq 'complete') { $fixture.Processes.Remove(1002) }
            }
            Stop-OwnedService ([pscustomobject]@{Name='synthetic'})
            $expectedWaits=if ($drainMode -eq 'complete') { 1 } else { 60 }
            if ($fixture.Waits -ne $expectedWaits -or 1001 -notin $fixture.Stopped -or 1004 -notin $fixture.Stopped -or -not $fixture.Processes.ContainsKey(1003) -or (($drainMode -eq 'timeout') -ne (1002 -in $fixture.Stopped))) { throw "Scheduler drain/ownership failed: $drainMode" }
        }
    }
    & {
        $deployAst=[System.Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'deploy.ps1'),[ref]$tokens,[ref]$parseErrors)
        $shaGuard=$deployAst.Find({param($node) $node -is [Management.Automation.Language.IfStatementAst] -and $node.Extent.Text -match '^if \(\$Yes\) \{ Assert-ExpectedDeploySha '},$true)
        if (-not $shaGuard) { throw 'ExpectedSha apply guard is missing.' }
        $incomingSha='a' * 40; $ExpectedSha='b' * 40; $Yes=$true
        Assert-SelfCheckFailure { & ([scriptblock]::Create($shaGuard.Extent.Text)) } 'origin/staging changed since preview'
        $ExpectedSha=$incomingSha.ToUpperInvariant()
        & ([scriptblock]::Create($shaGuard.Extent.Text))
        $ExpectedSha=''; & ([scriptblock]::Create($shaGuard.Extent.Text))
        $Yes=$false; $ExpectedSha='b' * 40
        & ([scriptblock]::Create($shaGuard.Extent.Text))
        $preview=$deployAst.Find({param($node) $node -is [Management.Automation.Language.CommandAst] -and $node.GetCommandName() -eq 'Write-Host' -and $node.Extent.Text -match 'Preview only; deploy this SHA'},$true)
        if (-not $preview) { throw 'Pinned preview command is missing.' }
        $previewPath=Join-Path $scratch 'synthetic''s deploy.ps1'
        Write-Utf8File $previewPath $preview.Extent.Text
        $previewText=(. $previewPath 6>&1 | Out-String)
        if ($previewText -notmatch ('-Yes -ExpectedSha ' + $incomingSha) -or $previewText -notmatch [regex]::Escape($previewPath.Replace("'", "''"))) { throw 'Preview did not print the exact SHA-pinned invocation.' }
    }
    & {
        $statusAst=[System.Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'status.ps1'),[ref]$tokens,[ref]$parseErrors)
        $serveGuard=$statusAst.Find({param($node) $node -is [Management.Automation.Language.TryStatementAst] -and $node.Body.Extent.Text -match '^\{\s*\$serve = '},$true)
        if (-not $serveGuard) { throw 'Serve status guard is missing.' }
        $fixture=@{ExitCode=0; Output='{}'}
        function Invoke-NativeResult { return [pscustomobject]$fixture }
        foreach ($missingServe in @('{}','{"Services":{}}','{"Services":{"svc:print-for-me":{"Proxy":"http://127.0.0.1:9999"}}}')) {
            $fixture.Output=$missingServe
            $AllowMissingServe=$true
            $warnings=@(& ([scriptblock]::Create($serveGuard.Extent.Text)) 3>&1)
            if ($warnings.Count -ne 1 -or $warnings[0].Message -notmatch 'Serve is not ready:') { throw 'Setup rerun did not warn about absent/mismatched Serve.' }
            $AllowMissingServe=$false
            Assert-SelfCheckFailure { & ([scriptblock]::Create($serveGuard.Extent.Text)) }
        }
        $fixture.ExitCode=7; $AllowMissingServe=$true
        $warnings=@(& ([scriptblock]::Create($serveGuard.Extent.Text)) 3>&1)
        if ($warnings.Count -ne 1) { throw 'Unreadable Serve status failed setup rerun.' }
        $AllowMissingServe=$false
        Assert-SelfCheckFailure { & ([scriptblock]::Create($serveGuard.Extent.Text)) } 'Cannot read Serve status'
        $fixture.ExitCode=0; $fixture.Output='{"Services":{"svc:print-for-me":{"Proxy":"http://127.0.0.1:8030"}}}'
        & ([scriptblock]::Create($serveGuard.Extent.Text))
    }
    $rendered=Get-RenderedHerdSite 'C:\synthetic path\public' 'print-for-me.example.ts.net'
    if ($rendered -notmatch 'listen 127\.0\.0\.1:8030;' -or $rendered -match '{{' -or $rendered -notmatch 'fastcgi_pass 127\.0\.0\.1:9085;' -or -not $rendered.Contains('root "C:/synthetic path/public";')) { throw 'Nginx template rendering failed.' }
    $db=Join-Path $scratch 'source.sqlite'
    $snapshot=Join-Path $scratch 'snapshot.sqlite'
    $helper=Join-Path $PSScriptRoot 'sqlite-backup.php'
    $create=Invoke-NativeResult $php @('-r','$db = new PDO("sqlite:".$argv[1]); $db->exec("CREATE TABLE sample (id INTEGER); INSERT INTO sample VALUES (1), (2)");',$db) $syntheticEnv
    if ($create.ExitCode -ne 0) { throw 'Synthetic SQLite creation failed.' }
    $wal=Invoke-NativeResult $php @($helper,'wal',$db) $syntheticEnv
    if ($wal.ExitCode -ne 0 -or $wal.Output.Trim() -ne 'wal') { throw "SQLite WAL helper failed: $($wal.Error) $($wal.Output)" }
    $copy=Invoke-NativeResult $php @($helper,'snapshot',$db,$snapshot) $syntheticEnv
    if ($copy.ExitCode -ne 0) { throw "SQLite snapshot helper failed: $($copy.Error)" }
    $summary=$copy.Output | ConvertFrom-Json
    if ($summary.integrity -ne 'ok' -or $summary.tables.sample -ne 2 -or $summary.page_count -le 0) { throw 'SQLite summary failed.' }
    if ((Invoke-NativeResult $php @($helper,'snapshot',$db,$snapshot) $syntheticEnv).ExitCode -eq 0) { throw 'SQLite overwrote an existing backup.' }
    $check=Invoke-NativeResult $php @($helper,'check',$snapshot) $syntheticEnv
    if ($check.ExitCode -ne 0 -or $check.Output.Trim() -ne 'ok') { throw 'SQLite backup integrity failed.' }
    & {
        function Get-ProductionRoot { return $scratch }
        function Get-ProductionHome { return (Join-Path $scratch 'home') }
        function Get-ProductionEnv { return $syntheticEnv }
        function Get-ProductionDatabase { return $db }
        function Get-HerdPhp { return $php }
        function Invoke-Git { param([string[]]$Arguments) return ('a' * 40) }
        function Assert-ProductionLocation { }
        function Invoke-Checked { param($FilePath, $ArgumentList, $EnvironmentFile) throw 'synthetic-execution-boundary' }
        foreach ($command in @('test','migrate:fresh','migrate:refresh','migrate:reset','migrate:rollback','db:wipe','db:seed','key:generate','migrate:fre','db:se','key:gen')) {
            try { Invoke-Artisan @($command); throw 'Destructive command was accepted.' }
            catch { if ($_.Exception.Message -ne 'Refusing destructive production Artisan command.') { throw } }
        }
        Write-Utf8File (Join-Path $scratch 'artisan') '<?php echo json_encode([ARTISAN_BINARY, $_SERVER["argv"]]);'
        $schedulerArguments = Get-ServiceArguments 'scheduler'
        $scheduler = Invoke-NativeResult $php $schedulerArguments $syntheticEnv
        $arguments = $scheduler.Output | ConvertFrom-Json
        if ($scheduler.ExitCode -ne 0 -or $arguments[0] -ne (Join-Path $scratch 'artisan') -or $arguments[1][1] -ne 'schedule:work') { throw 'Absolute scheduler bootstrap failed.' }
        foreach ($relative in @('home\backups','storage\app\private','storage\app\public')) { New-PrivateDirectory (Join-Path $scratch $relative) }
        Write-Utf8File (Join-Path $scratch 'storage\app\private\sample.stl') 'synthetic-upload'
        Write-Utf8File (Join-Path $scratch 'storage\app\public\photo.jpg') 'synthetic-photo'
        function Invoke-Checked {
            param($FilePath, $ArgumentList, $EnvironmentFile)
            $result=Invoke-NativeResult $FilePath $ArgumentList $EnvironmentFile
            if ($result.ExitCode -ne 0) { throw 'Synthetic backup verification failed.' }
        }
        $folder=Invoke-ProductionBackup
        $manifest=[IO.File]::ReadAllText((Join-Path $folder 'manifest.json')) | ConvertFrom-Json
        if ($manifest.db.tables.sample -ne 2 -or @($manifest.files.PSObject.Properties).Count -ne 4 -or
            [IO.File]::ReadAllText((Join-Path $folder 'storage-app\private\sample.stl')) -ne 'synthetic-upload') { throw 'Full backup manifest/upload copy failed.' }
    }
    Write-Host "OK: $count PowerShell scripts parsed; ACL, env isolation, quoting/cmd, native capture, logs, three task XML contracts, Run light-guard/retries, ExpectedSha refusal/pinned preview, case-insensitive ownership, scheduler drain/completion/timeout, transcript capture, Serve warning/strict status, nginx template, SQLite, guarded Artisan, scheduler bootstrap and full backup checks passed. No tasks registered."
} catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
finally {
    if ($scratch -and (Test-Path -LiteralPath $scratch)) {
        $resolved=[IO.Path]::GetFullPath($scratch)
        $tempPrefix=[IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\') + '\'
        if (-not $resolved.StartsWith($tempPrefix,[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetFileName($resolved) -notmatch '^print-for-me-ps-check-[a-f0-9]{32}$') { throw 'Unsafe self-check cleanup path.' }
        Remove-Item -LiteralPath $resolved -Recurse -Force
    }
}
