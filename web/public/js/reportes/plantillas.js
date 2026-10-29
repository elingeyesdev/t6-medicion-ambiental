document.querySelectorAll('[data-filtros]').forEach((editor) => {
    const lista = editor.querySelector('[data-lista]');
    const agregar = editor.querySelector('[data-agregar]');
    const renumerar = () => {
        [...lista.children].forEach((fila, i) => fila.querySelectorAll('[name]').forEach((campo) => {
            campo.name = campo.name.replace(/condiciones\[[^\]]+\]/, `condiciones[${i}]`);
        }));
        agregar.disabled = lista.children.length >= 10;
    };
    agregar.addEventListener('click', () => {
        if (lista.children.length >= 10) return;
        lista.append(editor.querySelector('template').content.cloneNode(true));
        renumerar();
        lista.lastElementChild.querySelector('select').focus();
    });
    lista.addEventListener('click', (evento) => {
        const quitar = evento.target.closest('[data-quitar]');
        if (!quitar) return;
        quitar.closest('.rep-condicion').remove();
        renumerar();
        agregar.focus();
    });
    renumerar();
});
