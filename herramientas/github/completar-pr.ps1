[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidateRange(1, 2147483647)][int]$Numero,
    [switch]$VistaPrevia
)

$ErrorActionPreference = 'Stop'
$repositorio = 'elingeyesdev/t6-medicion-ambiental'
$salida = & gh pr view $Numero --repo $repositorio --json body,headRefName,state,url
if ($LASTEXITCODE -ne 0) { throw 'No se pudo leer el PR.' }
$pr = $salida | ConvertFrom-Json
if ($pr.state -ne 'OPEN') { throw 'Solo se completa la descripción de un PR abierto.' }

$inicio = '<!-- borrar-rama-al-fusionar -->'
$fin = '<!-- /borrar-rama-al-fusionar -->'
$comando = "gh pr merge $Numero --repo $repositorio --merge"
if ($pr.headRefName -in @('main', 'develop')) {
    $aclaracion = 'La rama de origen es permanente: se conserva después de fusionar. Main necesita la aprobación expresa del ingeniero.'
} else {
    $comando += ' --delete-branch'
    $aclaracion = 'Así se fusiona y se elimina la rama de esta tarea. Main y develop se conservan.'
}
$seccion = @"
$inicio
## Al aceptar este PR

Cuando esté aprobado y con las pruebas pasadas, usar:

``````powershell
$comando
``````

$aclaracion El comando se ejecuta al fusionar; escribirlo aquí no activa el borrado automático del repositorio.
$fin
"@

$cuerpo = [string]$pr.body
$posicion = $cuerpo.IndexOf($inicio, [StringComparison]::Ordinal)
if ($posicion -ge 0) {
    $final = $cuerpo.IndexOf($fin, $posicion, [StringComparison]::Ordinal)
    if ($final -lt 0) { throw 'La sección anterior está incompleta; revisar la descripción antes de cambiarla.' }
    $nuevo = $cuerpo.Substring(0, $posicion) + $seccion + $cuerpo.Substring($final + $fin.Length)
} else {
    $nuevo = $cuerpo.TrimEnd() + "`n`n" + $seccion + "`n"
}
if ($VistaPrevia) { Write-Output $seccion; return }
if ($nuevo -eq $cuerpo) { Write-Output "PR #${Numero}: el comando ya está completo."; return }

$archivoTemporal = [IO.Path]::GetTempFileName()
try {
    [IO.File]::WriteAllText($archivoTemporal, $nuevo, [Text.UTF8Encoding]::new($false))
    & gh pr edit $Numero --repo $repositorio --body-file $archivoTemporal
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo guardar la descripción del PR.' }
} finally {
    Remove-Item -LiteralPath $archivoTemporal -ErrorAction SilentlyContinue
}
