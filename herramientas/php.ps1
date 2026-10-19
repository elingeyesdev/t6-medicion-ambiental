$ArgumentosPhp = $args

$ErrorActionPreference = 'Stop'
$phpBin = (Get-Command php -ErrorAction Stop).Source
$iniActual = & $phpBin -r 'echo php_ini_loaded_file() ?: "";'
$phpOpciones = @()
$phprcAnterior = $env:PHPRC

try {
    if (-not $iniActual) {
        # PHP portable sin php.ini: configuración del proyecto, sin cambiar Laragon.
        $raizProyecto = Split-Path -Parent $PSScriptRoot
        $directorioExtensiones = Join-Path (Split-Path -Parent $phpBin) 'ext'
        $directorioTemporal = Join-Path $raizProyecto 'tmp/php'
        New-Item -ItemType Directory -Force -Path $directorioTemporal | Out-Null
        $iniProyecto = Join-Path $directorioTemporal 'php.ini'
        $lineasIni = @('memory_limit=512M', 'date.timezone=UTC', ('extension_dir="{0}"' -f ($directorioExtensiones -replace '\\', '/')))
        foreach ($extensionPhp in @('curl', 'fileinfo', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3')) {
            if (-not (Test-Path (Join-Path $directorioExtensiones "php_$extensionPhp.dll"))) {
                throw "Falta la extensión $extensionPhp. Instale PHP con las extensiones indicadas en web/README.md."
            }
            $lineasIni += "extension=$extensionPhp"
        }
        $certificados = Join-Path $raizProyecto 'tmp/runtime/cacert.pem'
        if (Test-Path -LiteralPath $certificados) {
            $rutaCertificados = $certificados -replace '\\', '/'
            $lineasIni += ('curl.cainfo="{0}"' -f $rutaCertificados)
            $lineasIni += ('openssl.cafile="{0}"' -f $rutaCertificados)
        }
        [System.IO.File]::WriteAllLines($iniProyecto, $lineasIni, [System.Text.UTF8Encoding]::new($false))
        $env:PHPRC = $iniProyecto
        $phpOpciones = @('-c', $iniProyecto)
    }
    & $phpBin @phpOpciones @ArgumentosPhp
    $codigoPhp = $LASTEXITCODE
} finally {
    $env:PHPRC = $phprcAnterior
}
exit $codigoPhp
