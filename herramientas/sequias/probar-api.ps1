param([string]$UrlBase = 'http://127.0.0.1:5789')
$ErrorActionPreference = 'Stop'
$consultaApiSequias = Invoke-RestMethod "$UrlBase/api/v1/sequias?mesFinal=2024-09&municipio=BO070101&muestra=scz-piloto-01"
$detalleApiSequias = Invoke-RestMethod "$UrlBase/api/v1/sequias/scz-piloto-01?mesFinal=2024-09&municipio=BO070101"
$resumenApiSequias = Invoke-RestMethod "$UrlBase/api/v1/sequias/resumen?mesFinal=2024-09&municipio=BO070101&muestra=scz-piloto-01"
if ($consultaApiSequias.versionContrato -ne 'sequias-1.0-m3-candidata' -or $consultaApiSequias.datos.Count -ne 1 -or
    $consultaApiSequias.datos[0].spi.categoria -ne 'normal' -or
    [math]::Abs($consultaApiSequias.datos[0].spi.valor - (-0.6410480784168507)) -gt 0.000001 -or
    $consultaApiSequias.datos[0].spi.valor -ne $detalleApiSequias.datos[0].spi.valor -or
    $resumenApiSequias.resumen.spiMinimo -ne $consultaApiSequias.datos[0].spi.valor -or
    $consultaApiSequias.representacionMapa.rellenarPoligonos -ne $false -or
    $consultaApiSequias.datos[0].territorioContexto -ne 'BO070101' -or
    $consultaApiSequias.datos[0].municipioDelCentroCelda -ne 'BO070103') {
    throw 'El piloto no coincide entre consulta, detalle, resumen o referencia.'
}
Write-Host 'API de sequias: consulta, detalle y resumen coinciden.' -ForegroundColor Green
Write-Host "SPI: $($consultaApiSequias.datos[0].spi.valor); categoria: $($consultaApiSequias.datos[0].spi.categoria)."
Write-Host "Lluvia julio-septiembre 2024: $($consultaApiSequias.datos[0].precipitacionAcumuladaMm) mm."
Write-Host 'Contexto BO070101; centro de celda BO070103. Una muestra, sin superficie municipal evaluada.'
Write-Host 'Si responde 503 por vencimiento: renovar por consola segun docs/API02_API_SEQUIAS.md.'
exit 0
