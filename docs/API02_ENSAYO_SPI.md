# API-02 / #36: ensayo local del SPI-3 propuesto

El candidato de #35 / PR #42 ya se puede **contrastar en PHP**, separado en `web/app/Services/Sequias/Experimental/`. Sigue pendiente de revisión de Jaziel: este avance no adopta el método ni completa #36. La ruta de sequías sigue en 503; `main`, focos y el contrato HTTP aceptado se conservan. No se exige Python para ejecutar Laravel ni el ensayo.

## Prueba sencilla en este equipo

Desde PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias
.\herramientas\sequias\probar-spi.ps1
```

Resultado esperado: **114,5 mm**, **SPI −0,6410480784**, categoría **normal**, 30 ventanas de referencia completas. El script termina en 0 si coincide con la referencia a tolerancia absoluta 1e-6. Usa las dos versiones locales ya preparadas, verifica otra vez su integridad y no descarga datos. No necesita servidor ni clave NASA. Es el piloto de julio–septiembre de 2024 en una celda ERA5, no una evaluación actual de todo Santa Cruz.

Para ver el JSON completo, desde `web/`:

```powershell
..\herramientas\php.ps1 artisan sequias:ensayar-spi 7642d10dab58e12edaf928a3d4548a3d7d804c74aa5c36d0fc585316ab51dd79 195010a0862fd4f0384b65693bb3a7966fa0540f1ea5c41c54c0fb7313ebb723 2024-09
```

Sintaxis general: `php artisan sequias:ensayar-spi ID_BASE ID_OBJETIVO AAAA-MM`. Salida 0: índice finito; 1: resultado null con motivo; 2: entrada incompatible, mes no admitido o versión ausente/corrupta. `estado=ensayo_pendiente_revision` siempre, incluso si el resultado es normal. Los IDs identifican versiones del almacén, no archivos o URLs arbitrarios.

En otra instalación el almacén privado estará vacío. Preparar los originales con `sequias:preparar-serie` y su declaración de procedencia, según [preparación de series](API02_PREPARACION_SERIES.md). Las capturas y fechas originales están en el commit `1d1865f9686a97c9a61a3ecccd07006040e2b7f5` del [PR #42](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/42), en `docs/evidencias/met35/fuente-20261022/`; no se duplican aquí. Los IDs de este ejemplo requieren conservar exactamente las declaraciones que acompañan la evidencia PHP. También se pueden ejecutar las pruebas siguientes sin importar capturas ni usar Internet.

## Datos y cálculo

`EnsayoSpi3` lee únicamente versiones verificadas por el repositorio de series. Rechaza mezclar muestras, coordenadas solicitadas/celdas, tipos de evidencia, proveedores, productos o naturaleza. Para capturas externas exige la declaración Open-Meteo / ERA5 / reanálisis. Esto contrasta metadatos e integridad, **no certifica la autenticidad** de una declaración. Las fixtures siguen identificadas como simulación controlada.

Admite meses desde enero de 2021, anteriores al mes UTC actual, como propone #35. Suma los tres meses completos de la ventana objetivo y las 30 ventanas que terminan en el mismo mes entre 1991 y 2020, incluyendo noviembre/diciembre de 1990 para enero/febrero de 1991. Un día/mes ausente o nulo conserva la ventana en null. Una falta fuera de las ventanas usadas no invalida otras ventanas completas. No se convierte a cero ni se redondea lluvia antes de sumar.

`Spi3Propuesto` exige 30 ventanas completas, al menos 10 positivos de 0,01 mm o más y una base positiva no constante. Ajusta Gamma por MLE con localización cero, agrega la masa en cero y transforma la probabilidad mediante la normal inversa. Mantiene categorías y fronteras del candidato, sin redondear el índice para clasificar. Devuelve null con `BASE_INCOMPLETA`, `PERIODO_INCOMPLETO`, `BASE_NO_AJUSTABLE`, `AJUSTE_FALLIDO` o `PROBABILIDAD_EXTREMA`. No recorta probabilidades 0/1 para inventar un índice finito.

`NumericaGammaNormal` no añade dependencias al servidor: resuelve por bisección `log(α)−ψ(α)=log(media)−media(log(x))`, con escala `media/α`. Usa recurrencia hasta 12 y expansiones de log-Gamma/digamma ([NIST DLMF 5.11](https://dlmf.nist.gov/5.11)); serie de Gamma inferior y fracción continua superior ([8.7](https://dlmf.nist.gov/8.7), [8.9](https://dlmf.nist.gov/8.9)); normal inversa por bisección de la cola `Q(1/2,z²/2)/2` ([8.4](https://dlmf.nist.gov/8.4)). Son implementaciones propias de esas ecuaciones, no código copiado de SciPy.

Límites técnicos explícitos: forma Gamma entre 1e-8 y 1e6; máximo 160 pasos para el ajuste, 10.000 términos para Gamma y 80 pasos para la inversa. La falta de convergencia o un rango no representable devuelve `AJUSTE_FALLIDO`; no cambia el método silenciosamente. La pequeña protección de denominadores de la fracción continua evita división por cero; no recorta el índice/probabilidad. Estas cotas numéricas requieren revisión junto con la propuesta, especialmente para bases casi constantes y colas extremas.

El resultado conserva muestra, coordenadas solicitadas y de celda, referencia, ventana, acumulados, parámetros y procedencia/ID de ambas entradas. No calcula promedio municipal, área en sequía, asignación territorial ni daño agrícola; no es el SPI oficial de Copernicus (su ajuste es distinto) ni está validado con estaciones locales.

## Contraste reproducible

Desde `web/`, después de instalar las dependencias fijadas:

```powershell
..\herramientas\php.ps1 artisan test --compact --filter=Spi3
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
```

Las pruebas numéricas usan un [oráculo congelado](ejemplos/sequias/spi3-oraculo.json) calculado independientemente con **SciPy 1.16.3**: nueve bases/objetivos, 35 pares Gamma CDF/supervivencia y ocho cuantiles normales. Solo el primer caso procede del piloto externo; los demás son entradas controladas. Se contrastan diferentes escalas, ceros, umbral 0,01, asimetría, concentración y colas. Tolerancias: SPI absoluto 1e-6, CDF 1e-8, parámetros 1e-7 relativo con suelo absoluto 1e-7 y normal inversa 1e-10. El caso exponencial tiene además una solución analítica independiente.

El [generador](../herramientas/sequias/generar-oraculo-spi.py) permite reproducir ese archivo con Python/SciPy fijado, sin ejecutar PHP ni consultar proveedores:

```powershell
python herramientas/sequias/generar-oraculo-spi.py --piloto RUTA_AL_RESULTADO_REFERENCIA_DEL_PR42.json --salida tmp/oraculo-reproducido.json
```

El archivo de origen es `docs/evidencias/met35/resultado-referencia.json` de dicho commit. Su SHA-256 con finales LF (los del blob Git), los hashes exactos de las capturas, fechas y URLs se conservan en el oráculo; comparar el archivo reproducido antes de reemplazar referencias. La normalización LF se aplica solo al informe de referencia, nunca a capturas originales. Las pruebas normales de CI usan el oráculo congelado y no instalan Python/SciPy para el cálculo PHP.

Las pruebas de integración generan series **controladas**, preparan versiones aisladas y verifican cruce de año, mes de referencia, faltantes/null, incompatibilidades, corte UTC, códigos de consola y conservación del 503. La evidencia del piloto real reusado está en [api02-spi](evidencias/api02-spi/README.md).

Verificación local de este avance: **19 pruebas nuevas / 244 aserciones**; suite completa **117 aprobadas / 705 aserciones**; formato y demostración PowerShell aprobados.

## Lo que continúa pendiente

Revisión del método/contrato #35, catálogo territorial de muestras, acceso y renovación del proveedor, caché operativa, respuesta HTTP y clientes web/Flutter. El ensayo PHP reduce el riesgo del portado numérico; no acredita esas funcionalidades ni cierra sus issues.
