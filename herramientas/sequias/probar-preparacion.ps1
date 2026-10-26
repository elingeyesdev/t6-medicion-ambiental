$ErrorActionPreference = 'Stop'
$raizSeries = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$ejecutorSeries = Join-Path $raizSeries 'herramientas/php.ps1'

function Invoke-PruebaSerie {
    param([string[]] $Argumentos, [int] $CodigoEsperado = 0)
    $salidaSerie = & $ejecutorSeries artisan @Argumentos
    $codigoSerie = $LASTEXITCODE
    if ($codigoSerie -ne $CodigoEsperado) {
        throw "La prueba de preparación devolvió $codigoSerie en lugar de $CodigoEsperado. $($salidaSerie -join ' ')"
    }
    return ($salidaSerie -join "`n" | ConvertFrom-Json)
}

Push-Location (Join-Path $raizSeries 'web')
try {
    $completaSeries = Invoke-PruebaSerie -Argumentos @('sequias:preparar-serie', '../docs/ejemplos/sequias/serie-completa.json', '../docs/ejemplos/sequias/procedencia-completa.json')
    $repetidaSeries = Invoke-PruebaSerie -Argumentos @('sequias:preparar-serie', '../docs/ejemplos/sequias/serie-completa.json', '../docs/ejemplos/sequias/procedencia-completa.json')
    $leidaSeries = Invoke-PruebaSerie -Argumentos @('sequias:ver-serie', $completaSeries.identificador, '--detalle')
    $incompletaSeries = Invoke-PruebaSerie -CodigoEsperado 1 -Argumentos @('sequias:preparar-serie', '../docs/ejemplos/sequias/serie-incompleta.json', '../docs/ejemplos/sequias/procedencia-incompleta.json')
    $detalleSeries = Invoke-PruebaSerie -CodigoEsperado 1 -Argumentos @('sequias:ver-serie', $incompletaSeries.identificador, '--detalle')
    if ($completaSeries.identificador -ne $repetidaSeries.identificador -or -not $repetidaSeries.reutilizada -or $leidaSeries.integridad -ne 'verificada') {
        throw 'No se verificó la reutilización de la misma versión.'
    }
    if ($leidaSeries.informe.meses[0].precipitacionAcumuladaMm -ne 60 -or $null -ne $detalleSeries.informe.meses[0].precipitacionAcumuladaMm) {
        throw 'No coinciden los acumulados esperados de los ejemplos.'
    }
    if ($detalleSeries.informe.resumen.diasValidos -ne 28 -or $detalleSeries.informe.resumen.diasAusentes -ne 1 -or $detalleSeries.informe.resumen.diasNulos -ne 1 -or $detalleSeries.informe.meses[0].fechasAusentes -notcontains '2024-06-10' -or $detalleSeries.informe.meses[0].fechasNulas -notcontains '2024-06-20') {
        throw 'No coinciden los faltantes esperados de la serie incompleta.'
    }
    Write-Host 'Ejemplos controlados: sin lluvia observada ni cálculo de sequía.' -ForegroundColor Cyan
    Write-Host "Completa: $($leidaSeries.informe.resumen.diasValidos) días válidos; 60 mm; integridad verificada." -ForegroundColor Green
    Write-Host "Repetida: misma versión reutilizada. ID: $($completaSeries.identificador)"
    Write-Host 'Incompleta: día 10 ausente, día 20 nulo; acumulado null; versión conservada.' -ForegroundColor Yellow
    Write-Host "ID incompleta: $($incompletaSeries.identificador)"
    Write-Host 'No se consultaron proveedores ni se habilitó la API de sequías.'
} finally {
    Pop-Location
}
exit 0
