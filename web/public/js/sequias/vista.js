import { numero, categoria, motivos, fecha, coordenada } from './contrato.js';

const elemento = (tag, texto, clase) => {
    const e = document.createElement(tag); if (texto !== undefined) e.textContent = texto; if (clase) e.className = clase; return e;
};
const poner = (id, texto) => { document.getElementById(id).textContent = texto; };

export class VistaSequias {
    constructor(catalogos, alSeleccionar) {
        this.catalogos = catalogos; this.alSeleccionar = alSeleccionar;
        this.territorios = new Map(catalogos.territorios.map(t => [t.identificador, t.nombre]));
        this.muestras = new Map(catalogos.muestras.map(m => [m.identificador, m]));
    }
    nombre(id) { return this.territorios.get(id) ?? id ?? 'No determinado'; }
    etiquetaMuestra(id) { const m = this.muestras.get(id); return m ? `${this.nombre(m.municipio)} · ${id}` : id; }
    mostrar(r) {
        this.respuesta = r;
        const c = r.consulta, s = r.resumen, cobertura = r.cobertura;
        poner('sq-titulo-resultados', this.nombre(c.municipio ?? c.provincia ?? 'BO07'));
        poner('sq-periodo', `${c.desde} a ${c.hastaIncluido} · días incluidos · UTC. Referencia ${c.referencia.desde} a ${c.referencia.hastaIncluido}.`);
        poner('sq-obtencion', `Publicación preparada · fuente recuperada ${fecha(c.obtencion.obtenidoEn)}. Vigencia técnica hasta ${fecha(c.obtencion.vigenteHasta)}; no indica condiciones actuales.`);
        poner('sq-total', numero(s.totalMuestras)); poner('sq-seleccionadas', `de ${numero(cobertura.muestrasSeleccionadas)} muestras seleccionadas`);
        poner('sq-minimo', numero(s.spiMinimo)); poner('sq-sin-indice', numero(s.muestrasSinIndice)); poner('sq-pendientes', numero(cobertura.muestrasSinPreparar.length));
        poner('sq-cobertura', `${cobertura.estadoDatos === 'parcial' ? 'Cobertura parcial · ' : ''}${cobertura.municipiosConDatos}/${cobertura.municipiosEnFiltro} municipios · ${cobertura.celdasUnicas} celdas publicadas.`);
        const lista = document.getElementById('sq-lista'); lista.replaceChildren();
        poner('sq-conteo', `${r.datos.length} en el conjunto`);
        for (const d of r.datos) {
            const boton = elemento('button', undefined, 'sq-fila'); boton.type = 'button'; boton.dataset.muestra = d.idMuestra; boton.setAttribute('aria-pressed', 'false');
            boton.append(elemento('strong', this.nombre(d.territorioContexto)), elemento('span', `SPI-3 ${numero(d.spi.valor)} · ${categoria(d)}`, 'sq-categoria'), elemento('span', `Precipitación acumulada: ${numero(d.precipitacionAcumuladaMm)} mm`), elemento('span', d.idMuestra));
            boton.addEventListener('click', () => this.alSeleccionar(d.idMuestra, boton)); lista.append(boton);
        }
        if (!r.datos.length) lista.append(elemento('p', 'Sin resultados para esta consulta. No se asignan índices a las muestras sin publicar.', 'sq-ayuda'));
        const pendientes = document.getElementById('sq-nombres-pendientes'); pendientes.replaceChildren();
        for (const id of cobertura.muestrasSinPreparar) pendientes.append(elemento('li', this.etiquetaMuestra(id)));
        poner('sq-titulo-pendientes', `${cobertura.muestrasSinPreparar.length} muestras sin preparar`);
        document.getElementById('sq-listado-pendientes').hidden = !cobertura.muestrasSinPreparar.length;
        const fuentes = document.getElementById('sq-fuentes'); fuentes.replaceChildren();
        for (const fuente of r.fuentes) {
            fuentes.append(elemento('p', `${fuente.proveedor} · ${fuente.producto} · ${fuente.naturaleza}. Precipitación en ${fuente.unidad}; resolución de celda ${numero(fuente.resolucionGrados)}°. ${fuente.atribucion}`));
            fuentes.append(elemento('p', `Fecha de actualización de la serie: ${fuente.fechaActualizacion ? fecha(fuente.fechaActualizacion) : 'no informada por el proveedor'}. Recuperación y actualización no son equivalentes.`));
            for (const captura of fuente.capturas ?? []) {
                const bloque = elemento('details'); bloque.append(elemento('summary', `Captura ${captura.periodo.desde} a ${captura.periodo.hastaIncluido}`), elemento('p', `Recuperada ${fecha(captura.obtenidoEn)}. Muestra de origen ${captura.muestraOrigen}. SHA-256 ${captura.sha256}.`));
                fuentes.append(bloque);
            }
        }
        const advertencias = document.getElementById('sq-advertencias'); advertencias.replaceChildren();
        for (const texto of r.advertencias) advertencias.append(elemento('li', texto));
        this.cerrar();
    }
    seleccionar(id) {
        const d = this.respuesta?.datos.find(m => m.idMuestra === id);
        if (!d) return false;
        const c = this.respuesta.consulta;
        const campos = [
            ['Muestra', d.idMuestra], ['Municipio solicitado', this.nombre(d.territorioContexto)],
            ['SPI-3 · adimensional', `${numero(d.spi.valor, 10)} · ${categoria(d)}`], ['Precipitación de los tres meses', `${numero(d.precipitacionAcumuladaMm)} mm`],
            ['Período calculado', `${c.desde} a ${c.hastaIncluido} · UTC · días incluidos`], ['Referencia', `${c.referencia.desde} a ${c.referencia.hastaIncluido} · ${d.spi.aniosValidos} años válidos`],
            ['Coordenada solicitada', coordenada(d.coordenadaSolicitada)], ['Centro de celda representado', coordenada(d.coordenadaCelda)],
            ['Municipio del centro de celda', this.nombre(d.municipioDelCentroCelda)], ['Naturaleza del dato', 'Índice derivado de reanálisis ERA5; no es una medición de estación'],
            ['Método', d.metodo], ['Fuente', d.fuentesRef.map(id => { const f = this.respuesta.fuentes.find(s => s.id === id); return `${f.proveedor} · ${f.producto}`; }).join('; ')],
        ];
        const lista = document.getElementById('sq-campos-detalle'); lista.replaceChildren();
        for (const [nombre, valor] of campos) { const div = elemento('div'); div.append(elemento('dt', nombre), elemento('dd', valor)); lista.append(div); }
        const motivo = document.getElementById('sq-motivo-detalle'); motivo.hidden = d.spi.valor !== null;
        motivo.textContent = motivos[d.spi.motivo] ?? '';
        document.getElementById('sq-detalle').hidden = false;
        document.querySelectorAll('.sq-fila').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.muestra === id)));
        return true;
    }
    cerrar() {
        document.getElementById('sq-detalle').hidden = true;
        document.querySelectorAll('.sq-fila').forEach(b => b.setAttribute('aria-pressed', 'false'));
    }
}
