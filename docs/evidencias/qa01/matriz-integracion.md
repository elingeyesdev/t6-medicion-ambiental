# QA-01 — Matriz de integración web y móvil

## Registro de ejecución

- Fecha de ejecución: 21/10/2026 (hora de Bolivia, UTC−4; según las capturas compartidas).
- Entorno: Windows; Laravel/Blade local en el puerto 8003; API local en el puerto 8002; Flutter en emulador Android Pixel 7 API 36.
- Alcance de este registro: comparación nominal de una consulta real en web y Flutter.
- Revisión cruzada de Josué: pendiente.
- SHA exacto del código web usado para estas capturas: no quedó registrado al ejecutar la consulta.
- Código móvil: rama `feature/20-flutter-focos-calor`, commit `8b995d3` (confirmar que corresponde al APK de las capturas).

## Matriz

| Caso | Entradas / pasos | Resultado web | Resultado Flutter | Estado |
|---|---|---|---|---|
| Consulta nominal | Provincia Warnes; todos los municipios; confianza Todas; 14/10/2026 00:00 a 21/10/2026 00:00, Bolivia; final excluido. Abrir mapa/lista y el detalle de la detección. | 1 detección; 0 de confianza alta; FRP máxima 3,43 MW; 0 registros con faltantes. | Los mismos totales y la misma detección en mapa y detalle. | **Coincide**, según las capturas adjuntas al Issue #21. |
| Parámetros inválidos | Registrar entradas inválidas y observar la respuesta de ambas interfaces/API. | Sin resultado registrado en esta ejecución. | Sin resultado registrado en esta ejecución. | Pendiente de evidencia. |
| Consulta vacía | Ejecutar un período/territorio sin registros y comprobar el estado vacío en ambas interfaces. | Sin resultado registrado en esta ejecución. | Sin resultado registrado en esta ejecución. | Pendiente de evidencia. |
| Falta de cobertura | Consultar un ámbito sin cobertura comprobada y distinguirlo de una consulta vacía. | Sin resultado registrado en esta ejecución. | Sin resultado registrado en esta ejecución. | Pendiente de evidencia. |
| Fallo del proveedor | Usar una prueba controlada/fixture de fallo; registrar por separado cualquier consulta real. | Sin resultado registrado en esta ejecución. | Sin resultado registrado en esta ejecución. | Pendiente de evidencia. |
| Respuesta antigua | Usar una prueba controlada/fixture de respuesta anterior y verificar que se identifique su fecha. | Sin resultado registrado en esta ejecución. | Sin resultado registrado en esta ejecución. | Pendiente de evidencia. |

## Detalle de la consulta nominal

- Identificador observado: `firms-d3b5fec73c36c3c91b3e24e1eab5d5cd6c5bfd95f43dd5c5a5e25f2c844ead4d`.
- Territorio: Warnes.
- Fecha observada: 20/10/2026 13:32, hora de Bolivia.
- Confianza: nominal.
- Potencia radiativa (FRP): 3,43 MW.
- Fuente/producto/versión: NASA FIRMS / VIIRS_SNPP_NRT / 2.0NRT.
- Recuperación indicada en el detalle móvil: 21/10/2026 00:26, Bolivia; antigüedad mostrada: 68 segundos; tipo: caché de API.
- Consulta indicada en el detalle móvil: `a8486379-3868-4632-a16d-ef05ec46c1b6`.

## Pasos para reproducir

1. Desde la raíz del repositorio, en una terminal, ejecutar `Set-ExecutionPolicy -Scope Process Bypass` si PowerShell bloquea los scripts; después `.\iniciar-api.ps1 -Puerto 8002`. Dejar la terminal abierta.
2. En otra terminal, desde la raíz, ejecutar `Set-ExecutionPolicy -Scope Process Bypass` si PowerShell bloquea los scripts y `.\iniciar-web.ps1 -Puerto 8003`. Abrir `http://127.0.0.1:8003/modulos/focos-calor`.
3. En otra terminal, desde `movil/`, ejecutar `flutter devices` y `flutter run -d <id-del-emulador>`.
4. En ambas interfaces aplicar los filtros de la fila nominal y comparar totales, identificador, territorio, fecha, confianza, FRP, fuente y versión.

## Evidencia adjunta al Issue #21

- [Web: consulta, filtros, resumen, mapa y lista](https://github.com/user-attachments/assets/a733f6ec-576b-4d9f-b2ad-0b15f4bf6015)
- [Móvil: filtros](https://github.com/user-attachments/assets/7eae1491-8ee7-4230-ae14-b63d0ff76aac)
- [Móvil: mapa y resumen](https://github.com/user-attachments/assets/60ac7956-dc94-4acc-b26d-77266f1ca031)
- [Móvil: detalle y procedencia](https://github.com/user-attachments/assets/dbf7b658-60f6-4502-9c63-bc9884ec5ba5)
- [Web: mapa y detalle de la detección](https://github.com/user-attachments/assets/dfa444da-28d4-4cf5-94b9-f8dea9ba1ae0)

Las capturas acreditan la consulta nominal y la coincidencia visible entre clientes. No acreditan por sí solas los casos que figuran pendientes ni sustituyen la revisión cruzada.
