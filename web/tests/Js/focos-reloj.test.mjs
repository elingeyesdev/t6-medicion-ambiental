import test from 'node:test';
import assert from 'node:assert/strict';
import { crearRelojServidor } from '../../public/js/focos-calor/reloj.js';
import { periodoReciente, limitesPeriodo } from '../../public/js/focos-calor/periodo.js';
import { aLocal, validarPeriodoLocal } from '../../public/js/focos-calor/contrato.js';

test('los siete días terminan en el servidor aunque el navegador esté adelantado o atrasado', () => {
 const original = Date.now;
 try {
  for (const cliente of ['2028-01-01T00:00:00Z','2020-01-01T00:00:00Z']) {
   Date.now = () => Date.parse(cliente);
   const reloj = crearRelojServidor('2026-11-12T19:20:30Z', () => 100);
   const rango = periodoReciente(7,reloj.ahora());
   assert.equal(rango.hasta,'2026-11-12T19:20:30.000Z');
   assert.equal(Date.parse(rango.hasta)-Date.parse(rango.desde),168*3600000);
   assert.deepEqual(validarPeriodoLocal(aLocal(rango.desde),aLocal(rango.hasta),reloj.ahora()),{desde:'2026-11-05T19:20:30Z',hasta:'2026-11-12T19:20:30Z'});
  }
 } finally { Date.now = original; }
});
test('el presente avanza con tiempo transcurrido sin depender de cambios del reloj civil',()=>{
 let transcurrido=100;const reloj=crearRelojServidor('2026-11-12T19:20:30Z',()=>transcurrido);
 transcurrido=3600100;assert.equal(reloj.ahora(),Date.parse('2026-11-12T20:20:30Z'));
 reloj.sincronizar('2026-11-12T18:00:00Z');assert.equal(reloj.ahora(),Date.parse('2026-11-12T18:00:00Z'));
});
test('la edición manual sigue rechazando un segundo futuro y limita el calendario al servidor',()=>{
 const reloj=crearRelojServidor('2026-11-12T19:20:30Z',()=>0);
 assert.throws(()=>validarPeriodoLocal('2026-11-06T15:20:30','2026-11-12T15:20:31',reloj.ahora()),/futuros/);
 assert.equal(limitesPeriodo('2026-11-05T15:20:30','',reloj.ahora()).hasta.max,'2026-11-12T15:20:30');
});
test('la hora en otra zona representa el mismo instante y los cruces de año conservan 168 horas',()=>{
 const reloj=crearRelojServidor('2027-01-01T00:30:00-04:00',()=>0);const r=periodoReciente(7,reloj.ahora());
 assert.equal(r.hasta,'2027-01-01T04:30:00.000Z');assert.equal(r.desde,'2026-12-25T04:30:00.000Z');
});
test('una hora ausente o sin zona no cae en el reloj del equipo ni corrompe la sincronización anterior',()=>{
 for(const valor of [null,undefined,'','invalida','2026-11-12T19:20:30'])assert.throws(()=>crearRelojServidor(valor),/hora/);
 const reloj=crearRelojServidor('2026-11-12T19:20:30Z',()=>0);assert.throws(()=>reloj.sincronizar('invalida'),/hora/);assert.equal(reloj.ahora(),Date.parse('2026-11-12T19:20:30Z'));
});
