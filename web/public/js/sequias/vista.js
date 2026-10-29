import { numero, categoria, coordenada } from './contrato.js';

const elemento = (tag, texto, clase) => {
    const e = document.createElement(tag); if (texto !== undefined) e.textContent = texto; if (clase) e.className = clase; return e;
};
const poner = (id, texto) => { document.getElementById(id).textContent = texto; };
const dia = valor => valor.slice(0, 10).split('-').reverse().join('/');

export class VistaSequias {
    constructor(catalogos, alSeleccionar) {
        this.catalogos = catalogos; this.alSeleccionar = alSeleccionar;
        this.territorios = new Map(catalogos.territorios.map(t => [t.identificador, t.nombre]));
        this.muestras = new Map(catalogos.muestras.map(m => [m.identificador, m]));
    }
    nombre(id) { return this.territorios.get(id) ?? id ?? 'No determinado'; }
    etiquetaMuestra(id) { const m = this.muestras.get(id); return m ? this.nombre(m.municipio) : 'Punto de consulta'; }
    mostrar(r) {
        this.respuesta = r;
        const c = r.consulta, s = r.resumen, cobertura = r.cobertura;
        poner('sq-titulo-resultados', this.nombre(c.municipio ?? c.provincia ?? 'BO07'));
        poner('sq-periodo', `${dia(c.desde)} al ${dia(c.hastaIncluido)}`);
        poner('sq-total', numero(s.totalMuestras)); poner('sq-seleccionadas', `de ${numero(cobertura.muestrasSeleccionadas)} puntos elegidos`);
        poner('sq-sin-indice', numero(s.muestrasSinIndice)); poner('sq-pendientes', numero(cobertura.muestrasSinPreparar.length));
        poner('sq-cobertura', `Hay datos de ${cobertura.municipiosConDatos} de los ${cobertura.municipiosEnFiltro} municipios elegidos.${cobertura.estadoDatos === 'parcial' ? ' La consulta no cubre todo el territorio.' : ''}`);
        const lista = document.getElementById('sq-lista'); lista.replaceChildren();
        poner('sq-conteo', `${r.datos.length} en el conjunto`);
        for (const d of r.datos) {
            const boton = elemento('button', undefined, 'sq-fila'); boton.type = 'button'; boton.dataset.muestra = d.idMuestra; boton.setAttribute('aria-pressed', 'false');
            boton.append(elemento('strong', this.nombre(d.territorioContexto)), elemento('span', categoria(d), 'sq-categoria'), elemento('span', `Lluvia acumulada: ${numero(d.precipitacionAcumuladaMm)} mm`));
            boton.addEventListener('click', () => this.alSeleccionar(d.idMuestra, boton)); lista.append(boton);
        }
        if (!r.datos.length) lista.append(elemento('p', 'No hay datos para el lugar y las fechas elegidos.', 'sq-ayuda'));
        const pendientes = document.getElementById('sq-nombres-pendientes'); pendientes.replaceChildren();
        for (const id of cobertura.muestrasSinPreparar) pendientes.append(elemento('li', this.etiquetaMuestra(id)));
        poner('sq-titulo-pendientes', `${cobertura.muestrasSinPreparar.length} puntos sin datos para estas fechas`);
        document.getElementById('sq-listado-pendientes').hidden = !cobertura.muestrasSinPreparar.length;
        const fuentes = document.getElementById('sq-fuentes'); fuentes.replaceChildren();
        for (const fuente of r.fuentes) {
            fuentes.append(elemento('p', `Fuente: ${fuente.proveedor} · ${fuente.producto}. ${fuente.atribucion}`));
        }
        const advertencias = document.getElementById('sq-advertencias'); advertencias.replaceChildren();
        if (cobertura.estadoDatos === 'parcial') advertencias.append(elemento('li', 'Faltan datos en parte de la zona consultada.'));
        this.cerrar();
    }
    seleccionar(id) {
        const d = this.respuesta?.datos.find(m => m.idMuestra === id);
        if (!d) return false;
        const c = this.respuesta.consulta;
        const campos = [
            ['Municipio consultado', this.nombre(d.territorioContexto)], ['Condición del período', categoria(d)],
            ['Lluvia de los tres meses', `${numero(d.precipitacionAcumuladaMm)} mm`], ['Período', `${dia(c.desde)} al ${dia(c.hastaIncluido)}`],
            ['Ubicación del punto', coordenada(d.coordenadaCelda)], ['Municipio del punto', this.nombre(d.municipioDelCentroCelda)],
        ];
        const lista = document.getElementById('sq-campos-detalle'); lista.replaceChildren();
        for (const [nombre, valor] of campos) { const div = elemento('div'); div.append(elemento('dt', nombre), elemento('dd', valor)); lista.append(div); }
        const motivo = document.getElementById('sq-motivo-detalle'); motivo.hidden = d.spi.valor !== null;
        motivo.textContent = d.spi.valor === null ? 'No se pudo obtener un resultado para este punto. No significa que las condiciones sean normales.' : '';
        document.getElementById('sq-detalle').hidden = false;
        document.querySelectorAll('.sq-fila').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.muestra === id)));
        return true;
    }
    cerrar() {
        document.getElementById('sq-detalle').hidden = true;
        document.querySelectorAll('.sq-fila').forEach(b => b.setAttribute('aria-pressed', 'false'));
    }
}
