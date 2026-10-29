param(
    [string]$PgBin = 'C:/Program Files/PostgreSQL/16/bin',
    [ValidateRange(1024,65535)][int]$Puerto = 55432
)
$ErrorActionPreference = 'Stop'
$raizPgPruebas = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$phpPgPruebas = (Get-Command php -ErrorAction Stop).Source
foreach ($binarioPgPruebas in @('initdb.exe','pg_ctl.exe','createdb.exe','pg_isready.exe')) {
    if (!(Test-Path -LiteralPath (Join-Path $PgBin $binarioPgPruebas))) { throw "Falta $binarioPgPruebas en la instalación indicada." }
}
if (Get-NetTCPConnection -State Listen -LocalPort $Puerto -ErrorAction SilentlyContinue) { throw 'El puerto de pruebas está ocupado.' }
$carpetaPgPruebas = Join-Path $raizPgPruebas ('tmp/postgresql-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $carpetaPgPruebas | Out-Null
$datosPgPruebas = Join-Path $carpetaPgPruebas 'data'
$clavePgPruebas = Join-Path $carpetaPgPruebas 'clave.txt'
[IO.File]::WriteAllText($clavePgPruebas,[Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)),[Text.UTF8Encoding]::new($false))
$anterioresPgPruebas = @{}
foreach ($variablePgPruebas in @('DB_PASSWORD','DB_PORT','DB_USERNAME','GEOSUR_PRUEBA_POSTGRES','PHPRC','VIEW_COMPILED_PATH','PGPASSWORD')) {
    $anterioresPgPruebas[$variablePgPruebas] = [Environment]::GetEnvironmentVariable($variablePgPruebas,'Process')
}
$arrancadoPgPruebas = $false
try {
    & (Join-Path $PgBin 'initdb.exe') -D $datosPgPruebas -U geosur_pruebas --encoding=UTF8 --locale=C --auth=scram-sha-256 "--pwfile=$clavePgPruebas" *> (Join-Path $carpetaPgPruebas 'init.log')
    if ($LASTEXITCODE -ne 0) { throw 'Falló la preparación del clúster de pruebas; revisa su init.log.' }
    $argsPgPruebas = '-D "'+$datosPgPruebas+'" -l "'+(Join-Path $carpetaPgPruebas 'servidor.log')+'" -o "-h 127.0.0.1 -p '+$Puerto+' -F" start -w -t 20'
    $lanzadorPgPruebas = Start-Process -FilePath (Join-Path $PgBin 'pg_ctl.exe') -ArgumentList $argsPgPruebas -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $carpetaPgPruebas 'start.log') -RedirectStandardError (Join-Path $carpetaPgPruebas 'start-error.log')
    # Espera solo pg_ctl; Start-Process -Wait también esperaría el servidor permanente.
    if (!$lanzadorPgPruebas.WaitForExit(25000) -or $lanzadorPgPruebas.ExitCode -ne 0) { throw 'No arrancó el clúster aislado.' }
    $arrancadoPgPruebas = $true
    $env:DB_PASSWORD = [IO.File]::ReadAllText($clavePgPruebas)
    $env:PGPASSWORD = $env:DB_PASSWORD
    $env:DB_USERNAME = 'geosur_pruebas'
    $env:DB_PORT = [string]$Puerto
    $env:GEOSUR_PRUEBA_POSTGRES = '1'
    & (Join-Path $PgBin 'createdb.exe') -h 127.0.0.1 -p $Puerto -U geosur_pruebas geosur_m3_tests
    if ($LASTEXITCODE -ne 0) { throw 'No se creó la base de pruebas.' }
    & (Join-Path $raizPgPruebas 'herramientas/php.ps1') -r 'echo "Entorno PHP preparado";'
    $iniBasePgPruebas = Join-Path $raizPgPruebas 'tmp/php/php.ini'
    $iniDestinoPgPruebas = Join-Path $carpetaPgPruebas 'php.ini'
    $iniCargadoPgPruebas = & $phpPgPruebas -r 'echo php_ini_loaded_file() ?: "";'
    if ($iniCargadoPgPruebas) { $iniBasePgPruebas = $iniCargadoPgPruebas }
    $iniContenidoPgPruebas = if (Test-Path -LiteralPath $iniBasePgPruebas) { Get-Content -Raw $iniBasePgPruebas } else { '' }
    $extensionesPgPruebas = (Join-Path (Split-Path -Parent $phpPgPruebas) 'ext') -replace '\\','/'
    [IO.File]::WriteAllText($iniDestinoPgPruebas, $iniContenidoPgPruebas+"`nextension_dir=`"$extensionesPgPruebas`"`nextension=pdo_pgsql`n", [Text.UTF8Encoding]::new($false))
    $env:PHPRC = $iniDestinoPgPruebas
    $env:VIEW_COMPILED_PATH = Join-Path $raizPgPruebas 'web/storage/framework/views-pruebas/pgsql'
    & $phpPgPruebas -c $iniDestinoPgPruebas (Join-Path $raizPgPruebas 'web/vendor/bin/phpunit') -c (Join-Path $raizPgPruebas 'web/phpunit.pgsql.xml') --colors=never
    if ($LASTEXITCODE -ne 0) { throw 'Hay pruebas PostgreSQL fallidas.' }
} finally {
    $detenidoPgPruebas = $true
    if ($arrancadoPgPruebas -or (Test-Path -LiteralPath (Join-Path $datosPgPruebas 'postmaster.pid'))) {
        & (Join-Path $PgBin 'pg_ctl.exe') -D $datosPgPruebas stop -m fast -w -t 20 *> (Join-Path $carpetaPgPruebas 'stop.log')
        $detenidoPgPruebas = $LASTEXITCODE -eq 0
    }
    foreach ($variablePgPruebas in $anterioresPgPruebas.Keys) { [Environment]::SetEnvironmentVariable($variablePgPruebas,$anterioresPgPruebas[$variablePgPruebas],'Process') }
    if (!$detenidoPgPruebas) { throw 'No se confirmó la parada del clúster de pruebas; revisa su stop.log.' }
    Write-Output 'El clúster efímero quedó detenido. Sus archivos y registros quedan en tmp; no se tocó el servicio PostgreSQL del equipo ni la base de la demo.'
}
