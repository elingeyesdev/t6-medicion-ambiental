param([switch] $Renovar)

$ErrorActionPreference = 'Stop'
$raizCaptura = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$ejecutorCaptura = Join-Path $raizCaptura 'herramientas/php.ps1'

function Invoke-CapturaPiloto {
    param([bool] $Forzar = $false)
    $opcionesCaptura = @('sequias:capturar-serie', 'scz-piloto-01', '--longitud=-63.1821', '--latitud=-17.7833', '--desde=2024-07-01', '--hasta=2024-09-30')
    if ($Forzar) { $opcionesCaptura += '--renovar' }
    $textoCaptura = & $ejecutorCaptura artisan @opcionesCaptura
    if ($LASTEXITCODE -ne 0) {
        throw "La captura no esta completa o fallo. Ver el motivo del comando: $($textoCaptura -join ' ')"
    }
    return ($textoCaptura -join "`n" | ConvertFrom-Json)
}

Push-Location (Join-Path $raizCaptura 'web')
try {
    $primeraCaptura = Invoke-CapturaPiloto -Forzar $Renovar.IsPresent
    $segundaCaptura = Invoke-CapturaPiloto
    if ($segundaCaptura.esConsultaNueva -or $segundaCaptura.tipoObtencion -ne 'cache_captura' -or $primeraCaptura.version.identificador -ne $segundaCaptura.version.identificador -or $primeraCaptura.version.procedencia.obtenidoEn -ne $segundaCaptura.version.procedencia.obtenidoEn) {
        throw 'La repeticion no reutilizo la misma captura y fecha originales.'
    }
    if ($primeraCaptura.version.informe.resumen.diasValidos -ne 92 -or $primeraCaptura.version.integridad -ne 'verificada') {
        throw 'El piloto no tiene los 92 dias completos con integridad verificada.'
    }
    $detalleTextoCaptura = & $ejecutorCaptura artisan sequias:ver-serie $primeraCaptura.version.identificador --detalle
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo comprobar la captura conservada.' }
    $detalleCaptura = $detalleTextoCaptura -join "`n" | ConvertFrom-Json
    $totalCaptura = ($detalleCaptura.informe.meses | Measure-Object -Property precipitacionAcumuladaMm -Sum).Sum
    $fechaCaptura = $primeraCaptura.version.procedencia.obtenidoEn
    if ($fechaCaptura -is [datetime] -or $fechaCaptura -is [datetimeoffset]) {
        $fechaCaptura = $fechaCaptura.ToUniversalTime().ToString("yyyy-MM-dd'T'HH:mm:ss.ffffff'Z'", [System.Globalization.CultureInfo]::InvariantCulture)
    }
    Write-Host 'Captura Open-Meteo / ERA5; metodo #35 acordado. No publica SPI por si sola.' -ForegroundColor Yellow
    Write-Host "Primera obtencion: $($primeraCaptura.tipoObtencion). Recuperada UTC: $fechaCaptura."
    Write-Host "92 dias completos de julio-septiembre de 2024. Total: $totalCaptura mm." -ForegroundColor Green
    Write-Host 'Repeticion: misma version y fecha, sin nueva solicitud al proveedor.'
    Write-Host "ID: $($primeraCaptura.version.identificador)"
    Write-Host 'Una celda de reanalisis, no una estacion ni todo el municipio. La API publica sigue en preparacion.'
} finally {
    Pop-Location
}
exit 0
