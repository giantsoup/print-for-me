<#
.SYNOPSIS
Shared Windows production helpers. Dot-source from the adjacent scripts.
.DESCRIPTION
Never edit or clean the production checkout. Herd must remain running. All child
processes strip ambient development configuration. Private paths reject reparse points.
#>
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$script:WindowsScriptsRoot = $PSScriptRoot

function Get-RepositoryRoot { return [IO.Path]::GetFullPath((Join-Path $script:WindowsScriptsRoot '../../..')) }
function Get-ProductionRoot { return (Join-Path $env:USERPROFILE 'Production\print-for-me') }
function Get-ProductionHome { return (Join-Path $env:LOCALAPPDATA 'PrintForMe\production') }
function Get-ProductionEnv { return (Join-Path (Get-ProductionHome) 'production.env') }
function Get-ProductionDatabase { return (Join-Path (Get-ProductionHome) 'data\database.sqlite') }
function Get-HerdPhp {
    $php = Join-Path $env:USERPROFILE '.config\herd\bin\php85\php.exe'
    $result = Invoke-NativeResult $php @('-r', 'echo PHP_VERSION;')
    if ($result.ExitCode -ne 0 -or $result.Output -notmatch '^8\.5\.') { throw 'Herd PHP 8.5 is required.' }
    return $php
}
function Get-Composer { return (Join-Path $env:USERPROFILE '.config\herd\bin\composer.phar') }
function Get-HerdNginxExe { return (Join-Path $env:ProgramFiles 'Herd\resources\app.asar.unpacked\resources\bin\nginx\nginx.exe') }
function Get-HerdSiteConfPath { return (Join-Path $env:USERPROFILE '.config\herd\config\valet\Nginx\print-for-me-production.conf') }
$script:ExpectedOrigin = 'https://github.com/giantsoup/print-for-me.git'
$script:ProductionBranch = 'staging'
$script:Port = 8030
function Get-TaskName { param([string]$Role) return "PrintForMe-Production-$Role" }

