# Limpieza de ramas integradas #81

El 28/10 se revisaron los quince PR integrados del repositorio. Se borraron trece ramas remotas; las otras dos ya no existían. También se eliminaron dieciséis referencias locales integradas, incluidas dos usadas para revisar móvil/QA.

Antes de borrar se comprobó el commit exacto de cada rama, su PR fusionado, que su integración siguiera en main/develop y que no fuera origen o base de un PR abierto. El borrado remoto fue atómico y condicionado al commit esperado. Las carpetas de trabajo se conservaron; las que usaban una rama cerrada quedaron sobre el mismo commit, sin eliminar sus archivos.

`resultado.json` identifica ramas, PR y commits. No contiene credenciales ni rutas privadas. Main sigue en `851a79f` y develop en `98e428f`; no se fusionaron PR pendientes.

Después de esta limpieza Jaziel aprobó e integró #55 en develop (`aa8aa7d`). Su rama remota ya estaba eliminada al comprobarlo; también se eliminó su referencia local, ya integrada. El total local pasa a diecisiete. Este avance del compañero es posterior al registro inicial, no una fusión hecha durante la limpieza.

El comando se añadió a los PR abiertos mediante `herramientas/github/completar-pr.ps1`. Se comprobó su sintaxis, el modo de vista previa y que repetirlo no duplique la sección. El script no fusiona PR ni elimina ramas: solo completa la descripción para el momento de aceptar el cambio.

El ajuste global de GitHub **Automatically delete head branches** sigue desactivado. La cuenta tiene escritura, pero GitHub rechazó su modificación. Debe configurarlo quien administre el repositorio; un comando escrito en una descripción tampoco activa ese ajuste.
