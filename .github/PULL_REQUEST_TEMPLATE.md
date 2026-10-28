## Problema y resultado

Describe qué necesidad resuelve y qué comportamiento cambia.

Closes #

## Qué cambió

Describe únicamente los cambios incluidos.

## Cómo probar

Indica requisitos, comandos y pasos reproducibles.

## Evidencia

Enlaza capturas, resultados o registros de la versión ejecutada. Distingue pruebas controladas, simulación y consultas externas reales. No incluyas claves privadas.

## Revisión

- [ ] Rama de la tarea actualizada respecto de develop.
- [ ] Pruebas apropiadas ejecutadas con resultado registrado.
- [ ] Revisión solicitada al compañero.
- [ ] Restricciones o decisiones pendientes documentadas.

## Al aceptar el PR

Con la revisión aprobada y las pruebas pasadas, fusionar la rama de esta tarea y borrarla:

```powershell
gh pr merge NUMERO --repo elingeyesdev/t6-medicion-ambiental --merge --delete-branch
```

Completar el número con `./herramientas/github/completar-pr.ps1 -Numero NUMERO` después de abrir el PR. El comando de arriba se ejecuta al fusionar; ponerlo en la descripción no lo ejecuta automáticamente.

Si el origen es `develop` o `main`, conservar esa rama permanente. Una entrega hacia main sigue necesitando la aprobación expresa del ingeniero.
