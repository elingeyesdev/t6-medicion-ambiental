# Demostración sencilla de la consulta real de NASA

Respuesta a la [revisión preliminar de Jaziel en #18](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/18#issuecomment-6242482264). Preparada el **18/10/2026**. Se pidió explicar la consulta real antes de aprobar; no se reportó un defecto de la API ni se registró aprobación final.

## Qué hace #18

El cliente pide datos al backend de GeoCruz. Laravel consulta NASA FIRMS, recibe detecciones VIIRS, conserva las que están dentro del territorio y período pedidos y devuelve JSON con detalle, fuente, resumen y cobertura. La clave se usa solamente en el backend.

El mapa pertenece a **#19**. Que #18 no cambie visualmente el mapa de la base no significa que su API no esté funcionando. La comprobación de salud tampoco prueba una consulta NASA: son operaciones distintas.

## Mostrarlo en dos terminales

En esta PC, primera terminal PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-focos
.\iniciar-api.ps1
```

Si ya está escuchando en 8002, conservar ese servidor. En una instalación nueva, ejecutar antes `preparar.ps1` y configurar la propia clave solamente en `web/.env`. En la copia de Jaziel se usa la raíz real de su clon, rama `feature/18-api-focos-calor` actualizada con `git fetch origin` y `git pull --ff-only`.

En otra terminal, desde la misma carpeta:

```powershell
.\demostrar-api.ps1 -FechaUtc 2026-10-17 -Confianza nominal
```

Se imprime una explicación legible: URL pública local sin clave, producto NASA, territorio, período UTC y Bolivia, origen nuevo/caché, recuperación, cantidad de detecciones, FRP, cobertura y una detección de ejemplo **tomada de esa respuesta real**. No crea datos para la demostración. Funciona con Windows PowerShell 5.1 y PowerShell 7.

Para ver un filtro sin resultados en la muestra registrada:

```powershell
.\demostrar-api.ps1 -FechaUtc 2026-10-17 -Confianza alta
```

Sin `-Confianza` se consultan todas las confianzas. Sin `-FechaUtc` se consulta el día anterior en UTC. `-GuardarEvidencia` guarda el cuerpo JSON recibido en `tmp/demostracion-api/`, sin sobreescribir la evidencia histórica del repositorio. Para otro servidor local se puede usar `-Puerto 8003`.

## Qué se obtuvo realmente

Consulta nueva de confianza nominal recuperada el **18/10/2026 a las 06:22:59 UTC**, equivalente al **18/10 a las 02:22:59 en Bolivia**:

- Territorio: departamento de Santa Cruz, `BO07`.
- Período: 17/10 00:00 UTC incluido → 18/10 00:00 UTC excluido. En Bolivia: 16/10 20:00 → 17/10 20:00.
- Fuente: NASA FIRMS, producto `VIIRS_SNPP_NRT`, naturaleza `observacion`, versión informada `2.0NRT`.
- Resultado: **4 detecciones nominales**, 0 de confianza alta, FRP máxima **5,26 MW**, sin registros con faltantes en esta muestra.
- La primera detección se adquirió el 17/10 a las 18:30 UTC, tiene confianza nominal y FRP **3,46 MW**. Es distinta de la potencia máxima de todo el conjunto.
- Cobertura: **desconocida**. Encontrar cuatro detecciones no certifica observación completa del departamento.

La consulta independiente de confianza alta recuperada a las **06:22:28 UTC** devolvió **0 detecciones** y FRP máxima `null` (**Sin dato**, no 0 MW). Es evidencia de un filtro vacío, no prueba de que no existan incendios.

Evidencia completa: [consulta nueva nominal](evidencias/api01/revision_20261018/consulta_nominal_real.json), [consulta alta sin registros](evidencias/api01/revision_20261018/consulta_alta_sin_registros.json), [salida legible nominal](evidencias/api01/revision_20261018/demostracion_nominal.txt) y [salida legible alta](evidencias/api01/revision_20261018/demostracion_alta.txt).

Las salidas legibles se ejecutaron después de la recuperación inicial: pueden indicar `cache`, conservando la fecha original. El JSON nominal fijado registra `consulta_proveedor`; el de alta guardado posteriormente registra la reutilización de su consulta original. Caché no significa simulación. Estas respuestas tienen una instantánea temporal; no reutilizar su token como si nunca venciera.

## Explicación para la revisión

Se puede decir: «GeoCruz consultó NASA para ese día y territorio, y recibió cuatro detecciones nominales. Este JSON muestra sus coordenadas, hora de adquisición, confianza, potencia y procedencia. La API funcionó; estos datos no confirman cuatro incendios».

La **adquisición** es cuándo observó el sensor; la **recuperación**, cuándo el backend recibió los datos. `fechaActualizacion=null` significa que el producto no informó esa fecha, no que la recuperación sea su actualización. FRP en **MW** es potencia radiativa; no es área quemada. Confianza es una clasificación del producto, no gravedad. Los resultados NRT y su disponibilidad pueden cambiar en una descarga posterior.

## Comprobar sin terminal

Abrir [la misma consulta nominal en el navegador](http://127.0.0.1:8002/api/v1/focos-calor?desde=2026-10-17T00:00:00Z&hasta=2026-10-18T00:00:00Z&tamanioPagina=500&confianza=nominal). La respuesta es JSON. Buscar `consulta`, `fuentes`, `resumen` y `datos`. Para el recorrido visual, usar la rama y servidor de #19; no reemplazar #18 por una captura de mapa.

## Validación y aceptación

Se ejecutó el comando con respuesta real positiva y vacía, en PowerShell 7 y Windows PowerShell 5.1, y se comprobaron el rechazo de una fecha imposible y el error con servidor inaccesible. Las respuestas nuevas se validaron con el esquema de #18 y se contrastó el resumen con sus registros. No se modificaron el servicio de consulta, el contrato ni sus reglas para responder a esta observación.

La demostración queda disponible para que ambos la recorran. **No se afirma que ya se haya presentado a Jaziel ni que la haya aprobado.** Se mantienen pendientes su aceptación del contrato/PR y los acuerdos territoriales que no consten explícitamente. Las pruebas automáticas con fixtures y esta evidencia externa conservan su distinción.
