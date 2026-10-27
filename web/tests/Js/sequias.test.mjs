import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { comprobarRespuesta, validarFiltros, leerEnlace, numero, categoria, ErrorConsulta } from '../../public/js/sequias/contrato.js';
import { ConsultaSequiasWeb } from '../../public/js/sequias/consulta.js';

// Fixtures congeladas: estas pruebas no consultan proveedores ni acreditan datos nuevos.
const leer = ruta => JSON.parse(readFileSync(new URL(ruta, import.meta.url)));
const catalogos = { territorios: leer('../../../compartido/territorios/datos/catalogo.json').territorios, muestras: leer('../../../compartido/sequias/muestras.json').muestras };
const filtros = { mesFinal: '2024-09', municipio: 'BO070101', muestra: 'scz-piloto-01' };
const hoy = new Date('2026-10-25T18:00:00Z');
const fixture = () => { const r = leer('../../../docs/evidencias/api02-http/consulta.json'); r.consulta.obtencion.vigenteHasta = '2099-01-01T00:00:00Z'; return r; };
const crear = pedir => new ConsultaSequiasWeb('/api/v1', () => {}, pedir);

test('el calendario requiere meses completos UTC y conserva el contexto territorial', () => {
    assert.deepEqual(validarFiltros(filtros, catalogos, hoy), filtros);
    for (const mes of ['2026-10', '2026-11', '2020-12', '2024-13', '2024-00', '2024-9', '']) assert.throws(() => validarFiltros({ mesFinal: mes }, catalogos, hoy));
    assert.throws(() => validarFiltros({ ...filtros, provincia: 'BO0702' }, catalogos, hoy));
    assert.throws(() => validarFiltros({ ...filtros, municipio: 'BO070103' }, catalogos, hoy));
    assert.throws(() => validarFiltros({ ...filtros, muestra: 'no-existe' }, catalogos, hoy));
});

test('los enlaces de consulta y detalle rechazan campos repetidos, vacíos o ajenos', () => {
    assert.equal(leerEnlace('', catalogos, hoy), null);
    assert.deepEqual(leerEnlace('?mesFinal=2024-09&municipio=BO070101&seleccion=scz-piloto-01', catalogos, hoy), { filtros: { mesFinal: '2024-09', municipio: 'BO070101' }, seleccion: 'scz-piloto-01' });
    for (const s of ['?mesFinal=2024-09&mesFinal=2024-09', '?mesFinal=2024-09&modo=simulado', '?mesFinal=2024-09&provincia=', '?mesFinal=2024-09&seleccion=desconocida']) assert.throws(() => leerEnlace(s, catalogos, hoy));
});

test('el visor conserva el SPI del servidor y no convierte null en cero o normal', () => {
    const r = fixture(); r.datos[0].spi.valor = -0.99999999; r.datos[0].spi.categoria = 'normal';
    assert.equal(comprobarRespuesta(r, filtros).datos[0].spi.valor, -0.99999999);
    assert.equal(categoria(r.datos[0]), 'Normal');
    assert.equal(numero(null), 'Sin dato'); assert.equal(numero(0), '0');
    r.datos[0].spi = { valor: null, categoria: null, motivo: 'PERIODO_INCOMPLETO' };
    r.resumen.muestrasConIndice = 0; r.resumen.muestrasSinIndice = 1; r.resumen.spiMinimo = null;
    assert.equal(comprobarRespuesta(r, filtros).datos[0].spi.valor, null);
    assert.equal(categoria(r.datos[0]), 'Sin índice');
    r.datos[0].spi.categoria = 'normal'; assert.throws(() => comprobarRespuesta(r, filtros));
});

test('no se aceptan polígonos coloreados, otra consulta, puntos inválidos o cobertura falsa', () => {
    for (const cambiar of [
        r => { r.representacionMapa.rellenarPoligonos = true; },
        r => { r.consulta.mesFinal = '2024-08'; },
        r => { r.datos[0].coordenadaCelda = [200, -17]; },
        r => { r.resumen.muestrasConIndice = 0; },
        r => { r.cobertura.muestrasSeleccionadas = 56; },
        r => { r.cobertura.superficieEvaluadaKm2 = 100; },
        r => { r.datos[0].fuentesRef = ['inventada']; },
    ]) { const r = fixture(); cambiar(r); assert.throws(() => comprobarRespuesta(r, filtros)); }
    const parcial = leer('../../../docs/evidencias/api02-http/departamento-parcial.json'); parcial.consulta.obtencion.vigenteHasta = '2099-01-01T00:00:00Z';
    assert.equal(comprobarRespuesta(parcial, { mesFinal: '2024-09' }).cobertura.muestrasSinPreparar.length, 55);
    parcial.consulta.municipio = 'BO070101'; assert.throws(() => comprobarRespuesta(parcial, { mesFinal: '2024-09' }));
});

test('una publicación vencida se rechaza y un resultado abierto se marca como anterior', async () => {
    const r = fixture(); r.consulta.obtencion.vigenteHasta = '2020-01-01T00:00:00Z';
    assert.throws(() => comprobarRespuesta(r, filtros), e => e.codigo === 'PERIODO_NO_DISPONIBLE');
    const c = crear(async () => fixture()); await c.consultar(filtros);
    c.estado.actual.respuesta.consulta.obtencion.vigenteHasta = '2020-01-01T00:00:00Z'; c.verificarVigencia();
    assert.equal(c.estado.fase, 'anterior'); assert.equal(c.estado.error.codigo, 'PERIODO_NO_DISPONIBLE');
});

test('dos consultas fuera de orden no mezclan índices ni contexto', async () => {
    const pendientes = []; const c = crear(() => new Promise(resolve => pendientes.push(resolve)));
    const primera = c.consultar(filtros); const segunda = c.consultar(filtros);
    const nueva = fixture(); nueva.datos[0].spi.valor = -.5; pendientes[1](nueva); await segunda;
    pendientes[0](fixture()); await primera;
    assert.equal(c.estado.actual.respuesta.datos[0].spi.valor, -.5);
});

test('editar o volver sin filtros cancela la petición pendiente aunque el transporte no cancele', async () => {
    let resolver; const c = crear(() => new Promise(resolve => { resolver = resolve; }));
    const pendiente = c.consultar(filtros); c.editar(); resolver(fixture()); await pendiente;
    assert.equal(c.estado.actual, null); assert.equal(c.estado.borrador, true);
    const otra = c.consultar(filtros); c.limpiar(); resolver(fixture()); await otra;
    assert.equal(c.estado.actual, null); assert.equal(c.estado.fase, 'inicial');
});

test('un fallo conserva el resultado anterior y reintenta los filtros fallidos', async () => {
    const consultas = []; let fallar = false;
    const c = crear(async url => { consultas.push(url); if (fallar) throw new ErrorConsulta('No hay muestras', 'MUESTRAS_NO_DISPONIBLES'); return fixture(); });
    await c.consultar(filtros); const anterior = c.estado.actual; fallar = true;
    const nuevos = { mesFinal: '2024-08' }; await c.consultar(nuevos);
    assert.equal(c.estado.actual, anterior); assert.equal(c.estado.fase, 'error');
    await c.reintentar(); assert.ok(consultas.at(-1).endsWith('mesFinal=2024-08'));
    c.restaurar(); assert.equal(c.estado.fase, 'listo'); assert.equal(c.estado.pendiente, null);
});
