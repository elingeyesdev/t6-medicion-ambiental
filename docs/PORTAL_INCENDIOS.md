# Portal de avisos de incendio

El portal académico se abre en `/portal-incendios`. Puede consultarse sin cuenta. Publica detecciones reales de NASA FIRMS de alta confianza en Santa Cruz. La ficha muestra el municipio y las coordenadas aproximadas de la primera detección. Los estados y resultados de brigada representan una demostración académica.

## Preparar el entorno

1. Configurar la clave NASA FIRMS en el backend y ejecutar las migraciones de Laravel.
2. **Crear cuenta** está disponible en el acceso del portal. El registro de la demo activa de inmediato una cuenta propia del portal y pide nombre, correo y contraseña.
3. Ejecutar `php artisan portal-incendios:importar` para consultar las últimas 24 horas. Para actualizar cada 30 minutos, habilitar `PORTAL_INCENDIOS_IMPORTAR=true` y ejecutar el scheduler de Laravel en el entorno de destino.
4. En Focos de calor, abrir una detección de alta confianza y pulsar **Consultar aviso público**. Si la detección ya fue importada, el enlace abre directamente su ficha.

Las detecciones se agrupan dentro del mismo municipio a un máximo de 3 km y 6 horas de la detección inicial. Una observación ya registrada conserva su aviso al importar otra vez. Los avisos sin atender pasan al historial a las 72 horas de su publicación. Los avisos tomados siguen activos hasta que la cuenta asignada registre un resultado.
