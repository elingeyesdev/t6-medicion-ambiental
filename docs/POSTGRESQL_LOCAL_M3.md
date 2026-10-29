# PostgreSQL local comprobado en M3 (#66)

29/10/2026. Probé la web contra PostgreSQL **16.11** en un clúster nuevo de pruebas, con usuario, contraseña aleatoria y base propios. Solo escuchó en `127.0.0.1`; no inicié el servicio PostgreSQL instalado ni abrí sus bases. La demo sigue usando su SQLite, con las mismas cuentas.

## Resultado

- Suite PostgreSQL: **476 pruebas, 5707 comprobaciones correctas**, PHP 8.3.30.
- Concurrencia real PostgreSQL: cuatro procesos importando el mismo lote de 60 lecturas dejan una copia; dos lotes en conflicto conservan un ganador completo y revierten el perdedor. Estos dos casos usan esquemas nuevos y separados dentro de la base de pruebas, y los retiran al terminar.
- SQLite conserva sus dos modos, DELETE y WAL. Después de adaptar las pruebas, los 19 casos de aislamiento, reportes y concurrencia pasaron, con 140 comprobaciones. La integración completa SQLite había pasado 477 pruebas y 5717 comprobaciones.
- La diferencia de un caso entre las suites se debe a los dos modos propios de SQLite frente al único modo PostgreSQL; no se omitió un caso funcional de reportes o permisos.

La primera pasada encontró dos supuestos en las pruebas: el control de aislamiento exigía SQLite y el ensayo de reportes suponía que los IDs empezaban en 1. Ahora el control reconoce únicamente el PostgreSQL de pruebas autorizado y el ensayo conserva las primeras cien lecturas por su identidad real. No se bajó el límite de reportes ni se cambió el comportamiento de la aplicación para hacer pasar la prueba.

## Repetir sin tocar la demo

Con PostgreSQL 16 instalado, PHP 8.3 y `pdo_pgsql` disponible, desde la raíz:

```powershell
./herramientas/datos/probar-postgresql.ps1
# Si el puerto está ocupado, el comando se detiene; elegir otro explícitamente:
./herramientas/datos/probar-postgresql.ps1 -Puerto 55433
```

El script crea un directorio nuevo con nombre único bajo `tmp/`, genera su clave en privado, prepara un clúster con SCRAM, crea `geosur_m3_tests` y ejecuta `web/phpunit.pgsql.xml`. El arranque queda oculto. El bootstrap exige la opción explícita, la base nombrada y loopback; no acepta una URL de conexión que sustituya ese destino. La contraseña se pasa por el entorno y no se imprime ni se guarda en el repositorio.

La configuración normal `web/phpunit.xml` sigue forzando SQLite en memoria. El script restaura su entorno al terminar y detiene solamente el clúster que creó, también si falla una prueba. Los archivos de prueba y sus registros quedan ignorados en `tmp/`; no se borran bases ajenas ni se reutiliza el PostgreSQL del usuario. El servicio real y un despliegue siguen fuera de esta comprobación.
