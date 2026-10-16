param([ValidateRange(1024, 65535)][int]$Puerto = 8001)

$ErrorActionPreference = 'Stop'
$raizTerritorial = Join-Path $PSScriptRoot 'compartido/territorios'
Write-Host "Catálogo territorial: http://127.0.0.1:$Puerto/revisar_catalogo.php" -ForegroundColor Green
Write-Host 'Esta herramienta local permite revisar la propuesta del issue #16. Detener con Ctrl+C.'
php -S "127.0.0.1:$Puerto" -t $raizTerritorial
exit $LASTEXITCODE
