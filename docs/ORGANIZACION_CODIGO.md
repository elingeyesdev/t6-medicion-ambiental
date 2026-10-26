# Dónde está cada parte

EnviroSense / GeoCruz usa un repositorio con `web/`, `movil/` y `compartido/`. El diseño cambia sin mover las reglas de los módulos a las plantillas. La copia original está en `referencias/envirosense/codigo/`, para comparar la adaptación.

## Web: empezar por la pantalla

```text
web/
  resources/views/
    layouts/app.blade.php             estructura del panel Tabler
    components/panel/                 menú, cabecera, pie e iconos compartidos
    paginas/inicio.blade.php          panel de consultas disponibles
    modulos/focos-calor/index.blade.php
    modulos/sequias/index.blade.php
    errors/404.blade.php              página genérica para direcciones inexistentes
  public/
    vendor/tabler/                    plantilla original, licencia y versión
    vendor/tabler-icons/              SVG originales usados por el panel
    vendor/leaflet/                   biblioteca del mapa
    css/panel/panel.css               ajustes comunes de nuestra interfaz
    css/modulos/                     estilos particulares de cada consulta
    js/focos-calor/                   interacción del visor de focos
    js/sequias/                       interacción del visor de sequías
  app/
    Http/Controllers/InicioController.php  entrega pantallas y catálogos iniciales
    Http/Controllers/Api/                  recibe consultas HTTP del sistema
    Services/Territorios/                  catálogo, filtros y geometrías
    Services/FocosCalor/                   proveedor y reglas de focos
    Services/Sequias/                      series, publicaciones y cálculo
  config/geocruz.php                  nombres y disponibilidad de pantallas
  routes/web.php                     direcciones de las pantallas
  routes/api.php                     operaciones compartidas con Flutter
  tests/Feature/                     comportamiento HTTP de pantallas y API
  tests/Js/                          filtros, fechas y estado de los visores
```

**Cambiar menú o cabecera:** editar `components/panel/`. **Cambiar una pantalla:** su Blade en `modulos/` y su CSS/JS, sin tocar la otra. **Cambiar regla o cálculo:** `app/Services/`, contrato y pruebas correspondientes; no meter fórmulas en Blade. **Habilitar un módulo:** primero implementar y verificar su pantalla/consulta; después cambiar su disponibilidad en `config/geocruz.php`.

Tabler se actualiza como dependencia: conservar licencia, versión e integridad en `procedencia.json`. No editar sus archivos minificados para personalizar un botón. Los ajustes propios van en `css/panel/panel.css`; los componentes Blade usan las clases reales de Tabler. Los SVG están limitados a una lista fija en `components/panel/icono.blade.php`, sin rutas proporcionadas por usuarios.

Los JavaScript de cada visor siguen separados por función: `contrato.js` interpreta parámetros y respuestas; `consulta.js` controla solicitudes y estados; `mapa.js` dibuja; `vista.js` presenta los resultados; `inicio.js` conecta esas partes. No existe una segunda fórmula ambiental en el cliente.

## Móvil y elementos comunes

`movil/lib/app/` inicia Flutter. `movil/lib/core/` contiene red, formatos y reglas de presentación comunes. `movil/lib/features/` agrupa pantallas, modelos y estado de cada función. La revisión Material 3 y el menú pendiente se siguen en #48; esta guía no afirma que esa adaptación ya se hizo.

`compartido/api/` contiene contratos y ejemplos; `compartido/territorios/` contiene catálogo y límites; `compartido/sequias/` identifica método y muestras. Estos archivos no son estilos ni pantallas. `docs/` conserva decisiones, pasos y evidencia.

## Probar la adaptación en este PC

Desde `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\panel-envirosense`, ejecutar `herramientas/sequias/iniciar-visor.ps1 -Puerto 5791`. Abrir http://127.0.0.1:5791/. Probar el menú, focos y el piloto histórico de sequías. Una instalación nueva requiere las dependencias y su configuración privada; las claves no están en el repositorio.

Las publicaciones de sequías tienen vencimiento. Para renovar el piloto en **esta copia**, desde `web/`: `..\herramientas\php.ps1 artisan sequias:preparar-consulta scz-piloto-01 2024-09 --consultar --renovar`. Esto consulta al proveedor por consola; abrir el panel no descarga series.
