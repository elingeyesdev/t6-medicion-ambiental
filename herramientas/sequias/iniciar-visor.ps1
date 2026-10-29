param([ValidateRange(1024, 65535)][int]$Puerto = 5790)
$ErrorActionPreference = 'Stop'
$raizVisorSequias = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Push-Location (Join-Path $raizVisorSequias 'web/public')
try {
    Write-Host "GeoCruz: http://127.0.0.1:$Puerto/modulos/sequias"
    & (Join-Path $raizVisorSequias 'herramientas/php.ps1') -S "127.0.0.1:$Puerto" -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
} finally {
    Pop-Location
}
