<?php

declare(strict_types=1);

use App\Services\Territorios\FiltroTerritorialInvalido;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

function escapar(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errorSeleccion = null;
$seleccion = null;
$provincia = null;
$municipio = null;
try {
    $catalogo = require __DIR__.'/herramientas/cargar_catalogo.php';
    $fuentes = json_decode(file_get_contents(__DIR__.'/fuentes/fuentes.json'), true, 512, JSON_THROW_ON_ERROR);
    $provincias = $catalogo->listar('provincia');
    $municipios = $catalogo->listar('municipio');
    foreach (['provincia', 'municipio'] as $campo) {
        if (isset($_GET[$campo]) && ! is_string($_GET[$campo])) {
            throw new FiltroTerritorialInvalido('FILTRO_INVALIDO', 'Seleccione un solo identificador por campo.');
        }
    }
    $provincia = ($_GET['provincia'] ?? '') === '' ? null : $_GET['provincia'];
    $municipio = ($_GET['municipio'] ?? '') === '' ? null : $_GET['municipio'];
    $seleccion = $catalogo->resolverFiltros($provincia, $municipio);
} catch (FiltroTerritorialInvalido $error) {
    http_response_code(422);
    $errorSeleccion = $error;
} catch (Throwable) {
    http_response_code(500);
    exit('No se puede abrir el catálogo local. Ejecute compartido/territorios/comprobar.php para diagnosticarlo.');
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Revisión territorial · GeoCruz</title><link rel="stylesheet" href="revision.css"></head>
<body>
<main>
    <header><a class="marca" href="revisar_catalogo.php">GeoCruz <span>Catálogo territorial</span></a><span class="pendiente">Propuesta · revisión pendiente</span></header>
    <p class="etiqueta">HERRAMIENTA LOCAL DE REVISIÓN · ISSUE #16</p>
    <h1>Santa Cruz, de departamento a municipio.</h1>
    <p class="introduccion">Revisa los identificadores, las relaciones territoriales y las denominaciones de la propuesta compartida para web y móvil.</p>
    <section class="conteos" aria-label="Cobertura del catálogo">
        <div><strong><?= count($catalogo->listar('departamento')) ?></strong><span>departamento</span></div>
        <div><strong><?= count($provincias) ?></strong><span>provincias</span></div>
        <div><strong><?= count($municipios) ?></strong><span>unidades de nivel municipal</span></div>
        <div><strong><?= count($catalogo->listar()) ?></strong><span>geometrías vinculadas</span></div>
    </section>
    <section class="panel" aria-labelledby="titulo-seleccion">
        <h2 id="titulo-seleccion">Comprueba una selección</h2>
        <p>Sin filtros se conserva Santa Cruz. Al elegir solo un municipio se obtiene su provincia desde el catálogo.</p>
        <form method="get" action="revisar_catalogo.php">
            <label for="provincia">Provincia<select id="provincia" name="provincia"><option value="">Todas las provincias</option>
                <?php foreach ($provincias as $p) { ?><option value="<?= escapar($p['identificador']) ?>" <?= $provincia === $p['identificador'] ? 'selected' : '' ?>><?= escapar($p['nombre'].' · '.$p['identificador']) ?></option><?php } ?>
            </select></label>
            <label for="municipio">Municipio / autonomía<select id="municipio" name="municipio"><option value="">Todos los municipios</option>
                <?php foreach ($provincias as $p) { ?><optgroup label="<?= escapar($p['nombre']) ?>"><?php foreach ($catalogo->listar('municipio', $p['identificador']) as $m) { ?><option value="<?= escapar($m['identificador']) ?>" <?= $municipio === $m['identificador'] ? 'selected' : '' ?>><?= escapar($m['nombre'].' · '.$m['identificador']) ?></option><?php } ?></optgroup><?php } ?>
            </select></label>
            <button type="submit">Comprobar selección</button><a class="limpiar" href="revisar_catalogo.php">Limpiar</a>
        </form>
        <p class="ayuda">Aquí puedes combinar cualquier municipio y provincia para probar el rechazo de relaciones incompatibles. El selector del visor ambiental se implementará en #19.</p>
        <?php if ($errorSeleccion !== null) { ?>
            <div class="resultado error" role="alert"><strong>Selección incompatible o inválida</strong><p><?= escapar($errorSeleccion->getMessage()) ?></p><code><?= escapar($errorSeleccion->codigo) ?></code></div>
        <?php } elseif ($seleccion !== null) { ?>
            <div class="resultado correcto" role="status"><strong>Selección válida</strong><p>Ámbito efectivo: <b><?= escapar($seleccion['territorioEfectivo']['nombre']) ?></b> · <code><?= escapar($seleccion['territorioEfectivo']['identificador']) ?></code></p>
                <p>Provincia: <?= $seleccion['provincia'] === null ? 'Sin filtro provincial' : escapar($seleccion['provincia']['nombre'].' · '.$seleccion['provincia']['identificador']) ?><?= $municipio !== null && $provincia === null ? ' (obtenida desde el municipio)' : '' ?></p>
            </div>
        <?php } ?>
    </section>
    <section class="fuentes" aria-labelledby="titulo-fuentes"><h2 id="titulo-fuentes">Fuente y versión visibles</h2><p>Límites: <b>Ministerio de Desarrollo Rural y Tierras · OCHA/HDX COD-AB v02</b>. Coordenadas de origen de <b>2013</b>; revisión del publicador del <b>30/10/2025</b>. Licencia <a href="<?= escapar($fuentes['geometrias']['urlLicencia']) ?>">CC BY 3.0 IGO</a>.</p><p>Los nombres municipales se contrastaron con el INE, Censo 2024. Se conservan las denominaciones de autonomía de Charagua y Gutiérrez. Los códigos mostrados son <b>PCODE de OCHA/HDX</b>, no códigos oficiales INE.</p><p>Esta comprobación no certifica un deslinde legal vigente ni integra consultas ambientales. La adopción de la propuesta requiere revisión del equipo.</p><a href="datos/catalogo.json">Ver catálogo JSON</a> · <a href="fuentes/fuentes.json">Ver procedencia</a> · <a href="comprobar.php">Ejecutar comprobaciones locales</a></section>
    <section class="panel" aria-labelledby="titulo-provincia"><h2 id="titulo-provincia">Las 15 provincias y sus unidades municipales</h2><p>Abre una provincia para revisar todos sus nombres y códigos.</p>
        <?php foreach ($provincias as $p) {
            $hijos = $catalogo->listar('municipio', $p['identificador']); ?>
            <details><summary><?= escapar($p['nombre']) ?> <code><?= escapar($p['identificador']) ?></code><span><?= count($hijos) ?> unidades</span></summary><ul><?php foreach ($hijos as $m) { ?><li><code><?= escapar($m['identificador']) ?></code> <?= escapar($m['nombre']) ?></li><?php } ?></ul></details>
        <?php } ?>
    </section>
    <footer>Versión <?= escapar($catalogo->metadatos()['version']) ?> · Sin conexión a proveedores ambientales.</footer>
</main>
</body></html>
