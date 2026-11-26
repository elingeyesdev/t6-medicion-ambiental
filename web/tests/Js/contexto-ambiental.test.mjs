import test from 'node:test';
import assert from 'node:assert/strict';
import { recordarTerritorio, territorioAnterior } from '../../public/js/panel/contexto-ambiental.js';
import { procedenciaFoco } from '../../public/js/focos-calor/elementos.js';

const lugares = [
    { tipo: 'provincia', identificador: 'BO0701' },
    { tipo: 'provincia', identificador: 'BO0710' },
    { tipo: 'municipio', identificador: 'BO071005', padre: 'BO0710' },
];

test('el contexto de la pestaña conserva solo los territorios compatibles', () => {
    const original = Object.getOwnPropertyDescriptor(globalThis, 'sessionStorage');
    const memoria = new Map();
    Object.defineProperty(globalThis, 'sessionStorage', { configurable: true, value: {
        getItem: clave => memoria.get(clave) ?? null,
        setItem: (clave, valor) => memoria.set(clave, valor),
    } });
    try {
        assert.deepEqual(territorioAnterior(lugares), {});
        recordarTerritorio({ municipio: 'BO071005', mesFinal: '2026-10' });
        assert.deepEqual(territorioAnterior(lugares), { provincia: 'BO0710', municipio: 'BO071005' });
        recordarTerritorio({ provincia: 'BO0701', municipio: 'BO071005' });
        assert.deepEqual(territorioAnterior(lugares), { provincia: 'BO0701', municipio: undefined });
        memoria.set([...memoria.keys()][0], '{');
        assert.deepEqual(territorioAnterior(lugares), {});
    } finally {
        if (original) Object.defineProperty(globalThis, 'sessionStorage', original);
        else delete globalThis.sessionStorage;
    }
});

test('la ficha usa la fuente de la detección y distingue cero de un dato ausente', () => {
    const fuentes = [
        { identificador: 'nrt', proveedor: 'NASA FIRMS', producto: 'VIIRS_NOAA20_NRT' },
        { identificador: 'sp', proveedor: 'NASA FIRMS', producto: 'VIIRS_SNPP_SP' },
    ];
    const foco = {
        identificador: 'deteccion-controlada', naturaleza: 'observacion', fuentesRef: ['sp'],
        atributos: { potenciaRadiativa: 0, unidadPotenciaRadiativa: 'MW' },
    };
    const ficha = Object.fromEntries(procedenciaFoco(foco, fuentes));
    assert.equal(ficha.Producto, 'VIIRS_SNPP_SP');
    assert.equal(ficha['Tipo de dato'], 'Observación satelital');
    assert.match(ficha['Potencia radiativa'], /^0\s+MW$/);
    foco.atributos.potenciaRadiativa = null;
    foco.fuentesRef = [];
    const faltante = Object.fromEntries(procedenciaFoco(foco, fuentes));
    assert.equal(faltante.Fuente, 'No informado');
    assert.equal(faltante['Potencia radiativa'], 'No informada');
});
