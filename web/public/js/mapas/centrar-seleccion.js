(() => {
    const L = window.L;
    if (!L || L.Map.prototype.centrarSeleccion) return;

    L.Map.include({
        recordarParaCentrar(punto) {
            this._puntoParaCentrar = punto == null ? null : L.latLng(punto);
        },

        centrarSeleccion() {
            const destino = this._puntoParaCentrar;
            if (destino) {
                this.setView(destino, 14, { animate: false });
                return;
            }
            if (this._vistaParaCentrar) {
                this.setView(this._vistaParaCentrar.centro, this._vistaParaCentrar.zoom, { animate: false });
            }
        },
    });

    L.Map.addInitHook(function () {
        const mapa = this;
        mapa.whenReady(() => {
            if (!mapa.dragging.enabled() && !mapa.scrollWheelZoom.enabled() && !mapa.zoomControl) return;
            mapa._vistaParaCentrar = { centro: mapa.getCenter(), zoom: mapa.getZoom() };
            mapa.on('click', ({ latlng }) => mapa.recordarParaCentrar(latlng));
            mapa.on('popupopen', ({ popup }) => mapa.recordarParaCentrar(popup.getLatLng()));

            const control = L.control({ position: 'topright' });
            control.onAdd = () => {
                const contenedor = L.DomUtil.create('div', 'leaflet-bar');
                const boton = L.DomUtil.create('button', 'geosur-centrar', contenedor);
                boton.type = 'button';
                boton.textContent = 'Centrar';
                boton.title = 'Centrar en el último punto seleccionado';
                boton.addEventListener('click', () => mapa.centrarSeleccion());
                L.DomEvent.disableClickPropagation(contenedor);
                return contenedor;
            };
            control.addTo(mapa);
        });
    });
})();