function ConvertTo-NativeArgument {
    param([AllowEmptyString()][string]$Value)
    return '"' + [regex]::Replace([regex]::Replace($Value, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"'
}

function New-ChildStartInfo {
    param([string]$FilePath, [string[]]$ArgumentList = @(), [string]$EnvironmentFile = (Get-ProductionEnv), [hashtable]$ExtraEnvironment = @{}, [string]$WorkingDirectory = (Get-RepositoryRoot))
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $FilePath
    $arguments = ($ArgumentList | ForEach-Object { ConvertTo-NativeArgument $_ }) -join ' '
    if ([IO.Path]::GetExtension($FilePath) -in @('.cmd', '.bat')) {
        $start.FileName = $env:ComSpec
        $start.Arguments = '/d /s /c "' + (ConvertTo-NativeArgument $FilePath) + ' ' + $arguments + '"'
    } else { $start.Arguments = $arguments }
    $start.WorkingDirectory = $WorkingDirectory
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $envKeys = @()
    if ($EnvironmentFile -and (Test-Path -LiteralPath $EnvironmentFile)) { $envKeys = @((Read-EnvFile $EnvironmentFile).Keys) }
    foreach ($key in @($start.EnvironmentVariables.Keys)) {
        if ($key -in $envKeys -or $key -match '^(APP_|DB_|SESSION_|MAIL_|QUEUE_|CACHE_|LOG_|REDIS_|FILESYSTEM_|AWS_|VITE_|INERTIA_|TRUSTED_|BROADCAST_|ADMIN_|MEMCACHED_|PHP_CLI_|XDEBUG_)' -or
            $key -in @('LARAVEL_STORAGE_PATH', 'PHP_INI_SCAN_DIR', 'NODE_ENV', 'DB_URL')) {
            $start.EnvironmentVariables.Remove($key)
        }
    }
    foreach ($key in $ExtraEnvironment.Keys) { $start.EnvironmentVariables[$key] = [string]$ExtraEnvironment[$key] }
    return $start
}

function Invoke-NativeResult {
    param([string]$FilePath, [string[]]$ArgumentList = @(), [string]$EnvironmentFile = (Get-ProductionEnv), [hashtable]$ExtraEnvironment = @{}, [string]$InputText, [string]$WorkingDirectory = (Get-RepositoryRoot))
    $start = New-ChildStartInfo $FilePath $ArgumentList $EnvironmentFile $ExtraEnvironment $WorkingDirectory
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.RedirectStandardInput = $true
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    try {
        [void]$process.Start()
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        if ($InputText) { $process.StandardInput.Write($InputText) }
        $process.StandardInput.Close()
        $process.WaitForExit()
        return [pscustomobject]@{ ExitCode=$process.ExitCode; Output=$stdout.Result; Error=$stderr.Result }
    } finally { $process.Dispose() }
}

function Invoke-Checked {
    param([string]$FilePath, [string[]]$ArgumentList = @(), [string]$EnvironmentFile = (Get-ProductionEnv), [hashtable]$ExtraEnvironment = @{})
    $result = Invoke-NativeResult $FilePath $ArgumentList $EnvironmentFile $ExtraEnvironment
    if ($result.Output) { Write-Host $result.Output -NoNewline }
    if ($result.Error) { Write-Host $result.Error -NoNewline }
    if ($result.ExitCode -ne 0) { throw "$([IO.Path]::GetFileName($FilePath)) failed (exit $($result.ExitCode))." }
}

function Assert-PlainPath {
    param([string]$Path)
    if (-not [IO.Path]::IsPathRooted($Path)) { throw 'An absolute path is required.' }
    $current = [IO.Path]::GetFullPath($Path)
    while ($current) {
        if ((Test-Path -LiteralPath $current) -and ((Get-Item -LiteralPath $current -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw "Refusing reparse point: $current" }
        $current = [IO.Path]::GetDirectoryName($current)
    }
}

function Protect-PrivatePath {
    param([string]$Path)
    Assert-PlainPath $Path
    $item = Get-Item -LiteralPath $Path -Force
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
    if ($item.PSIsContainer) {
        $acl = New-Object Security.AccessControl.DirectorySecurity
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    } else {
        $acl = New-Object Security.AccessControl.FileSecurity
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'Allow')
    }
    $acl.SetOwner($sid)
    $acl.SetAccessRuleProtection($true, $false)
    $acl.AddAccessRule($rule)
    $item.SetAccessControl($acl)
    Assert-PrivatePath $Path
}

function Assert-PrivatePath {
    param([string]$Path)
    Assert-PlainPath $Path
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    $acl = Get-Acl -LiteralPath $Path
    $rules = @($acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
    if (-not $acl.AreAccessRulesProtected -or $rules.Count -ne 1 -or $rules[0].IdentityReference.Value -ne $sid -or $rules[0].AccessControlType -ne 'Allow' -or $acl.Owner -ne [Security.Principal.WindowsIdentity]::GetCurrent().Name -or $rules[0].FileSystemRights -ne 'FullControl') { throw "Expected a protected user-only ACL: $Path" }
}

function New-PrivateDirectory {
    param([string]$Path)
    Assert-PlainPath $Path
    [void][IO.Directory]::CreateDirectory($Path)
    Protect-PrivatePath $Path
}

function Invoke-Services {
    param([string]$Operation, [string]$Role, [switch]$Yes)
    $arguments = @('-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $script:WindowsScriptsRoot 'services.ps1'), '-Operation', $Operation, '-Role', $Role)
    if ($Yes) { $arguments += '-Yes' }
    Invoke-Checked 'powershell.exe' $arguments
}

function Get-TailnetUrl {
    $tailscale = Join-Path $env:ProgramFiles 'Tailscale\tailscale.exe'
    $result = Invoke-NativeResult $tailscale @('status', '--json')
    if ($result.ExitCode -ne 0) { throw 'tailscale status failed.' }
    $suffix = [string](($result.Output | ConvertFrom-Json).MagicDNSSuffix)
    $suffix = $suffix.TrimEnd('.')
    if ($suffix -notmatch '^[a-z0-9-]+\.ts\.net$') { throw 'Missing or invalid MagicDNSSuffix.' }
    return "https://print-for-me.$suffix"
}

function Get-HttpResult {
    param([string]$Url, [string]$Method = 'GET', [hashtable]$Headers = @{}, [string]$HostHeader)
    $request = [Net.HttpWebRequest]::Create($Url)
    if ($HostHeader) { $request.Host = $HostHeader }
    $request.Method = $Method
    $request.AllowAutoRedirect = $false
    $request.Timeout = 15000
    foreach ($key in $Headers.Keys) { $request.Headers[$key] = [string]$Headers[$key] }
    if ($Method -eq 'POST') { $request.ContentLength = 0 }
    try { $response = $request.GetResponse() }
    catch [Net.WebException] {
        if (-not $_.Exception.Response) { throw }
        $response = $_.Exception.Response
    }
    try {
        $reader = New-Object IO.StreamReader($response.GetResponseStream())
        try { $body = $reader.ReadToEnd() } finally { $reader.Dispose() }
        return [pscustomobject]@{ Status=[int]$response.StatusCode; Body=$body; Headers=$response.Headers; Cookies=@($response.Headers.GetValues('Set-Cookie')) }
    } finally { $response.Close() }
}

function Write-Utf8File {
    param([string]$Path, [string]$Text)
    Assert-PlainPath $Path
    [IO.File]::WriteAllText($Path, $Text, (New-Object Text.UTF8Encoding $false))
}

function Read-EnvFile {
    param([string]$Path)
    $values = @{}
    foreach ($line in [IO.File]::ReadAllLines($Path)) {
        if ($line -match '^\s*(#|$)') { continue }
        if ($line -notmatch '^([A-Z][A-Z0-9_]*)=(.*)$') { throw 'Invalid env syntax; expected KEY=VALUE.' }
        $key = $Matches[1]; $value = $Matches[2].Trim()
        if ($values.ContainsKey($key)) { throw "Duplicate env key: $key" }
        if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) { $value = $value.Substring(1, $value.Length - 2) }
        $values[$key] = $value
    }
    return $values
}

function Assert-ProductionLocation {
    if ((Get-RepositoryRoot) -ne (Get-ProductionRoot)) { throw 'Run only from USERPROFILE\Production\print-for-me.' }
    Assert-PlainPath (Get-ProductionRoot)
    Assert-PlainPath (Get-ProductionHome)
}

function Sync-CheckoutEnv {
    $source = Get-ProductionEnv
    $destination = Join-Path (Get-ProductionRoot) '.env'
    Assert-PlainPath $source
    Assert-PlainPath $destination
    if (-not (Test-Path -LiteralPath $destination) -or (Get-FileHash -LiteralPath $source).Hash -ne (Get-FileHash -LiteralPath $destination).Hash) {
        [IO.File]::Copy($source, $destination, $true)
    }
    Protect-PrivatePath $destination
}

function Invoke-Artisan {
    param([string[]]$Arguments, [switch]$AllowDestructive)
    Assert-ProductionLocation
    $denied = @('test','migrate:fresh','migrate:refresh','migrate:reset','migrate:rollback','db:wipe','db:seed','key:generate')
    $command = @($Arguments | Where-Object { -not $_.StartsWith('-') } | Select-Object -First 1)
    if (-not $command.Count) { throw 'Refusing destructive production Artisan command.' }
    $parts = $command[0].Split(':')
    $destructive = $false
    foreach ($name in $denied) {
        $fullParts = $name.Split(':')
        if ($parts.Count -ne $fullParts.Count) { continue }
        $matchesCommand = $true
        for ($index=0; $index -lt $parts.Count; $index++) {
            if (-not $parts[$index] -or -not $fullParts[$index].StartsWith($parts[$index],[StringComparison]::OrdinalIgnoreCase)) { $matchesCommand=$false; break }
        }
        if ($matchesCommand) { $destructive=$true; break }
    }
    if ($destructive -and -not $AllowDestructive) { throw 'Refusing destructive production Artisan command.' }
    if (@($Arguments | Where-Object { $_ -match '^--(env|database)(=|$)' }).Count) { throw 'Production configuration overrides are forbidden.' }
    Invoke-Checked (Get-HerdPhp) (@((Join-Path (Get-ProductionRoot) 'artisan')) + $Arguments + '--no-interaction') (Get-ProductionEnv)
}

function Invoke-Composer {
    param([string[]]$Arguments)
    Invoke-Checked (Get-HerdPhp) (@((Get-Composer)) + $Arguments) (Get-ProductionEnv)
}

function Invoke-Npm {
    param([string[]]$Arguments)
    Invoke-Checked (Get-Command npm.cmd -ErrorAction Stop).Source $Arguments (Get-ProductionEnv)
}

function Invoke-Git {
    param([string[]]$Arguments)
    $result = Invoke-NativeResult 'git.exe' $Arguments
    if ($result.ExitCode -ne 0) { throw "git $($Arguments[0]) failed: $($result.Error)" }
    return $result.Output.Trim()
}

function Assert-ProductionGit {
    if ((Invoke-Git @('branch','--show-current')) -ne $script:ProductionBranch) { throw 'Local production must be on staging.' }
    if ((Invoke-Git @('remote','get-url','origin')) -ne $script:ExpectedOrigin) { throw 'Unexpected production origin.' }
    if ((Invoke-Git @('rev-parse','--abbrev-ref','@{upstream}')) -ne 'origin/staging') { throw 'Expected upstream origin/staging.' }
    if (Invoke-Git @('status','--porcelain','--untracked-files=all')) { throw 'Production checkout is dirty; never edit or clean production.' }
}

function Assert-ProductionEnv {
    $file = Get-ProductionEnv
    Assert-PrivatePath $file
    $values = Read-EnvFile $file
    $template = Read-EnvFile (Join-Path $script:WindowsScriptsRoot 'production.env.example')
    $missing = @($template.Keys | Where-Object { -not $values.ContainsKey($_) })
    if ($missing.Count) { throw "Missing env keys: $($missing -join ', ')" }
    $required = @{
        APP_ENV='production'; APP_DEBUG='false'; MAIL_MAILER='log'; APP_URL=(Get-TailnetUrl)
        TRUSTED_PROXIES='127.0.0.1'; SESSION_SECURE_COOKIE='true'; DB_CONNECTION='sqlite'
        DB_DATABASE=((Get-ProductionDatabase).Replace('\','/')); LOG_LEVEL='debug'
        SESSION_COOKIE='print_for_me_local_production_session'; SESSION_HTTP_ONLY='true'; SESSION_SAME_SITE='lax'
        SESSION_DOMAIN='null'; QUEUE_CONNECTION='database'; CACHE_STORE='database'; SESSION_DRIVER='database'
    }
    foreach ($key in $required.Keys) { if ($values[$key] -cne $required[$key]) { throw "Invalid production env key: $key" } }
    if ($values.APP_KEY -notmatch '^base64:[A-Za-z0-9+/]{43}=$') { throw 'Invalid APP_KEY format.' }
    if ($values.ADMIN_EMAIL -notmatch '^[^@\s]+@[^@\s]+\.[^@\s]+$') { throw 'Invalid ADMIN_EMAIL.' }
    return $values
}

function Assert-HerdInclude {
    $config = Join-Path $env:USERPROFILE '.config\herd\config\nginx\nginx.conf'
    $include = (Join-Path $env:USERPROFILE '.config/herd/config/valet/Nginx/*.conf').Replace('\','/')
    if ([IO.File]::ReadAllText($config) -notmatch ('include\s+"?' + [regex]::Escape($include) + '"?\s*;')) { throw 'Herd nginx.conf no longer includes valet/Nginx/*.conf.' }
}

function Assert-HerdListener {
    param([int]$Port)
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
    if (-not $listeners.Count) { throw "No Herd nginx listener on $Port." }
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
        if ($process.ExecutablePath -ne (Get-HerdNginxExe) -or ($Port -eq 8030 -and $listener.LocalAddress -ne '127.0.0.1')) { throw "Unexpected listener on $Port." }
    }
}

function Assert-Preflight {
    param([switch]$SkipWebInfrastructure)
    foreach ($command in @('git.exe','node.exe','npm.cmd')) { [void](Get-Command $command -ErrorAction Stop) }
    foreach ($path in @((Get-ProductionRoot),(Get-ProductionHome),(Get-HerdSiteConfPath),(Get-ProductionDatabase))) { Assert-PlainPath $path }
    foreach ($path in @((Get-HerdPhp),(Get-Composer),(Join-Path $env:ProgramFiles 'Tailscale\tailscale.exe'))) { if (-not (Test-Path -LiteralPath $path)) { throw "Missing prerequisite: $path" } }
    Invoke-Checked (Get-HerdPhp) @('-r', 'exit(extension_loaded("pdo_sqlite")?0:1);')
    $node = Invoke-NativeResult 'node.exe' @('--version')
    if ($node.ExitCode -ne 0 -or $node.Output.Trim() -ne ('v' + [IO.File]::ReadAllText((Join-Path (Get-RepositoryRoot) '.node-version')).Trim())) { throw 'Node version must match .node-version.' }
    $package = [IO.File]::ReadAllText((Join-Path (Get-RepositoryRoot) 'package.json')) | ConvertFrom-Json
    $npm = Invoke-NativeResult (Get-Command npm.cmd).Source @('--version')
    if ($npm.ExitCode -ne 0) { throw 'npm version check failed.' }
    $installed = [version]$npm.Output.Trim(); $expected = [version]$package.engines.npm.TrimStart('^')
    if ($installed.Major -ne $expected.Major -or $installed -lt $expected) { Write-Warning 'npm differs from package.json engines; npm ci may warn. Upgrade separately if desired.' }
    if (-not $SkipWebInfrastructure) {
        if (-not (Test-Path -LiteralPath (Get-HerdNginxExe))) { throw 'Herd nginx is required.' }
        Assert-HerdInclude
        Assert-HerdListener 80
        $cgi = @(Get-NetTCPConnection -State Listen -LocalPort 9085 -ErrorAction SilentlyContinue)
        if (-not $cgi.Count) { throw 'Herd PHP 8.5 pool is not listening on 9085.' }
        foreach ($listener in $cgi) {
            $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
            if ($listener.LocalAddress -ne '127.0.0.1' -or $process.ExecutablePath -ne (Join-Path $env:USERPROFILE '.config\herd\bin\php85\php-cgi.exe')) { throw 'Unidentified FastCGI listener.' }
        }
        if (@(Get-NetTCPConnection -State Listen -LocalPort 8030 -ErrorAction SilentlyContinue).Count) {
            if (-not (Test-Path -LiteralPath (Get-HerdSiteConfPath))) { throw 'Port 8030 is occupied without our Herd config.' }
            Assert-HerdListener 8030
        }
    }
    if ((Get-TailnetUrl) -ne 'https://print-for-me.chimp-hamal.ts.net') { throw 'Unexpected tailnet suffix.' }
}

function Get-ServiceProductionInfo {
    Assert-ProductionLocation
    foreach ($private in @((Get-ProductionHome),(Get-ProductionEnv),(Join-Path (Get-ProductionRoot) '.env'),(Join-Path (Get-ProductionRoot) 'storage'),(Join-Path (Get-ProductionRoot) 'bootstrap\cache'))) { Assert-PrivatePath $private }
    foreach ($directory in @('data','backups','logs','nginx')) { Assert-PrivatePath (Join-Path (Get-ProductionHome) $directory) }
    $statePath = Join-Path (Get-ProductionHome) 'setup-state.json'
    Assert-PlainPath $statePath
    $state = [IO.File]::ReadAllText($statePath) | ConvertFrom-Json
    $values = Read-EnvFile (Get-ProductionEnv)
    $checkoutValues = Read-EnvFile (Join-Path (Get-ProductionRoot) '.env')
    if (-not $state.app_url -or $values.APP_URL -cne $state.app_url -or $checkoutValues.APP_URL -cne $state.app_url) { throw 'Production APP_URL does not match setup-state.json.' }
    return [pscustomobject]@{ logs=(Join-Path (Get-ProductionHome) 'logs') }
}

function Assert-ExpectedDeploySha {
    param([string]$ExpectedSha, [string]$IncomingSha)
    if ($ExpectedSha -and $ExpectedSha -ne $IncomingSha) { throw "origin/staging changed since preview (expected $ExpectedSha; found $IncomingSha). Run a new preview before deploying." }
}

function Get-ProductionInfo {
    param([switch]$AllowIncomplete, [switch]$SkipWebInfrastructure)
    Assert-ProductionLocation
    foreach ($private in @((Get-ProductionHome),(Get-ProductionEnv),(Join-Path (Get-ProductionRoot) '.env'),(Join-Path (Get-ProductionRoot) 'storage'),(Join-Path (Get-ProductionRoot) 'bootstrap\cache'))) { Assert-PrivatePath $private }
    foreach ($directory in @('data','backups','logs','nginx')) { Assert-PrivatePath (Join-Path (Get-ProductionHome) $directory) }
    $values = Assert-ProductionEnv
    if (-not $AllowIncomplete) {
        $state = [IO.File]::ReadAllText((Join-Path (Get-ProductionHome) 'setup-state.json')) | ConvertFrom-Json
        if (-not $state.complete -or $state.checkout -ne (Get-ProductionRoot) -or $state.db -ne (Get-ProductionDatabase) -or $state.origin -ne $script:ExpectedOrigin) { throw 'Setup is incomplete or its marker is invalid.' }
    }
    Assert-Preflight -SkipWebInfrastructure:$SkipWebInfrastructure
    return [pscustomobject]@{ root=(Get-ProductionRoot); home=(Get-ProductionHome); logs=(Join-Path (Get-ProductionHome) 'logs'); db=(Get-ProductionDatabase); app_url=$values.APP_URL }
}

function Get-RenderedHerdSite {
    param([string]$PublicRoot = (Join-Path (Get-ProductionRoot) 'public'), [string]$TailnetHost = ([uri](Get-TailnetUrl)).Host)
    $text = [IO.File]::ReadAllText((Join-Path $script:WindowsScriptsRoot 'templates\print-for-me-production.nginx.conf'))
    return $text.Replace('{{PUBLIC_ROOT}}',$PublicRoot.Replace('\','/')).Replace('{{TAILNET_HOST}}',$TailnetHost).Replace('{{TAILNET_HOST_REGEX}}',[regex]::Escape($TailnetHost)).Replace('{{HERD_LOG}}',((Join-Path $env:USERPROFILE '.config/herd/Log').Replace('\','/')))
}

function Test-HerdSite {
    Assert-HerdInclude
    $path = Get-HerdSiteConfPath
    if (-not (Test-Path -LiteralPath $path) -or [IO.File]::ReadAllText($path) -cne (Get-RenderedHerdSite)) { throw 'Herd site config differs from the managed template.' }
    Assert-HerdListener 8030
}

function Install-HerdSite {
    Assert-HerdInclude
    $path = Get-HerdSiteConfPath
    Assert-PlainPath $path
    $rendered = Get-RenderedHerdSite
    $exists = Test-Path -LiteralPath $path
    if ($exists -and [IO.File]::ReadAllText($path) -ceq $rendered) { Assert-HerdListener 8030; return }
    $arguments = @('-t','-p',((Join-Path $env:USERPROFILE '.config/herd/config/nginx/').Replace('\','/')),'-c','nginx.conf')
    $baseline = Invoke-NativeResult (Get-HerdNginxExe) $arguments
    $previous = $null
    if ($exists) {
        $previous = Join-Path (Get-ProductionHome) ('nginx\previous-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffffffZ') + '.conf')
        [IO.File]::Copy($path,$previous,$false)
    }
    $changed = $false
    try {
        $changed = $true
        Write-Utf8File $path $rendered
        if ($baseline.ExitCode -eq 0) { Invoke-Checked (Get-HerdNginxExe) $arguments }
        else { Write-Warning 'Unchanged Herd configuration failed nginx -t with this prefix; using restart health and automatic config restore.' }
        Invoke-Checked (Join-Path $env:USERPROFILE '.config\herd\bin\herd.bat') @('restart','nginx')
        $ready = $false
        foreach ($attempt in 1..30) {
            try {
                Assert-HerdListener 80
                Assert-HerdListener 8030
                if ((Get-HttpResult 'http://127.0.0.1:8030/up').Status -eq 200) { $ready=$true; break }
            } catch { }
            Start-Sleep -Milliseconds 500
        }
        if (-not $ready) { throw 'Herd did not recover on ports 80 and 8030.' }
    } catch {
        $failure = $_
        if ($changed) {
            if ($exists) { [IO.File]::Copy($previous,$path,$true) }
            else { Remove-Item -LiteralPath $path -Force }
            Invoke-Checked (Join-Path $env:USERPROFILE '.config\herd\bin\herd.bat') @('restart','nginx')
            $recovered = $false
            foreach ($attempt in 1..30) {
                try { Assert-HerdListener 80; $recovered=$true; break } catch { Start-Sleep -Milliseconds 500 }
            }
            if (-not $recovered) { throw 'Previous config restored, but Herd port 80 is still down; recover Herd immediately.' }
        }
        throw $failure
    }
}

function Assert-ViteBuild {
    $root = Get-ProductionRoot
    $build = Join-Path $root 'public\build'
    $manifest = [IO.File]::ReadAllText((Join-Path $build 'manifest.json')) | ConvertFrom-Json
    foreach ($entry in $manifest.PSObject.Properties.Value) {
        foreach ($property in @('file','css','assets')) {
            if ($entry.PSObject.Properties.Name -contains $property) {
                foreach ($relative in @($entry.$property)) {
                    $path = [IO.Path]::GetFullPath((Join-Path $build $relative))
                    if (-not $path.StartsWith(($build + '\'),[StringComparison]::OrdinalIgnoreCase) -or -not (Test-Path -LiteralPath $path -PathType Leaf)) { throw 'Missing or unsafe Vite manifest asset.' }
                }
            }
        }
    }
    if (Test-Path -LiteralPath (Join-Path $root 'bootstrap\ssr')) { throw 'SSR bundles must be absent in local production.' }
}

function Sync-UserIni {
    Write-Utf8File (Join-Path (Get-ProductionRoot) 'public\.user.ini') ([IO.File]::ReadAllText((Join-Path $script:WindowsScriptsRoot 'templates\user.ini')))
}

function Assert-StorageLink {
    $target = Join-Path (Get-ProductionRoot) 'storage\app\public'
    $link = Join-Path (Get-ProductionRoot) 'public\storage'
    if (-not (Test-Path -LiteralPath $link)) { Invoke-Artisan @('storage:link') }
    $item = Get-Item -LiteralPath $link -Force
    if ($item.LinkType -ne 'Junction' -or @($item.Target).Count -ne 1 -or [IO.Path]::GetFullPath($item.Target[0]) -ne $target) { throw 'public/storage must be the expected junction.' }
}

function Test-PageAssets {
    param($Response, [string]$BaseUrl)
    if ($Response.Status -ne 200) { throw "Login probe returned HTTP $($Response.Status)." }
    $assets = @([regex]::Matches($Response.Body, '(?:href|src)="([^"]*/build/assets/[^"]+)"') | ForEach-Object { ([uri]::new(([uri]$BaseUrl), $_.Groups[1].Value)).AbsolutePath } | Select-Object -Unique)
    if (-not $assets.Count) { throw 'No built assets on login page.' }
    foreach ($asset in $assets) {
        $result = Get-HttpResult ($BaseUrl + $asset)
        if ($result.Status -ne 200) { throw "Asset failed: $asset" }
        $mime = [string]$result.Headers['Content-Type']
        if (($asset -match '\.js$' -and $mime -notmatch '^application/javascript') -or ($asset -match '\.css$' -and $mime -notmatch '^text/css')) { throw "Incorrect asset MIME type: $asset" }
    }
}

function Test-ProductionHttp {
    param([switch]$SkipTasks)
    $base = 'http://127.0.0.1:8030'
    if ((Get-HttpResult ($base + '/up')).Status -ne 200) { throw '/up is unhealthy.' }
    Test-PageAssets (Get-HttpResult ($base + '/login')) $base
    $url = Get-TailnetUrl
    $headers = @{'X-Forwarded-Proto'='https'; 'X-Forwarded-Host'=([uri]$url).Host; 'X-Forwarded-For'='100.100.100.100'}
    $response = Get-HttpResult ($base + '/login') 'GET' $headers ([uri]$url).Host
    if ($response.Status -ne 200 -or -not $response.Body.Contains($url + '/build/assets/') -or $response.Body.Contains('http://' + ([uri]$url).Host)) { throw 'HTTPS proxy simulation failed.' }
    $cookie = @($response.Cookies | Where-Object { $_ -match '^print_for_me_local_production_session=' })
    if ($cookie.Count -ne 1 -or $cookie[0] -notmatch '(?i);\s*secure' -or $cookie[0] -notmatch '(?i);\s*httponly' -or $cookie[0] -notmatch '(?i);\s*samesite=lax') { throw 'Session cookie security flags missing.' }
    $dashboard = Get-HttpResult ($base + '/dashboard') 'GET' $headers ([uri]$url).Host
    if ($dashboard.Status -ne 302 -or $dashboard.Headers['Location'] -ne ($url + '/login')) { throw 'Dashboard HTTPS redirect failed.' }
    foreach ($secret in @('/.env','/.user.ini')) {
        $result = Get-HttpResult ($base + $secret)
        if ($result.Status -notin @(403,404) -or $result.Body.Contains('APP_KEY')) { throw 'Private file protection failed.' }
    }
    if (-not $SkipTasks) { foreach ($role in @('worker','scheduler')) { Invoke-Services Status $role } }
    Write-Host 'OK: loopback health, assets, proxy headers, cookies and private files.'
}

function Test-TailnetHttp {
    param([string]$BaseUrl = (Get-TailnetUrl))
    try {
        if ((Get-HttpResult ($BaseUrl + '/up')).Status -ne 200) { throw 'Tailnet /up failed.' }
        Test-PageAssets (Get-HttpResult ($BaseUrl + '/login')) $BaseUrl
        $spoof = Get-HttpResult ($BaseUrl + '/login') 'GET' @{'X-Forwarded-Host'='evil.invalid'; 'X-Forwarded-Proto'='http'}
        if ($spoof.Status -ne 200 -or -not $spoof.Body.Contains($BaseUrl + '/build/assets/') -or $spoof.Body.Contains('evil.invalid/build/assets/')) { throw 'Serve did not overwrite forwarding headers.' }
    } catch [Net.WebException] {
        if ($_.Exception.Status -in @([Net.WebExceptionStatus]::ConnectFailure,[Net.WebExceptionStatus]::Timeout)) { Write-Warning 'Service VIP hairpin unavailable; verify from another tailnet device.' }
        else { throw }
    }
}

function Assert-ApplicationEnvironment {
    $result = Invoke-NativeResult (Get-HerdPhp) @((Join-Path (Get-ProductionRoot) 'artisan'),'about','--only=environment','--json','--no-interaction') (Get-ProductionEnv)
    if ($result.ExitCode -ne 0) { throw 'Artisan about failed.' }
    try {
        $about = $result.Output | ConvertFrom-Json
        $environment = $about.environment
        if ($environment.environment -ne 'production' -or $environment.debug_mode -notin @('OFF', $false)) { throw 'Unexpected application environment/debug mode.' }
    } catch {
        foreach ($check in @(@('app.env','production'),@('app.debug','false'))) {
            $value = Invoke-NativeResult (Get-HerdPhp) @((Join-Path (Get-ProductionRoot) 'artisan'),'config:show',$check[0],'--no-ansi','--no-interaction') (Get-ProductionEnv)
            if ($value.ExitCode -ne 0 -or $value.Output -notmatch ('(?im)\b' + [regex]::Escape($check[1]) + '\b')) { throw 'Could not validate production environment/debug mode.' }
        }
    }
}

function Enter-DeployMutex {
    $mutex = New-Object Threading.Mutex($false, 'Local\PrintForMe-Production-Deploy')
    try { $owned = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $owned=$true }
    if (-not $owned) { $mutex.Dispose(); throw 'Another production setup/deploy/backup is running.' }
    return $mutex
}

function Invoke-ProductionBackup {
    $sha = Invoke-Git @('rev-parse','HEAD')
    $folder = Join-Path (Get-ProductionHome) ('backups\' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffffffZ') + '-' + $sha.Substring(0,8))
    New-PrivateDirectory $folder
    $helper = Join-Path $script:WindowsScriptsRoot 'sqlite-backup.php'
    $result = Invoke-NativeResult (Get-HerdPhp) @($helper,'snapshot',(Get-ProductionDatabase),(Join-Path $folder 'database.sqlite')) (Get-ProductionEnv)
    if ($result.ExitCode -ne 0) { throw "SQLite snapshot failed; incomplete backup: $folder" }
    $database = $result.Output | ConvertFrom-Json
    foreach ($kind in @('private','public')) {
        $source = Join-Path (Get-ProductionRoot) "storage\app\$kind"
        Assert-PlainPath $source
        if (Test-Path -LiteralPath $source) {
            foreach ($item in Get-ChildItem -LiteralPath $source -Recurse -Force) { Assert-PlainPath $item.FullName }
            $copy = Invoke-NativeResult 'robocopy.exe' @($source,(Join-Path $folder "storage-app\$kind"),'/E','/COPY:DAT','/R:1','/W:1')
            if ($copy.ExitCode -gt 7) { throw "Upload backup failed; incomplete backup: $folder" }
        }
    }
    [IO.File]::Copy((Get-ProductionEnv),(Join-Path $folder 'production.env'),$false)
    $files = @{}
    foreach ($file in Get-ChildItem -LiteralPath $folder -File -Recurse -Force) { $files[$file.FullName.Substring($folder.Length+1)] = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash }
    $manifest = @{ created_utc=[DateTime]::UtcNow.ToString('o'); git_sha=$sha; db=$database; files=$files }
    Write-Utf8File (Join-Path $folder 'manifest.json') ($manifest | ConvertTo-Json -Depth 10)
    $saved = [IO.File]::ReadAllText((Join-Path $folder 'manifest.json')) | ConvertFrom-Json
    foreach ($file in $saved.files.PSObject.Properties) {
        if ((Get-FileHash -LiteralPath (Join-Path $folder $file.Name) -Algorithm SHA256).Hash -ne $file.Value) { throw "Backup hash mismatch: $folder" }
    }
    Invoke-Checked (Get-HerdPhp) @($helper,'check',(Join-Path $folder 'database.sqlite')) (Get-ProductionEnv)
    Protect-PrivatePath $folder
    $total = (Get-ChildItem -LiteralPath (Join-Path (Get-ProductionHome) 'backups') -File -Recurse -Force | Measure-Object Length -Sum).Sum
    Write-Host "Backup: $folder; total retained bytes: $total"
    if ($total -gt 5GB) {
        $oldest = (Get-ChildItem -LiteralPath (Join-Path (Get-ProductionHome) 'backups') -Directory | Sort-Object Name | Select-Object -First 5 -ExpandProperty Name) -join ', '
        Write-Warning "Backups exceed 5 GB. Oldest: $oldest. Nothing was deleted."
    }
    return $folder
}

function Invoke-LoggedChild {
    param([Diagnostics.ProcessStartInfo]$StartInfo, [string]$LogPath)
    if (-not ('PrintForMe.ChildLog' -as [type])) {
        Add-Type -TypeDefinition @"
using System;
using System.Diagnostics;
using System.IO;
namespace PrintForMe {
    public static class ChildLog {
        public static int Run(ProcessStartInfo start, string path) {
            start.RedirectStandardOutput = true;
            start.RedirectStandardError = true;
            using (var writer = new StreamWriter(new FileStream(path, FileMode.Append, FileAccess.Write, FileShare.Read)))
            using (var process = new Process()) {
                writer.AutoFlush = true;
                var gate = new object();
                DataReceivedEventHandler append = (sender, args) => {
                    if (args.Data != null) { lock (gate) { writer.WriteLine(args.Data); } }
                };
                process.StartInfo = start;
                process.OutputDataReceived += append;
                process.ErrorDataReceived += append;
                process.Start();
                process.BeginOutputReadLine();
                process.BeginErrorReadLine();
                process.WaitForExit();
                return process.ExitCode;
            }
        }
    }
}
"@
    }
    return [PrintForMe.ChildLog]::Run($StartInfo, $LogPath)
}
