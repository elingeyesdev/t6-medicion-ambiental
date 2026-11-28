const paisaje = document.querySelector('.login-landscape');
const fotos = [...paisaje.querySelectorAll('[data-foto]')];
const creditos = [...paisaje.querySelectorAll('[data-credito]')];
let actual = 0;
let anterior;

function mostrarSiguiente() {
    for (let avance = 1; avance < fotos.length; avance++) {
        const siguiente = (actual + avance) % fotos.length;
        const foto = fotos[siguiente];
        if (!foto.complete || !foto.naturalWidth) continue;

        anterior?.classList.remove('foto-anterior');
        anterior = fotos[actual];
        anterior.classList.replace('foto-activa', 'foto-anterior');
        foto.classList.add('foto-activa');
        creditos[actual].inert = true;
        creditos[siguiente].inert = false;
        actual = siguiente;
        return;
    }
}

let reloj;
function programar() {
    clearInterval(reloj);
    if (!document.hidden && !paisaje.querySelector('figcaption :focus')) {
        reloj = setInterval(mostrarSiguiente, 5000);
    }
}

document.addEventListener('visibilitychange', programar);
paisaje.addEventListener('focusin', programar);
paisaje.addEventListener('focusout', () => queueMicrotask(programar));
programar();
