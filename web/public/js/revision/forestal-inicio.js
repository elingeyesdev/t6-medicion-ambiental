import { iniciarRevisionForestal } from './forestal.js';

// Espera a Leaflet defer, sin depender de herramientas de compilación.
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => iniciarRevisionForestal(), { once: true });
else iniciarRevisionForestal();
