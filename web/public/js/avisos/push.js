// El SDK solo se carga al pulsar Activar. No solicita permiso ni registra dispositivos al abrir la página.
const cargarSdk = () => Promise.all([
    import('https://www.gstatic.com/firebasejs/13.0.0/firebase-app.js'),
    import('https://www.gstatic.com/firebasejs/13.0.0/firebase-messaging.js')]);

export function iniciarPush(doc = document, entorno = globalThis, cargar = cargarSdk) {
    const form = doc.getElementById('avisos-push');
    if (!form) return;
    const estado = doc.getElementById('push-estado');
    async function guardar(datos) {
        const r = await entorno.fetch(form.action, { method: 'PUT', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            body: JSON.stringify(datos) });
        if (!r.ok || r.redirected) throw new Error('guardar');
        estado.textContent = (await r.json()).mensaje;
    }
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!doc.getElementById('push-consentimiento').checked) {
            estado.textContent = 'Confirma que deseas recibir avisos en este navegador.';
            return;
        }
        if (!entorno.isSecureContext || !entorno.Notification || !entorno.navigator?.serviceWorker) {
            estado.textContent = 'Este navegador no permite activar los avisos aquí.';
            return;
        }
        const boton = form.querySelector('[type="submit"]');
        boton.disabled = true;
        try {
            const permiso = await entorno.Notification.requestPermission();
            if (permiso !== 'granted') { estado.textContent = 'No se concedió permiso para mostrar avisos.'; return; }
            const [{ initializeApp }, { getMessaging, getToken, onMessage }] = await cargar();
            const config = JSON.parse(doc.getElementById('avisos-push-publico').textContent);
            const worker = await entorno.navigator.serviceWorker.register(form.dataset.worker, { scope: '/avisos' });
            const messaging = getMessaging(initializeApp(config.firebase, 'geosur-avisos'));
            const token = await getToken(messaging, { vapidKey: config.vapid, serviceWorkerRegistration: worker });
            await guardar({ activo: true, token, consentimiento: true });
            onMessage(messaging, () => { estado.textContent = 'Tienes un nuevo aviso. Consulta tu bandeja.'; });
        } catch { estado.textContent = 'No pudimos activar estos avisos. Inténtalo de nuevo.'; }
        finally { boton.disabled = false; }
    });
    doc.getElementById('push-desactivar').addEventListener('click', async () => {
        try { await guardar({ activo: false }); }
        catch { estado.textContent = 'No pudimos desactivar estos avisos. Inténtalo de nuevo.'; }
    });
}

if (typeof document !== 'undefined') iniciarPush();
