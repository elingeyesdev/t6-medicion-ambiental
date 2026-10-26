$ErrorActionPreference = 'Stop'
$raizApiSequias = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Push-Location (Join-Path $raizApiSequias 'web/public')
try {
    & (Join-Path $raizApiSequias 'herramientas/php.ps1') -S 127.0.0.1:5789 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
} finally {
    Pop-Location
}
