<#
.SYNOPSIS
Run a guarded production Artisan command with isolated environment.
.DESCRIPTION
Use artisan.ps1 <arguments>. Destructive database/key/test commands are denied.
Never use tests or seeders in production. Production.env remains canonical.
#>
param([Parameter(ValueFromRemainingArguments=$true)][string[]]$Arguments)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
try {
    . "$PSScriptRoot/common.ps1"
    [void](Get-ProductionInfo)
    Invoke-Artisan $Arguments
} catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
