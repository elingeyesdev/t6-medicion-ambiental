[CmdletBinding()]
param(
    [ValidateSet('instalar', 'estado', 'pausar')][string]$Accion = 'estado',
    [string]$Php,
    [string]$Ini
)
$ErrorActionPreference = 'Stop'
$raizRaster = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../../..')).Path
$hashRaster = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($raizRaster.ToLowerInvariant()))).Substring(0, 12)
$nombreRaster = "GeoSur-Capas-$hashRaster"
$scriptRaster = Join-Path $PSScriptRoot 'ejecutar-local.ps1'
$tareaRaster = Get-ScheduledTask -TaskName $nombreRaster -ErrorAction SilentlyContinue
if ($tareaRaster -and $tareaRaster.Actions.Arguments -notlike "*$scriptRaster*") {
    throw 'Existe otra tarea con ese nombre; se conserva para revisión.'
}
if ($Accion -eq 'estado') {
    if (-not $tareaRaster) { Write-Output 'La actualización local todavía no está programada.'; return }
    $infoRaster = Get-ScheduledTaskInfo -TaskName $nombreRaster
    [pscustomobject]@{ Nombre = $nombreRaster; Estado = [string]$tareaRaster.State; UltimoResultado = $infoRaster.LastTaskResult; ProximaEjecucion = $infoRaster.NextRunTime } | ConvertTo-Json
    return
}
if ($Accion -eq 'pausar') {
    if ($tareaRaster) { Disable-ScheduledTask -TaskName $nombreRaster | Out-Null }
    Write-Output 'La actualización local está pausada. Una ejecución que ya comenzó puede terminar su publicación.'
    return
}
if (-not $Php -or -not $Ini -or -not (Test-Path -LiteralPath $Php -PathType Leaf) -or -not (Test-Path -LiteralPath $Ini -PathType Leaf)) {
    throw 'Para instalar, indica -Php y -Ini con rutas existentes.'
}
$pwshRaster = (Get-Process -Id $PID).Path
$argumentosRaster = '-NoProfile -NonInteractive -WindowStyle Hidden -File "{0}" -Php "{1}" -Ini "{2}"' -f $scriptRaster, $Php, $Ini
$accionRaster = New-ScheduledTaskAction -Execute $pwshRaster -Argument $argumentosRaster -WorkingDirectory $raizRaster
$disparadorRaster = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(5) -RepetitionInterval (New-TimeSpan -Hours 1)
$opcionesRaster = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -Hidden
$principalRaster = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $nombreRaster -Action $accionRaster -Trigger $disparadorRaster -Settings $opcionesRaster -Principal $principalRaster -Description 'Actualiza los recortes GFS locales de GeoSur; sin privilegios de administrador ni descargas desde visitas web.' -Force | Out-Null
Write-Output "Programada cada hora: $nombreRaster. Usa -Accion pausar para detener las siguientes ejecuciones."
