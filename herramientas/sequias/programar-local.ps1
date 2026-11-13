[CmdletBinding()]
param(
    [ValidateSet('instalar', 'estado', 'pausar')][string]$Accion = 'estado',
    [string]$Pythonw,
    [string]$Php,
    [string]$Ini
)

$ErrorActionPreference = 'Stop'
$raiz = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../..')).Path
$sha = [Security.Cryptography.SHA256]::Create()
try {
    $hash = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($raiz.ToLowerInvariant()))).Replace('-', '').Substring(0, 12)
} finally {
    $sha.Dispose()
}
$nombre = "GeoSur-Sequias-$hash"
$script = Join-Path $PSScriptRoot 'renovar-local.pyw'
$tarea = Get-ScheduledTask -TaskName $nombre -ErrorAction SilentlyContinue
if ($tarea -and $tarea.Actions.Arguments -notlike "*$script*") {
    throw 'Ya existe una tarea distinta con ese nombre.'
}

if ($Accion -eq 'estado') {
    if (-not $tarea) {
        Write-Output 'La renovación de Sequías aún no está programada.'
        return
    }
    $info = Get-ScheduledTaskInfo -TaskName $nombre
    [pscustomobject]@{
        Nombre = $nombre
        Estado = [string]$tarea.State
        UltimoResultado = $info.LastTaskResult
        UltimaEjecucion = $info.LastRunTime.ToString('o')
        ProximaEjecucion = $info.NextRunTime.ToString('o')
    } | ConvertTo-Json
    return
}
if ($Accion -eq 'pausar') {
    if ($tarea) { Disable-ScheduledTask -TaskName $nombre | Out-Null }
    return
}

foreach ($ruta in @($Pythonw, $Php, $Ini)) {
    if (-not $ruta -or -not (Test-Path -LiteralPath $ruta -PathType Leaf)) {
        throw 'Indica rutas existentes en -Pythonw, -Php e -Ini.'
    }
}
$Pythonw = (Resolve-Path -LiteralPath $Pythonw).Path
if ([IO.Path]::GetFileName($Pythonw) -ine 'pythonw.exe') {
    throw 'Usa pythonw.exe para ejecutar la tarea sin consola.'
}
$Php = (Resolve-Path -LiteralPath $Php).Path
$Ini = (Resolve-Path -LiteralPath $Ini).Path
$argumentos = '"{0}" --php "{1}" --ini "{2}"' -f $script, $Php, $Ini
$accion = New-ScheduledTaskAction -Execute $Pythonw -Argument $argumentos -WorkingDirectory $raiz
$inicio = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Hours 1)
$opciones = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 3) -Hidden
$usuario = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $usuario -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $nombre -Action $accion -Trigger $inicio -Settings $opciones -Principal $principal -Description 'Comprueba y renueva la publicación piloto de Sequías de GeoSur.' -Force | Out-Null
Write-Output "Programada cada hora: $nombre"
