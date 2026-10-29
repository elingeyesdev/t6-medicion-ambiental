# QA-02 — Verificación de cálculo e integración de sequías en Web y Móvil (#39)

**Fecha:** 29 de octubre de 2026  
**Responsables:** Jaziel Díaz (`Jazielle` — Coordinación e implementación Flutter) y Josué Padilla (`JosuePadillaUnivalle` — Revisión y backend/web Laravel).  
**Hitos y Trazabilidad:** M3, aceptación técnica de F / EDT 4.2. Depende de #37 (WEB-02) y #38 (MOV-04); hereda el método acordado en #35 (MET-01) y la API #36 (API-02).

---

## 1. Resumen Ejecutivo de la Verificación

Se verificó de forma cruzada la paridad completa entre la implementación web Blade (`WEB-02`, PR #45) y la aplicación móvil Flutter (`MOV-04`, PR #38) consumiendo la API común de sequías `sequias-1.0-m3-candidata` (`GET /api/v1/sequias`).

Ambas interfaces muestran de manera idéntica el indicador de sequía, los metadatos de referencia, las advertencias operativas y los límites de interpretación, sin discrepancias de cálculo ni de contexto.

---

## 2. Matriz de Parámetros Contrastados (MET-01 vs WEB-02 vs MOV-04)

| Parámetro | Definición en Contrato MET-01 (#35) | Web Blade (#37) | Móvil Flutter (#38) | Equivalencia |
| :--- | :--- | :--- | :--- | :---: |
| **Identificador** | ID de muestra alfanumérico único | `scz-piloto-01` | `scz-piloto-01` | **Exacta** |
| **Territorio** | Municipio de solicitud en catálogo territorial | BO070101 (Santa Cruz de la Sierra) | BO070101 (Santa Cruz de la Sierra) | **Exacta** |
| **Coordenadas** | Solicitada `[-63.1821, -17.7833]`, celda `[-63.25, -17.75]` | Diferencia solicitada vs. celda visible | Ficha muestra solicitada vs. celda ERA5 | **Exacta** |
| **Período** | Mes final `2024-09`, ventana de 3 meses (jul–sep) | 2024-07-01 a 2024-09-30 (UTC) | 2024-07-01 a 2024-09-30 (UTC) | **Exacta** |
| **Referencia** | 30 años climáticos base estándar (1991–2020) | 1991-01-01 a 2020-12-31 (30 años) | 1991-01-01 a 2020-12-31 (30 años) | **Exacta** |
| **Fuente** | Open-Meteo ERA5 reanálisis 0.25°, `precipitation_sum` | Declarada explícitamente con licencia CC BY 4.0 | Declarada explícitamente con atribución | **Exacta** |
| **Lluvia acum.** | Milímetros (mm) de la ventana de 3 meses | `114.5 mm` | `114.5 mm` | **Exacta** |
| **Indicador SPI** | SPI-3 obtenido por ajuste Gamma MLE en base | `−0.64137695` | `−0.64` (redondeo a 2 decimales) | **Exacta** |
| **Categoría** | Escala estándar: normal, moderada, severa, extrema | `normal` (valor entre -0.99 y +0.99) | `normal` (marcador e icono verde azulado) | **Exacta** |
| **Faltantes** | 55 municipios sin preparar reportados como pendientes | Lista explícita de `muestrasSinPreparar` | Resumen indica 55 sin preparar, sin inventar | **Exacta** |
| **Cobertura** | Muestras puntuales; no superficie ni promedio | Aviso de muestra puntual visible | Aviso explícito en resumen y notas | **Exacta** |
| **Resumen** | Total 1, con índice 1, sin índice 0, con déficit 0 | 1 muestra evaluada, 1 con índice, 0 déficit | 1 evaluada, 1 con índice, 0 sin índice, 0 déficit | **Exacta** |

---

## 3. Comprobación de Casos Límite y Reglas del Método

1. **Filtros e intervalos temporales inválidos:**
   - La solicitud de meses con formato inválido o fuera del rango (`mesFinal=2024-13` o `mesFinal=invalido`) devuelve HTTP 422 `FILTROS_INVALIDOS`. En Flutter se valida antes de emitir la petición y se gestiona el error 422 si proviene de la red.
2. **Territorio no disponible / Muestra sin preparar:**
   - La consulta para un municipio no preparado (ej. Cotoca `BO070102`) devuelve HTTP 503 `MUESTRAS_NO_DISPONIBLES` con el mensaje explícito indicando que los datos deben prepararse fuera de la petición web. Ni Blade ni Flutter inventan valores de reemplazo ni convierten la ausencia de datos en categoría "normal".
3. **Manejo de nulos vs ausencia de sequía:**
   - Si una muestra no cuenta con ajuste válido (ej. menos de 20 años en la base), `spi.valor` es `null` y se acompaña del motivo estructurado (ej. `SERIE_INSUFICIENTE`). Ambos clientes muestran `—` y conservan el motivo sin computar erróneamente un valor cero.
4. **Fallo del proveedor y reintento:**
   - Ante indisponibilidad temporal del backend, la app móvil retiene los datos visualizados previamente si existían, muestra el aviso de error con icono y habilita el botón de **Reintentar**, recuperando la consulta al restablecerse el servicio.
5. **Respuestas tardías (Out-of-order responses):**
   - Al cambiar rápidamente de filtros o meses, ambas interfaces usan identificadores de generación/consulta para descartar respuestas lentas que lleguen desfasadas, evitando sobreescrituras de estado.

---

## 4. Contraste Numérico con Ejemplo Reproducible de Referencia

Para garantizar que ambas interfaces no compartan un sesgo o error de cálculo idéntico originado en el backend, el resultado fue contrastado contra la referencia numérica independiente calculada en Python con `scipy.stats.gamma` (MLE) según el método acordado en MET-01 (#35):

* **Coordenada de celda:** `[-63.25, -17.75]` (Santa Cruz)
* **Precipitación acumulada (jul–sep 2024):** `114.5 mm`
* **Parámetros de ajuste Gamma (1991–2020):**
  * Parámetro de forma ($\alpha$): `7.48415188`
  * Parámetro de escala ($\beta$): `20.52470373 mm`
  * Probabilidad de precipitación cero ($q$): `0.0`
* **Probabilidad acumulada ($H(x)$):** `0.2606389`
* **SPI-3 Teórico de Referencia:** `−0.64137695`
* **SPI-3 Calculado en Laravel:** `−0.6413769540120462`
* **Discrepancia observada:** `< 10^-8` (idéntico hasta la precisión de coma flotante estándar).

---

## 5. Defectos Identificados y Correcciones Verificadas

Durante el proceso de verificación e integración móvil se identificaron y subsanaron los siguientes defectos:

1. **Falta de dependencias en backend local (`Sanctum`):**
   - *Defecto:* Error 500 (`No se pudo completar la consulta`) al autenticarse en móvil debido a falta de `laravel/sanctum` en `vendor/`.
   - *Corrección:* Ejecución de `composer install` y regeneración de autoloaders. Registro y emisión de token 100% operativos.
2. **Desbordamiento visual en selector de filtros de sequías:**
   - *Defecto:* Desbordamiento vertical de 53 píxeles (`BOTTOM OVERFLOWED BY 53 PIXELS`) en el bottom sheet de filtros al no tener cota máxima.
   - *Corrección:* Inclusión de `ConstrainedBox(maxHeight: 0.85)` y `SafeArea` con `SingleChildScrollView`, eliminando el desbordamiento.
3. **Consistencia de interfaz entre Focos de Calor y Sequías:**
   - *Defecto:* La pestaña de focos mostraba banners informativos innecesarios y carecía de la tarjeta superior de resumen con métricas, haciendo que ambas pantallas fueran visualmente dispares.
   - *Corrección:* Se unificó el diseño: ambas secciones cuentan con barra superior de control (Territorio/Mes + Filtros + Recargar), tarjeta de resumen de 4 métricas clave, mapa interactivo y detalle inferior.
4. **Alineación con la barra de estado del sistema:**
   - *Defecto:* Selector de módulo colisionaba con la hora y batería en dispositivos con notch.
   - *Corrección:* Implementación de `AppBar` con `TabBar` nativo de Material 3, respetando automáticamente las dimensiones de estado e insets.

---

## 6. Verificación de No Regresión en Focos de Calor

Se ejecutó la suite completa de pruebas de Flutter para validar que la integración del módulo de sequías y la unificación de la interfaz no afectaron las funcionalidades de focos de calor previamente aceptadas (MOV-02 / #20):

* **Comando:** `flutter test`
* **Resultado:** `34/34 All tests passed!` (incluyendo equivalencia, paginación, respuesta tardía, 0 registros y cálculo de FRP).
* **Análisis estático:** `flutter analyze` finalizó con **`No issues found!`**.

---

## 7. Participación y Revisión Cruzada

* **Jaziel Díaz (`Jazielle`):** Coordinación general, implementación de modelos, repositorio y vistas en Flutter (`MOV-04`), resolución de defectos de interfaz y ejecución en dispositivo físico/emulador.
* **Josué Padilla (`JosuePadillaUnivalle`):** Preparación y publicación del cálculo ERA5 en backend (`API-02`), desarrollo de vistas Blade (`WEB-02`), contraste de parámetros de API y revisión cruzada de consistencia de contrato.
