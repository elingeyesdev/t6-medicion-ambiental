import test from 'node:test';
import assert from 'node:assert/strict';
import { avisoDeteccion, CompartirFoco } from '../../public/js/focos-calor/compartir.js';

const foco = (lon = -63.18123, lat = -17.78123) => ({ geometria: { type: 'Point', coordinates: [lon, lat] }, fechas: { observadoDesde: '2026-11-02T02:30:00Z' } });
const contexto = { municipio: 'Santa Cruz de la Sierra', provincia: 'Andrés Ibáñez', fuentes: [{ proveedor: 'NASA FIRMS' }] };
const nodos = () => ({ boton: { addEventListener() {} }, estado: {}, alternativa: { focus() { this.enfocado = true; }, select() { this.seleccionado = true; } } });

test('el mensaje conserva coordenadas del punto y fecha Bolivia incluso al cambiar de día', () => {
    const texto = avisoDeteccion(foco(), contexto);
    assert.match(texto, /Santa Cruz de la Sierra, Andrés Ibáñez/);
    assert.match(texto, /1[\/]11[\/]26, 22:30/);
    assert.match(texto, /NASA FIRMS/);
    assert.match(texto, /no confirma un incendio/);
    assert.match(texto, /Coordenadas: -17.78123, -63.18123/);
    const url = new URL(texto.split('Ubicación: ')[1].split('\n')[0]);
    assert.equal(url.searchParams.get('query'), '-17.78123,-63.18123');
    assert.equal(url.searchParams.get('api'), '1');
});

test('no inventa municipio ni fuente y rechaza una ubicación ausente o fuera del planeta', () => {
    assert.match(avisoDeteccion(foco()), /Lugar no identificado/);
    assert.match(avisoDeteccion(foco()), /Fuente: No informada/);
    for (const invalido of [null, {}, foco(null), foco(181), foco(1, 91), foco(NaN), foco('2')]) assert.throws(() => avisoDeteccion(invalido), /Ubicación no disponible/);
});

test('copiar después de cambiar de selección usa la nueva detección', async () => {
    const n = nodos(); const copias = [];
    const compartir = new CompartirFoco(n.boton, n.estado, n.alternativa, { async writeText(t) { copias.push(t); } });
    compartir.seleccionar(foco(), contexto); await compartir.copiar();
    compartir.seleccionar(foco(-60, -20), contexto); await compartir.copiar();
    assert.match(copias[0], /-17.78123, -63.18123/);
    assert.match(copias[1], /-20, -60/);
    assert.match(n.estado.textContent, /copiadas/);
    assert.equal(n.alternativa.hidden, true);
    compartir.limpiar(); await compartir.copiar();
    assert.equal(copias.length, 2);
    assert.equal(n.boton.disabled, true);
});

test('si el navegador bloquea el copiado, ofrece el texto completo para copiar a mano', async () => {
    const n = nodos();
    const compartir = new CompartirFoco(n.boton, n.estado, n.alternativa, { async writeText() { throw new Error('denegado'); } });
    compartir.seleccionar(foco(), contexto); await compartir.copiar();
    assert.equal(n.alternativa.value, avisoDeteccion(foco(), contexto));
    assert.equal(n.alternativa.hidden, false);
    assert.equal(n.alternativa.enfocado, true);
    assert.equal(n.alternativa.seleccionado, true);
    assert.match(n.estado.textContent, /Selecciona y copia/);
});

test('un copiado pendiente no anuncia ni recupera un punto que ya se cerró', async () => {
    for (const error of [false, true]) {
        const n = nodos(); let finalizar;
        const compartir = new CompartirFoco(n.boton, n.estado, n.alternativa, { writeText() { return new Promise((resolve, reject) => { finalizar = error ? reject : resolve; }); } });
        compartir.seleccionar(foco(), contexto);
        const pendiente = compartir.copiar(); compartir.limpiar(); finalizar(); await pendiente;
        assert.equal(n.estado.textContent, '');
        assert.equal(n.alternativa.hidden, true);
        assert.equal(n.alternativa.value, '');
    }
});
