param(
    [ValidatePattern('^\d{4}-\d{2}-\d{2}$')]
    [string]$FechaUtc = [DateTime]::UtcNow.Date.AddDays(-1).ToString('yyyy-MM-dd'),
    [ValidateRange(1024, 65535)]
    [int]$Puerto = 8002,
    [ValidateSet('', 'alta', 'nominal', 'baja', 'desconocida')]
    [string]$Confianza = '',
    [switch]$GuardarEvidencia
)

$ErrorActionPreference = 'Stop'
$cultura = [System.Globalization.CultureInfo]::InvariantCulture

function Mostrar-Valor($Valor) {
    if ($null -eq $Valor) { return 'Sin dato' }
    return [Convert]::ToString($Valor, $cultura)
}

function Mostrar-Instante($Valor) {
    # PowerShell 7 puede convertir fechas JSON automaticamente; 5.1 las conserva como texto.
    if ($Valor -is [DateTimeOffset]) { return $Valor.UtcDateTime.ToString('yyyy-MM-ddTHH:mm:ssZ', $cultura) }
    if ($Valor -is [DateTime]) { return $Valor.ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ', $cultura) }
    return Mostrar-Valor $Valor
}

try {
    $inicio = [DateTime]::ParseExact($FechaUtc, 'yyyy-MM-dd', $cultura)
    $fin = $inicio.AddDays(1)
} catch {
    Write-Error 'FechaUtc debe ser una fecha real con formato yyyy-MM-dd.'
    exit 1
}

$desde = $inicio.ToString('yyyy-MM-dd') + 'T00:00:00Z'
$hasta = $fin.ToString('yyyy-MM-dd') + 'T00:00:00Z'
$url = "http://127.0.0.1:$Puerto/api/v1/focos-calor?desde=$desde&hasta=$hasta&tamanioPagina=500"
if ($Confianza) { $url += '&confianza=' + $Confianza }

Write-Output 'GeoCruz - Demostracion de la API real de NASA'
Write-Output "Consulta al backend: $url"
Write-Output 'Esperando respuesta externa o una instantanea vigente del backend...'

try {
    $http = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 80
    $textoRespuesta = $http.Content
    $respuesta = $textoRespuesta | ConvertFrom-Json
    if ($respuesta.consulta.modo -ne 'externo' -or $null -eq $respuesta.resumen.totalRegistros) {
        throw 'Respuesta no reconocida.'
    }
} catch {
    Write-Error 'La consulta no pudo completarse. Comprueba iniciar-api.ps1, la configuracion privada del backend, las fechas y el estado del proveedor. No se sustituyeron los resultados por una simulacion.'
    exit 1
}

$fuente = @($respuesta.fuentes)[0]
$alta = @($respuesta.resumen.indicadores | Where-Object codigo -eq 'focosConfianzaAlta')[0]
$frp = @($respuesta.resumen.indicadores | Where-Object codigo -eq 'potenciaMaxima')[0]
$obtencion = $respuesta.consulta.obtencion
$datos = @($respuesta.datos)

Write-Output ''
Write-Output "Fuente: $($fuente.proveedor); producto: $($fuente.producto); naturaleza: $($fuente.naturaleza)"
Write-Output "Territorio: Santa Cruz completo; ID $($respuesta.consulta.territorio.identificador)"
Write-Output "Periodo UTC: $desde incluido -> $hasta excluido"
Write-Output ("Equivalente Bolivia UTC-4: {0} -> {1}" -f $inicio.AddHours(-4).ToString('yyyy-MM-dd HH:mm'), $fin.AddHours(-4).ToString('yyyy-MM-dd HH:mm'))
Write-Output "Filtro de confianza: $(if ($Confianza) { $Confianza } else { 'todas' })"
Write-Output "Obtencion: $($obtencion.tipo); recuperacion UTC: $(Mostrar-Instante $obtencion.fechaRecuperacion)"
Write-Output "Antiguedad al responder: $($obtencion.antiguedadSegundos) segundos; vence: $(Mostrar-Instante $obtencion.expiraEn)"
Write-Output "Actualizacion del producto: $(Mostrar-Instante $obtencion.fechaActualizacion)"
Write-Output "Detecciones en todo el conjunto filtrado: $($respuesta.resumen.totalRegistros)"
Write-Output "Confianza alta: $(Mostrar-Valor $alta.valor)"
Write-Output "FRP maxima: $(Mostrar-Valor $frp.valor) $($frp.unidad)"
Write-Output "Registros con faltantes: $($respuesta.resumen.registrosConFaltantes)"
Write-Output "Pagina actual: $($respuesta.paginacion.pagina); registros recibidos: $($datos.Count); alcance del mapa: $($respuesta.representacionMapa.alcance)"
Write-Output "Cobertura: $($respuesta.cobertura.estado)"
Write-Output "Fundamento: $($respuesta.cobertura.fundamento)"

if ($datos.Count -gt 0) {
    $primero = $datos[0]
    Write-Output ''
    Write-Output 'Primera deteccion de esta pagina (no representa todo el conjunto):'
    Write-Output "ID: $($primero.identificador)"
    Write-Output "Adquisicion UTC: $(Mostrar-Instante $primero.fechas.observadoDesde)"
    Write-Output "Coordenadas [longitud, latitud]: [$($primero.geometria.coordinates -join ', ')]"
    Write-Output "Confianza: $($primero.atributos.confianza); FRP: $(Mostrar-Valor $primero.atributos.potenciaRadiativa) $($primero.atributos.unidadPotenciaRadiativa)"
} else {
    Write-Output 'Sin detecciones en esta pagina. No demuestra ausencia de incendios.'
}

Write-Output ''
Write-Output 'Interpretacion: detecciones termicas, no incendios confirmados. Confianza no es gravedad; MW no mide superficie quemada.'
Write-Output 'Recuperacion no equivale a adquisicion. Catalogo y contrato candidatos: revision del equipo pendiente.'
Write-Output 'La API devuelve JSON; el mapa web corresponde a #19.'

if ($GuardarEvidencia) {
    $carpeta = Join-Path $PSScriptRoot 'tmp/demostracion-api'
    New-Item -ItemType Directory -Force -Path $carpeta | Out-Null
    $ruta = Join-Path $carpeta ('consulta_' + [DateTime]::UtcNow.ToString('yyyyMMdd_HHmmss_fffffff') + '.json')
    [System.IO.File]::WriteAllText($ruta, $textoRespuesta, [System.Text.UTF8Encoding]::new($false))
    Write-Output "Respuesta completa guardada localmente: $ruta"
}
