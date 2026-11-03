[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Php,
    [Parameter(Mandatory)][string]$Ini
)
$ErrorActionPreference = 'Stop'
$raizRaster = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../../..')).Path
if (-not (Test-Path -LiteralPath $Php -PathType Leaf) -or -not (Test-Path -LiteralPath $Ini -PathType Leaf)) {
    throw 'Revisa las rutas del ejecutable PHP y su configuración.'
}
Push-Location -LiteralPath (Join-Path $raizRaster 'web')
try {
    & $Php -c $Ini artisan capas:actualizar --consultar-noaa --programada --no-interaction
    $resultadoRaster = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $resultadoRaster
