$ErrorActionPreference = 'Stop'
$raizEnsayoSpi = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$ejecutorEnsayoSpi = Join-Path $raizEnsayoSpi 'herramientas/php.ps1'

Push-Location (Join-Path $raizEnsayoSpi 'web')
try {
    $salidaEnsayoSpi = & $ejecutorEnsayoSpi artisan sequias:ensayar-spi 7642d10dab58e12edaf928a3d4548a3d7d804c74aa5c36d0fc585316ab51dd79 195010a0862fd4f0384b65693bb3a7966fa0540f1ea5c41c54c0fb7313ebb723 2024-09
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo reproducir el piloto. Requiere las dos versiones locales preparadas; ver docs/API02_ENSAYO_SPI.md. $($salidaEnsayoSpi -join ' ')"
    }
    $datosEnsayoSpi = $salidaEnsayoSpi -join "`n" | ConvertFrom-Json
    if ($datosEnsayoSpi.estado -ne 'ensayo_pendiente_revision' -or $datosEnsayoSpi.resultado.categoria -ne 'normal' -or [math]::Abs($datosEnsayoSpi.resultado.valor - (-0.6410480784168507)) -gt 0.000001 -or [math]::Abs($datosEnsayoSpi.precipitacionAcumuladaMm - 114.5) -gt 0.000001) {
        throw 'El piloto no coincide con la referencia conservada. Revisar la salida del comando individual.'
    }
    Write-Host 'Ensayo offline SPI-3 del metodo #35 acordado; no publica datos HTTP por si solo.' -ForegroundColor Yellow
    Write-Host "Julio-septiembre 2024: $($datosEnsayoSpi.precipitacionAcumuladaMm) mm."
    Write-Host "SPI: $($datosEnsayoSpi.resultado.valor); categoria: $($datosEnsayoSpi.resultado.categoria)." -ForegroundColor Green
    Write-Host "Base: $($datosEnsayoSpi.resultado.aniosValidos) ventanas completas de 1991-2020."
    Write-Host 'Las capturas guardadas tienen integridad verificada; no hubo descarga nueva.'
    Write-Host 'Una celda de reanalisis: no acredita sequia ni superficie afectada de todo el municipio.'
} finally {
    Pop-Location
}
exit 0
