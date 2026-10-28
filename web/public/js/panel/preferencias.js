// Preferencias de la interfaz; no contienen datos ambientales ni credenciales.
try {
    document.documentElement.dataset.bsTheme = localStorage.getItem('envirosense-tema') === 'dark' ? 'dark' : 'light';
    document.documentElement.classList.toggle('menu-contraido', localStorage.getItem('envirosense-menu') === 'contraido');
} catch { /* Sin almacenamiento, se usa el aspecto claro de EnviroSense. */ }
