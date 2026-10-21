# Adaptación de EnviroSense a GeoCruz

Fecha del corte: 20/10/2026. Seguimiento: [MIG-01 / #29](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/29). Base activa revisada: `develop` en `202df8fb77ace56eebc81d5b135ef6dd1d509fd7`. Esta incorporación conserva esa implementación y añade la referencia y su trazabilidad; no implementa nuevos módulos ambientales.

## Alcance confirmado

Josué confirmó mantener **focos de calor, sequías, inundaciones, deforestación y calidad del aire**, y adaptar las funciones útiles del referente. Se conserva el alcance de [DOC-04 / #4](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/4), la planificación y las tareas ya aceptadas. La sospecha sobre el orden de carga a `main` todavía no es una instrucción confirmada del ingeniero.

El código publicado de EnviroSense está completo como referencia en [`referencias/envirosense/codigo/`](../referencias/envirosense/codigo/), con [procedencia, atribución y sustituciones declaradas](../referencias/envirosense/README.md). La instalación local que se utilizó para probar el original tiene cambios posteriores y no se usa como fuente de esta línea base.

## Tecnología y organización activa

- `web/`: Laravel 12 con PHP 8.2+, controladores y servicios de la API común, vistas Blade y JavaScript del visor. Responsable: Josué.
- `movil/`: Flutter y Dart para Android, con navegación y cliente de la API. Responsable: Jaziel.
- `compartido/api/`: contrato OpenAPI, reglas de consulta y validación de respuestas compartidas.
- `compartido/territorios/`: catálogo y geometrías versionadas de Santa Cruz.
- `referencias/envirosense/`: proyecto de origen para comparación; no se ejecuta desde el arranque de GeoCruz.
- `docs/`: alcance, decisiones, pruebas y evidencias.

La interfaz React y el servidor Express permanecen en la referencia; las rutas, servicios y vistas de GeoCruz se reimplementan en Laravel/Blade. La aplicación Flutter se desarrolla por separado y consume la misma API. No se transforma una PWA de React en una aplicación Flutter copiando sus archivos.

## Correspondencia comprobable

Las rutas de origen siguientes se leen bajo `referencias/envirosense/codigo/`. La relación describe una adaptación funcional y no afirma que el código sea idéntico o que los datos de ambos sistemas sean equivalentes.

1. **Navegación y estructura de pantalla.** Origen: `Frontend/src/App.jsx` y `Frontend/src/components/Layout/Layout.jsx`. Adaptación: `web/routes/web.php`, `web/app/Http/Controllers/InicioController.php`, `web/resources/views/layouts/app.blade.php`, `web/config/geocruz.php`; en móvil, `movil/lib/app/geocruz_app.dart` y `movil/lib/features/home/home_shell.dart`. GeoCruz usa cinco módulos y consulta pública, sin importar las rutas de administración.
2. **Selección territorial.** Origen: `Frontend/src/hooks/useFronteras.js` y `Backend/Src/modules/geografia/`. Adaptación: `web/app/Services/Territorios/`, `web/app/Http/Controllers/Api/TerritoriosController.php` y `compartido/territorios/`. Se usan catálogo y geometrías propios de Santa Cruz, sin copiar IDs de ciudades o países del referente.
3. **Mapa, filtros, listado y selección de detalle.** Origen: `Frontend/src/pages/MapaMonitoreo/MapaMonitoreo.jsx` y `Frontend/src/components/MapaMonitoreo/ControlPanel.jsx`. Adaptación: `web/resources/views/focos-calor/index.blade.php` y `web/public/js/focos-calor/`. Los módulos JavaScript separan consulta, contrato, período, representación, mapa y vista. El listado y el mapa muestran la misma página de resultados; el resumen de la API corresponde al conjunto filtrado.
4. **Normalización de datos y errores.** Origen: `Backend/Src/modules/sensores/sensores.service.js`, `Backend/Src/utils/response.js` y `Frontend/src/config/httpClient.js`. Adaptación: `web/app/Services/FocosCalor/`, `web/app/Services/Api/` y `compartido/api/openapi.json`. El backend consulta NASA FIRMS y conserva procedencia y tiempos. No utiliza las estimaciones ni los reemplazos aleatorios del referente cuando falla un proveedor.
5. **Fechas y contexto.** Origen: `Frontend/src/components/UI/AtmosphericDatePicker.jsx` y `Frontend/src/utils/unidades.js`. Adaptación: `web/public/js/focos-calor/periodo.js`, `web/public/js/focos-calor/contrato.js`, `movil/lib/core/formatters/bolivia_time.dart` y `movil/lib/features/consultation/consulta_context.dart`. Las fechas, unidades y límites se definen por el producto y por el contrato de GeoCruz; no se heredan las ventanas meteorológicas del referente.
6. **Navegación móvil.** Origen funcional: `Frontend/src/pages/mobile/`. Adaptación Flutter: `movil/lib/features/home/`, `movil/lib/features/modules/`, `movil/lib/features/consultation/` y `movil/lib/core/network/geocruz_api_client.dart`. MOV-01 prepara la base y las plantillas; el consumo real de focos corresponde a MOV-02 / #20.

Las pruebas y evidencias anteriores se conservan en `web/tests/`, `movil/test/` y `docs/evidencias/`. Una correspondencia de archivos no sustituye una prueba de aceptación del módulo.

## Estado real de los cinco módulos

- **Focos de calor:** API y visor Blade integrados en `develop` mediante [#25](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/25) y [#26](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/26). Hay evidencia previa de consulta NASA y pruebas aisladas. La consulta móvil real sigue en [#20](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/20); la comparación de resultados web/móvil sigue en [#21](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/21).
- **Sequías:** entrada de navegación y requisitos definidos; consulta ambiental pendiente de método, producto e implementación.
- **Inundaciones:** entrada de navegación y requisitos definidos; pendientes método, escenas de referencia, validación e implementación. Un mapa de agua no demuestra por sí solo una inundación.
- **Deforestación:** entrada de navegación y requisitos definidos; pendientes adopción del producto, criterio, geometría e implementación de la consulta.
- **Calidad del aire:** entrada de navegación y requisitos definidos; pendiente decisión de fuente operativa y representación de observaciones o modelado, además de implementación.

No se presenta el código meteorológico del referente como si implementara automáticamente esos cuatro módulos. Las pantallas pendientes conservan “Próximamente” y no muestran registros ficticios.

## Funciones que no se incorporan a esta adaptación

Autenticación y roles, agua, ruido, sensores/MQTT, chatbot, simulación avanzada, reportes PDF/Excel, alertas y notificaciones permanecen en la referencia. Añadirlas a GeoCruz exige una decisión posterior de alcance, criterios de aceptación y planificación. No se migran cuentas, sesiones o datos de la base local original.

No se requiere PostgreSQL/TimescaleDB, un broker MQTT, Firebase, WhatsApp ni servicios de React/Express para probar el GeoCruz actual. Las claves de proveedores siguen únicamente en `web/.env`, fuera de Git y de Flutter.

## Qué revisar y probar

La [guía de ramas](FLUJO_GIT.md) define el paso de una tarea a `develop` y la promoción académica a `main`.

Desde la copia activa de GeoCruz:

```powershell
.\preparar.ps1
.\iniciar-web.ps1
```

Abrir **http://127.0.0.1:8003**. Revisar que aparecen los cinco módulos, que focos abre filtros/mapa/listado y que los otros cuatro indican preparación. Para una consulta externa configurar la clave propia en `web/.env` según [la guía de la API](../compartido/api/README.md). Probar una selección territorial y período admitidos, abrir el mismo registro desde mapa y lista, y contrastar fecha, coordenadas y procedencia. Una respuesta válida vacía y un error de proveedor deben verse distintos.

Comprobar la integridad de la referencia desde la raíz:

```powershell
python referencias/envirosense/verificar.py
```

Las comprobaciones de Laravel y JavaScript se ejecutan desde `web/` con `..\herramientas\php.ps1 artisan test --compact` y `npm test`. Para Flutter seguir [su guía](../movil/README.md). No se declara completada QA-01 ni la entrega M2 por incorporar esta referencia.
