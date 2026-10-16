param(
    [string]$Direccion = '127.0.0.1',
    [ValidateRange(1024, 65535)]
    [int]$Puerto = 8000
)

$ErrorActionPreference = 'Stop'
$ejecutorPhp = Join-Path $PSScriptRoot 'herramientas/php.ps1'
$directorioWeb = Join-Path $PSScriptRoot 'web'

if (-not (Test-Path (Join-Path $directorioWeb 'vendor/autoload.php'))) {
    throw 'Primero ejecute composer install dentro de web/. Consulte web/README.md.'
}

Push-Location $directorioWeb
try {
    & $ejecutorPhp scripts/preparar.php
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la configuración local.' }
    Write-Host "GeoCruz oficial: http://127.0.0.1:$Puerto" -ForegroundColor Green
    Write-Host "API de salud: http://127.0.0.1:$Puerto/api/salud"
    & $ejecutorPhp artisan serve "--host=$Direccion" "--port=$Puerto" --no-reload
    $codigoServidor = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $codigoServidor
