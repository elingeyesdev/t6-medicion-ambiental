const raiz = document.documentElement;
const menu = document.getElementById('menu-panel');
const abrir = document.getElementById('abrir-menu');
const contraer = document.getElementById('contraer-menu');
const fondo = document.getElementById('fondo-menu');
const tema = document.getElementById('cambiar-tema');
const movil = matchMedia('(max-width: 767.98px)');
const guardar = (clave, valor) => { try { localStorage.setItem(clave, valor); } catch { /* Las preferencias son opcionales. */ } };
const sincronizar = () => {
    const abierto = raiz.classList.contains('menu-abierto');
    abrir.setAttribute('aria-expanded', String(abierto));
    menu.inert = movil.matches && !abierto;
    fondo.hidden = !movil.matches || !abierto;
    contraer.setAttribute('aria-expanded', String(!raiz.classList.contains('menu-contraido')));
    contraer.setAttribute('aria-label', raiz.classList.contains('menu-contraido') ? 'Expandir menú' : 'Contraer menú');
    tema.setAttribute('aria-pressed', String(raiz.dataset.bsTheme === 'dark'));
};
const cerrar = () => { raiz.classList.remove('menu-abierto'); sincronizar(); abrir.focus(); };
abrir.addEventListener('click', () => {
    raiz.classList.toggle('menu-abierto'); sincronizar();
    if (raiz.classList.contains('menu-abierto')) menu.querySelector('a').focus();
});
fondo.addEventListener('click', cerrar);
document.addEventListener('keydown', e => {
    if (!movil.matches || !raiz.classList.contains('menu-abierto')) return;
    if (e.key === 'Escape') { e.preventDefault(); cerrar(); }
    if (e.key === 'Tab') {
        const enlaces = [...menu.querySelectorAll('a[href]')];
        if (e.shiftKey && document.activeElement === enlaces[0]) { e.preventDefault(); enlaces.at(-1).focus(); }
        else if (!e.shiftKey && document.activeElement === enlaces.at(-1)) { e.preventDefault(); enlaces[0].focus(); }
    }
});
contraer.addEventListener('click', () => {
    raiz.classList.toggle('menu-contraido'); sincronizar();
    guardar('envirosense-menu', raiz.classList.contains('menu-contraido') ? 'contraido' : 'expandido');
});
tema.addEventListener('click', () => {
    raiz.dataset.bsTheme = raiz.dataset.bsTheme === 'dark' ? 'light' : 'dark';
    guardar('envirosense-tema', raiz.dataset.bsTheme); sincronizar();
});
movil.addEventListener('change', () => { raiz.classList.remove('menu-abierto'); sincronizar(); });
sincronizar();
