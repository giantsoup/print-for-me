<#
.SYNOPSIS
Check production tasks, managed Herd config, HTTP, Serve mapping and deployed SHA.
.DESCRIPTION
Read-only. Requires Herd running. Schedules use UTC: daily jobs at 02:10-02:40 UTC
(18:10-18:40 PST or 19:10-19:40 PDT). Secure cookies require tailnet HTTPS.
Keep this instance owner-only until source-preview SSRF is fixed.
AllowMissingServe warns instead of failing when setup is complete but Serve is not configured yet.
#>
param([switch]$AllowMissingServe)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
try {
    . "$PSScriptRoot/common.ps1"
    [void](Get-ProductionInfo)
    Test-HerdSite
    Test-ProductionHttp
    try {
        $serve = Invoke-NativeResult (Join-Path $env:ProgramFiles 'Tailscale\tailscale.exe') @('serve','status','--json')
        if ($serve.ExitCode -ne 0) { throw 'Cannot read Serve status.' }
        $configuration = $serve.Output | ConvertFrom-Json
        $serviceProperty = $configuration.Services.PSObject.Properties['svc:print-for-me']
        if (-not $serviceProperty -or ($serviceProperty.Value | ConvertTo-Json -Depth 30 -Compress) -notmatch 'http://127\.0\.0\.1:8030') { throw 'Serve has no svc:print-for-me mapping to loopback 8030.' }
    } catch {
        if (-not $AllowMissingServe) { throw }
        Write-Warning "Serve is not ready: $($_.Exception.Message)"
    }
    Assert-ApplicationEnvironment
    Invoke-Artisan @('about','--only=environment','--json')
    Write-Host ('Deployed SHA: ' + (Invoke-Git @('rev-parse','HEAD')))
    Write-Host 'Schedule timezone: UTC (02:10-02:40 UTC daily; 18:10-18:40 PST / 19:10-19:40 PDT).'
} catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
