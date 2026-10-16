$ErrorActionPreference = 'Stop'
$directorioHerramientas = Join-Path $PSScriptRoot 'tmp/runtime'
$ejecutorPhp = Join-Path $PSScriptRoot 'herramientas/php.ps1'
New-Item -ItemType Directory -Force -Path $directorioHerramientas | Out-Null

$composerPhar = Join-Path $directorioHerramientas 'composer.phar'
$composerHash = Join-Path $directorioHerramientas 'composer.phar.sha256sum'
if (-not (Test-Path -LiteralPath $composerPhar) -or -not (Test-Path -LiteralPath $composerHash)) {
    Write-Host 'Descargando Composer desde getcomposer.org...'
    Invoke-WebRequest -UseBasicParsing -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $composerPhar
    Invoke-WebRequest -UseBasicParsing -Uri 'https://getcomposer.org/download/latest-stable/composer.phar.sha256sum' -OutFile $composerHash
}
$hashEsperado = (Get-Content -Raw -LiteralPath $composerHash).Trim().Split(' ')[0]
$hashCalculado = (Get-FileHash -LiteralPath $composerPhar -Algorithm SHA256).Hash
if ($hashEsperado -ne $hashCalculado) {
    throw 'La verificación SHA256 de Composer falló. No se ejecutó el archivo descargado.'
}

$archivoCertificados = Join-Path $directorioHerramientas 'cacert.pem'
if (-not (Test-Path -LiteralPath $archivoCertificados)) {
    Invoke-WebRequest -UseBasicParsing -Uri 'https://curl.se/ca/cacert.pem' -OutFile $archivoCertificados
}

Push-Location $PSScriptRoot
try {
    & $ejecutorPhp $composerPhar install --working-dir=web --prefer-dist --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Composer no pudo instalar las dependencias. Revise PHP y sus extensiones.' }
    & $ejecutorPhp $composerPhar run setup --working-dir=web
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la aplicación.' }
    Write-Host 'GeoCruz preparado. Ejecute .\iniciar.ps1 desde la carpeta oficial.' -ForegroundColor Green
} finally {
    Pop-Location
}
