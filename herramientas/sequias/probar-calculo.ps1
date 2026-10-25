param([switch]$Recalcular)

$ErrorActionPreference = 'Stop'
$raizCacheSpi = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$ejecutorCacheSpi = Join-Path $raizCacheSpi 'herramientas/php.ps1'
$argumentosCacheSpi = @('artisan', 'sequias:ensayar-spi',
    '7642d10dab58e12edaf928a3d4548a3d7d804c74aa5c36d0fc585316ab51dd79',
    '195010a0862fd4f0384b65693bb3a7966fa0540f1ea5c41c54c0fb7313ebb723', '2024-09', '--cache')

Push-Location (Join-Path $raizCacheSpi 'web')
try {
    $primeraArgumentosCacheSpi = @($argumentosCacheSpi)
    if ($Recalcular) { $primeraArgumentosCacheSpi += '--recalcular' }
    $primeraCacheSpi = & $ejecutorCacheSpi @primeraArgumentosCacheSpi
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo calcular/reutilizar el piloto. Requiere las dos versiones locales; ver docs/API02_CACHE_CALCULO.md. $($primeraCacheSpi -join ' ')"
    }
    $segundaCacheSpi = & $ejecutorCacheSpi @argumentosCacheSpi
    if ($LASTEXITCODE -ne 0) { throw "Fallo al reutilizar: $($segundaCacheSpi -join ' ')" }
    $aCacheSpi = $primeraCacheSpi -join "`n" | ConvertFrom-Json
    $bCacheSpi = $segundaCacheSpi -join "`n" | ConvertFrom-Json
    if ($aCacheSpi.estado -ne 'ensayo_pendiente_revision' -or $bCacheSpi.cacheCalculo.esCalculoNuevo -ne $false -or
        $aCacheSpi.cacheCalculo.huella -cne $bCacheSpi.cacheCalculo.huella -or
        $aCacheSpi.cacheCalculo.calculadoEn -ne $bCacheSpi.cacheCalculo.calculadoEn -or
        $aCacheSpi.cacheCalculo.expiraEn -ne $bCacheSpi.cacheCalculo.expiraEn -or
        ($Recalcular -and $aCacheSpi.cacheCalculo.esCalculoNuevo -ne $true)) {
        throw 'La segunda consulta no reutilizo el mismo calculo o cambio sus fechas.'
    }
    foreach ($rCacheSpi in @($aCacheSpi, $bCacheSpi)) {
        if ($null -eq $rCacheSpi.resultado.valor -or $rCacheSpi.resultado.categoria -ne 'normal' -or
            [math]::Abs($rCacheSpi.resultado.valor - (-0.6410480784168507)) -gt 0.000001 -or
            [math]::Abs($rCacheSpi.precipitacionAcumuladaMm - 114.5) -gt 0.000001) {
            throw 'El resultado no coincide con el piloto historico de referencia.'
        }
    }
    for ($iCacheSpi = 0; $iCacheSpi -lt 2; $iCacheSpi++) {
        if (($aCacheSpi.versiones[$iCacheSpi] | ConvertTo-Json -Depth 20 -Compress) -cne
            ($bCacheSpi.versiones[$iCacheSpi] | ConvertTo-Json -Depth 20 -Compress)) {
            throw 'Se alteraron identificador, integridad o procedencia de una entrada.'
        }
    }
    Write-Host 'Cache del ensayo SPI-3: metodo #35 acordado; implementacion #43 para revision.' -ForegroundColor Yellow
    Write-Host "Primera: $($aCacheSpi.cacheCalculo.tipo). Segunda: $($bCacheSpi.cacheCalculo.tipo)." -ForegroundColor Green
    Write-Host "SPI: $($bCacheSpi.resultado.valor); categoria: $($bCacheSpi.resultado.categoria); lluvia: $($bCacheSpi.precipitacionAcumuladaMm) mm."
    Write-Host "Calculado: $(([datetimeoffset]$bCacheSpi.cacheCalculo.calculadoEn).ToUniversalTime().ToString('o'))"
    Write-Host 'Mismo calculo y fechas en la segunda consulta; originales verificados otra vez.'
    Write-Host 'No hubo descarga ni se renovaron los datos de 2024. Esta prueba de cache no publica datos HTTP.'
} finally {
    Pop-Location
}
exit 0
