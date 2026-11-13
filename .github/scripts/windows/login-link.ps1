<#
.SYNOPSIS
Print one single-use sign-in link for an existing active production user.
.DESCRIPTION
Run login-link.ps1 -Email <owner-email>. Open the printed URL on your device within
10 minutes. Issuing another link cancels this one. Do not copy links into reports.
This sends no email and creates no account. Local production remains owner-only.
#>
param([Parameter(Mandatory=$true)][string]$Email)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
try {
    . "$PSScriptRoot/common.ps1"
    [void](Get-ProductionInfo)
    Invoke-Artisan @('auth:login-link',$Email)
    Write-Host 'Open this link on your device within 10 minutes. It can be used once.'
} catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
