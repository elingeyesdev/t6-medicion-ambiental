# Dónde está cada parte

GeoCruz usa un repositorio con `web/`, `movil/` y `compartido/`. El diseño cambia sin mover las reglas de las consultas a las plantillas. La copia original de EnviroSense está en `referencias/envirosense/codigo/`, para comparar la adaptación.

## Web: empezar por la pantalla

```text
web/
  resources/views/
    layouts/publico.blade.php         portada y recursos públicos
    layouts/acceso.blade.php          cubierta de acceso oficial de Tabler adaptada
    acceso/                          registro e ingreso
    paginas/inicio.blade.php          portada de GeoCruz
    layouts/app.blade.php             estructura compartida del panel
    components/panel/                 menú, cabecera, pie e iconos compartidos
    modulos/focos-calor/index.blade.php  mapa de monitoreo e inicio
    modulos/focos-calor/              filtros, mapa, estado y resultados separados
    modulos/sequias/index.blade.php
    errors/404.blade.php              página genérica para direcciones inexistentes
  public/
    vendor/tabler/                    plantilla original, licencia y versión
    vendor/tabler-icons/              SVG originales usados por el panel
    vendor/leaflet/                   biblioteca del mapa
    vendor/mapbox/                    logotipo oficial de atribución, sin motor GL
    vendor/space-grotesk/             tipografía de EnviroSense y licencia OFL
    css/panel/panel.css               identidad, menú y cabecera de EnviroSense
    css/panel/marca.css               logo GeoCruz y tamaños
    css/panel/mapas.css               selector y atribución de mapas
    css/paginas/publico.css           inicio y cubierta de acceso
    img/marca/                        logotipo y símbolo aprobados
    img/amboro/                       fotografía real, fuente y licencia
    css/modulos/                     estilos particulares de cada consulta
    js/panel/                        tema claro/oscuro y navegación compartida
    js/mapas/base.js                  base Mapbox común, sin cálculos ambientales
    js/focos-calor/                   interacción del visor de focos
    js/sequias/                       interacción del visor de sequías
  app/
    Http/Controllers/InicioController.php  entrega pantallas y catálogos iniciales
    Http/Controllers/Api/                  recibe consultas HTTP del sistema
    Support/ConfiguracionMapa.php          publica solo configuración pública válida
    Services/Territorios/                  catálogo, filtros y geometrías
    Services/FocosCalor/                   proveedor y reglas de focos
    Services/Sequias/                      series, publicaciones y cálculo
  config/geocruz.php                  nombres y disponibilidad de pantallas
  config/mapas.php                    token público Mapbox desde el entorno privado
  routes/web.php                     direcciones de las pantallas
  routes/api.php                     operaciones compartidas con Flutter
  tests/Feature/                     comportamiento HTTP de pantallas y API
  tests/Js/                          filtros, fechas y estado de los visores
```

**Cambiar menú o cabecera:** editar `components/panel/`. **Cambiar una pantalla:** su Blade en `modulos/` y su CSS/JS, sin tocar la otra. **Cambiar regla o cálculo:** `app/Services/`, contrato y pruebas correspondientes; no meter fórmulas en Blade. **Habilitar un módulo:** primero implementar y verificar su pantalla/consulta; después cambiar su disponibilidad en `config/geocruz.php`.

Tabler se actualiza como dependencia: conservar licencia, versión e integridad en `procedencia.json`. No editar sus archivos minificados para personalizar un botón. El acceso usa su composición oficial Sign in with cover; los ajustes propios quedan en `css/paginas/publico.css` y `css/panel/`. El símbolo de GeoCruz está en `img/marca/` y se usa desde `components/panel/marca.blade.php`. Los iconos de controles están limitados a una lista fija en `components/panel/icono.blade.php`, sin rutas proporcionadas por usuarios.

**Inicio, acceso y mapa:** `/` es la portada de GeoCruz. `/login` y `/register` recuperan su acceso con sesiones de Laravel. `/mapa` y `/modulos/` requieren sesión; comparten layout y consultas. `/mapa` reutiliza el visor de focos sin una segunda API. `focos-calor.css` define componentes de resultados y `mapa-monitoreo.css` su distribución sobre el mapa. `filtros.blade.php`, `mapa.blade.php`, `estado.blade.php` y `resultados.blade.php` mantienen cada parte localizable. La geometría inicial es contexto territorial; los resultados ambientales aparecen al consultar. `js/panel/preferencias.js` recupera el tema antes de dibujar; `navegacion.js` maneja menú contraíble, tema y teclado, sin cálculos ambientales. El acceso se explica en [ACCESO_ENVIROSENSE.md](ACCESO_ENVIROSENSE.md).

Los JavaScript de cada visor siguen separados por función: `contrato.js` interpreta parámetros y respuestas; `consulta.js` controla solicitudes y estados; `mapa.js` dibuja; `vista.js` presenta los resultados; `inicio.js` conecta esas partes. No existe una segunda fórmula ambiental en el cliente.

## Móvil y elementos comunes

`movil/lib/app/` inicia Flutter. `movil/lib/core/` contiene red, formatos y reglas de presentación comunes. `movil/lib/features/` agrupa pantallas, modelos y estado de cada función. La revisión Material 3 y el menú pendiente se siguen en #48; esta guía no afirma que esa adaptación ya se hizo.

`compartido/api/` contiene contratos y ejemplos; `compartido/territorios/` contiene catálogo y límites; `compartido/sequias/` identifica método y muestras. Estos archivos no son estilos ni pantallas. `docs/` conserva decisiones, pasos y evidencia.

## Probar la adaptación en este PC

Desde `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\panel-envirosense`, ejecutar `./iniciar-web.ps1 -Puerto 5791`. Abrir http://127.0.0.1:5791/. Probar el menú, focos y el piloto histórico de sequías. Una instalación nueva requiere las dependencias y su configuración privada; las claves no están en el repositorio. La comprobación de esta copia se hizo con el servidor PHP nativo alternativo `herramientas/sequias/iniciar-visor.ps1 -Puerto 5791`, que sirve el mismo panel completo.

Las publicaciones de sequías tienen vencimiento. Para renovar el piloto en **esta copia**, desde `web/`: `..\herramientas\php.ps1 artisan sequias:preparar-consulta scz-piloto-01 2024-09 --consultar --renovar`. Esto consulta al proveedor por consola; abrir el panel no descarga series.
