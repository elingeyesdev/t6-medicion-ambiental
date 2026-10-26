param([switch] $ConsultarPiloto)

$ErrorActionPreference = 'Stop'
$raizMuestras = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$ejecutorMuestras = Join-Path $raizMuestras 'herramientas/php.ps1'

function Invoke-PruebaMuestra {
    param([string[]] $Argumentos)
    $textoMuestras = & $ejecutorMuestras artisan @Argumentos
    if ($LASTEXITCODE -ne 0) { throw ($textoMuestras -join ' ') }
    return ($textoMuestras -join "`n" | ConvertFrom-Json)
}

Push-Location (Join-Path $raizMuestras 'web')
try {
    $todasMuestras = Invoke-PruebaMuestra -Argumentos @('sequias:muestras')
    $provinciaMuestras = Invoke-PruebaMuestra -Argumentos @('sequias:muestras', '--provincia=BO0701')
    $pilotoMuestras = Invoke-PruebaMuestra -Argumentos @('sequias:muestras', '--municipio=BO070101')
    if ($todasMuestras.datos.Count -ne 56 -or $provinciaMuestras.datos.Count -ne 5 -or $pilotoMuestras.datos.Count -ne 1 -or $pilotoMuestras.datos[0].identificador -ne 'scz-piloto-01' -or $pilotoMuestras.filtrosEfectivos.provincia -ne 'BO0701') {
        throw 'No coincide el catalogo o los filtros esperados.'
    }
    if ($todasMuestras.resumen.consultasClimaticasEnEstaOperacion -ne 0 -or $todasMuestras.resumen.spiCalculadosEnEstaOperacion -ne 0 -or $null -ne $pilotoMuestras.datos[0].spi -or $null -ne $pilotoMuestras.datos[0].coordenadaCelda) {
        throw 'Listar puntos no debe simular consultas de lluvia, celdas o indices.'
    }
    Write-Host 'Catalogo candidato: 56 puntos interiores, uno por unidad municipal del catalogo.' -ForegroundColor Green
    Write-Host 'Filtro BO0701 (Andres Ibanez): 5 muestras. BO070101: 1 muestra, scz-piloto-01.'
    Write-Host 'Piloto conservado: longitud -63.1821, latitud -17.7833.'
    Write-Host 'Listado sin Internet: celda e indice null; no acredita cobertura climatica.' -ForegroundColor Yellow
    if ($ConsultarPiloto) {
        $capturaMuestra = Invoke-PruebaMuestra -Argumentos @('sequias:capturar-muestra', 'scz-piloto-01', '--desde=2024-07-01', '--hasta=2024-09-30')
        if ($capturaMuestra.muestraCatalogada.municipio -ne 'BO070101' -or $capturaMuestra.version.informe.resumen.diasValidos -ne 92) {
            throw 'La captura no conserva el contexto y la cobertura diaria esperados del piloto.'
        }
        Write-Host "Captura registrada: $($capturaMuestra.tipoObtencion); 92 dias completos."
        Write-Host "Municipio de referencia: $($capturaMuestra.muestraCatalogada.municipio). Municipio del centro de celda: $($capturaMuestra.municipioDelCentroCelda)."
        Write-Host 'Se conserva el municipio de referencia; una celda no representa todo el municipio.'
    }
} finally {
    Pop-Location
}
exit 0
