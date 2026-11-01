import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { once } from 'node:events';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../../..');
const temp = path.join(root, 'tmp/mqtt-local66');
const npmDir = path.join(temp, 'npm');
const args = process.argv.slice(2);
const install = args.includes('--instalar');
const options = {};
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--instalar') continue;
  if (!['--php', '--php-ini', '--npm-cli'].includes(args[i]) || !args[i + 1]) {
    throw Error('Uso: node ejecutar.mjs --php RUTA_ABSOLUTA --php-ini RUTA_ABSOLUTA [--instalar] [--npm-cli RUTA_ABSOLUTA]');
  }
  options[args[i].slice(2)] = args[++i];
}
for (const key of ['php', 'php-ini']) {
  assert(options[key] && path.isAbsolute(options[key]), 'Se exige ruta absoluta para '+key);
  await fs.access(options[key]);
}
if (process.platform === 'win32') assert.match(options.php, /\.exe$/i);
await fs.access(path.join(root, 'web/vendor/autoload.php'));
const children = new Set();
const envBase = Object.fromEntries(Object.entries(process.env).filter(([k]) => /^(SystemRoot|WINDIR|TEMP|TMP)$/i.test(k)));

// Ejecutables reales, argumentos separados; ningún shell ni ventana de consola.
function launch(exe, argv, env, timeoutMs = 20000, cwd = root) {
  const child = spawn(exe, argv, { cwd, env, windowsHide: true, shell: false, stdio: ['ignore', 'pipe', 'pipe'] });
  children.add(child);
  let stdout = '', stderr = '';
  let timedOut = false;
  child.stdout.setEncoding('utf8').on('data', chunk => { stdout += chunk; });
  child.stderr.setEncoding('utf8').on('data', chunk => { stderr += chunk; });
  const timer = setTimeout(() => { timedOut = true; child.kill(); }, timeoutMs);
  const done = new Promise((resolve, reject) => {
    child.once('error', reject);
    child.once('close', (code, signal) => {
      clearTimeout(timer);
      children.delete(child);
      if (timedOut) reject(Error('Proceso excedió su límite: '+path.basename(exe)));
      else resolve({ code, signal, stdout, stderr });
    });
  });
  // El rechazo sigue disponible para el consumidor, sin rechazo no atendido al esperar eventos.
  done.catch(() => {});
  return { child, done };
}
if (install) {
  const npmCli = options['npm-cli'] ?? path.join(path.dirname(process.execPath), 'node_modules/npm/bin/npm-cli.js');
  assert(path.isAbsolute(npmCli));
  await fs.access(npmCli);
  await fs.mkdir(npmDir, { recursive: true });
  for (const f of ['package.json', 'package-lock.json']) await fs.copyFile(path.join(here, f), path.join(npmDir, f));
  for (const f of ['user.npmrc', 'global.npmrc']) await fs.writeFile(path.join(npmDir, f), '');
  const installed = await launch(process.execPath, [npmCli, 'ci', '--prefix', npmDir, '--ignore-scripts', '--no-audit', '--no-fund',
    '--registry=https://registry.npmjs.org', '--userconfig='+path.join(npmDir, 'user.npmrc'),
    '--globalconfig='+path.join(npmDir, 'global.npmrc'), '--cache='+path.join(temp, 'npm-cache')], envBase, 60000, npmDir).done;
  if (installed.code !== 0) throw Error(installed.stderr || installed.stdout);
  console.log('Dependencias privadas instaladas mediante npm ci --ignore-scripts.');
}
const requireLocal = createRequire(path.join(npmDir, 'package.json'));
for (const name of ['aedes', 'mqtt']) {
  const wanted = JSON.parse(await fs.readFile(path.join(here, 'package.json'), 'utf8')).dependencies[name];
  assert.equal(requireLocal(name+'/package.json').version, wanted, 'Versión de '+name);
}
assert.equal(await fs.readFile(path.join(npmDir, 'package-lock.json'), 'utf8'), await fs.readFile(path.join(here, 'package-lock.json'), 'utf8'), 'Reinstalar: lock privado distinto');
const aedes = requireLocal('aedes')();
const mqtt = requireLocal('mqtt');
const sockets = new Set();
const server = net.createServer(socket => {
  sockets.add(socket);
  socket.once('close', () => sockets.delete(socket));
  aedes.handle(socket);
});
const runs = path.join(temp, 'runs');
await fs.mkdir(runs, { recursive: true });
const run = await fs.mkdtemp(path.join(runs, 'run-'));
await fs.writeFile(path.join(run, '.mqtt66-owned'), 'Solo datos sintéticos MQTT66\n');
await fs.writeFile(path.join(run, 'prueba.sqlite'), '', { flag: 'wx' });
for (const f of ['cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']) {
  await fs.mkdir(path.join(run, f), { recursive: true });
}
const topics = ['geosur/mqtt66/temperatura', 'geosur/mqtt66/humedad'];
const map = {
  [topics[0]]: { punto: 'mqtt66-a', metrica: 'temperatura', unidad: '°C', origen: 'simulado', fuente: 'mqtt-local66' },
  [topics[1]]: { punto: 'mqtt66-b', metrica: 'humedad', unidad: '%', origen: 'simulado', fuente: 'mqtt-local66' },
};
const traces = { subscriptions: [], acks: [], clients: [], errors: [] };
aedes.on('subscribe', (subs, client) => traces.subscriptions.push({ client: client.id, subs }));
aedes.on('ack', (packet, client) => traces.acks.push({ client: client.id, topic: packet?.topic ?? null, payload: packet?.payload?.toString() ?? null, messageId: packet?.messageId ?? null }));
aedes.on('client', client => traces.clients.push(client.id));
aedes.on('clientError', (client, error) => traces.errors.push({ client: client.id, message: error.message }));
aedes.on('connectionError', (client, error) => traces.errors.push({ client: client?.id, message: error.message }));
let publisher;
let stopped = false;
const results = [];
const report = { formato: 'geosur.mqtt-local66.v1', inicio: new Date().toISOString(),
  baseObjetivo: 'b994b643d1b9086abd695053eb3a27030ed238b3', node: process.version,
  versiones: { aedes: requireLocal('aedes/package.json').version, mqtt: requireLocal('mqtt/package.json').version },
  alcance: 'LOCAL: broker real en loopback y payloads sintéticos; sin sensores físicos, broker externo ni TLS',
  casos: results, trazas: traces };
let env;
let actor;
function php(action, settings = {}, extra = {}) {
  if (interrupted) throw Error('Ejecución interrumpida');
  const job = launch(options.php, ['-c', options['php-ini'], '-d', 'disable_functions=exec,shell_exec,system,passthru,proc_open,popen', path.join(here, 'laravel.php'), action],
    { ...env, ...extra, MQTT66_OPTIONS: JSON.stringify(settings) });
  return { ...job, done: job.done.then(raw => {
    assert.equal(raw.stderr, '', raw.stderr);
    let data;
    try { data = JSON.parse(raw.stdout); } catch { throw Error('Salida PHP no JSON: '+raw.stdout); }
    assert.equal(raw.code, data.exit, 'Código del proceso y del comando');
    return data;
  }) };
}
async function snapshot() {
  const s = (await php('snapshot').done).result;
  assert.equal(s.cuentas, 1); assert.equal(s.puntos, 2);
  assert.equal(s.avisos, 0); assert.equal(s.envios, 0); assert.equal(s.suscripciones, 0);
  return s;
}
async function waitUntil(predicate, label, job, ms = 8000) {
  const end = Date.now() + ms;
  while (!predicate()) {
    if (interrupted) throw Error('Ejecución interrumpida');
    if (job?.child.exitCode !== null && job?.child.exitCode !== undefined) {
      throw Error(label+': proceso terminó antes del evento: '+JSON.stringify(await job.done));
    }
    if (Date.now() > end) throw Error('Timeout: '+label);
    await new Promise(resolve => setTimeout(resolve, 20));
  }
}
function receiver(id, max, extra = {}, settings = {}) {
  const job = php('receive', { '--actor': actor, '--conectar': true, '--max-mensajes': max, '--segundos': 10, '--no-interaction': true, ...settings },
    { GEOSUR_MQTT_CLIENTE: id, ...extra });
  return { ...job, id };
}
async function subscribed(job) {
  await waitUntil(() => traces.subscriptions.some(s => s.client === job.id), 'suscripción '+job.id, job);
  const sub = traces.subscriptions.find(s => s.client === job.id);
  assert.deepEqual(sub.subs.map(s => ({ topic: s.topic, qos: s.qos })), topics.map(topic => ({ topic, qos: 1 })));
}
const payload = (valor, instante = '2026-10-27T12:00:00Z') => JSON.stringify({ valor, instante });
async function publish(topic, body, settings = {}) {
  await publisher.publishAsync(topic, body, { qos: 1, retain: false, ...settings });
}
async function acked(job, n) {
  await waitUntil(() => traces.acks.filter(a => a.client === job.id).length >= n, 'PUBACK '+job.id, job);
}
async function test(name, execute) {
  const start = Date.now();
  try {
    const detail = await execute();
    results.push({ nombre: name, ok: true, ms: Date.now() - start, ...detail });
    console.log('OK '+name);
  } catch (error) {
    results.push({ nombre: name, ok: false, error: error.message });
    throw error;
  }
}
async function cleanup() {
  if (stopped) return;
  stopped = true;
  const pending = [...children].map(child => {
    const closed = once(child, 'close').catch(() => {});
    child.kill();
    return closed;
  });
  await Promise.all(pending);
  for (const socket of sockets) socket.destroy();
  if (publisher) await publisher.endAsync(true);
  if (server.listening) await new Promise(resolve => server.close(resolve));
  await new Promise(resolve => aedes.close(resolve));
  report.limpieza = { brokerCerrado: aedes.closed, servidorCerrado: !server.listening,
    procesosPropios: children.size, sockets: sockets.size };
}
let interrupted = false;
let runtimeError;
const abort = error => {
  runtimeError = error instanceof Error ? error : Error(String(error));
  interrupted = true;
  for (const child of children) child.kill();
  for (const socket of sockets) socket.destroy();
};
const onSignal = () => abort(Error('Ejecución interrumpida'));
process.once('uncaughtException', abort);
process.once('unhandledRejection', abort);
process.once('SIGINT', onSignal);
process.once('SIGTERM', onSignal);
try {
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen({ host: '127.0.0.1', port: 0 }, resolve);
  });
  const address = server.address();
  assert.equal(address.address, '127.0.0.1');
  report.broker = { host: address.address, puerto: address.port, persistencia: 'memoria, efímera' };
  env = { ...envBase, COLUMNS: '100', LINES: '40', TERM: 'dumb', NO_COLOR: '1', APP_ENV: 'testing', APP_DEBUG: 'false', APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    DB_CONNECTION: 'sqlite', DB_DATABASE: run+'/prueba.sqlite', DB_URL: '', MQTT66_RUN: run,
    MAIL_MAILER: 'array', SESSION_DRIVER: 'array', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync',
    GEOSUR_TIEMPO_HABILITADO: 'false', GEOSUR_MQTT_HABILITADO: 'true', GEOSUR_MQTT_URL: 'mqtt://127.0.0.1:'+address.port,
    GEOSUR_MQTT_USUARIO: '', GEOSUR_MQTT_CLAVE: '', GEOSUR_MQTT_CA: '', GEOSUR_MQTT_ESPERA: '2',
    GEOSUR_MQTT_TEMAS: JSON.stringify(map) };
  const prepared = await php('prepare').done;
  assert.equal(prepared.exit, 0); assert.equal(prepared.result.puntos, 2);
  actor = prepared.result.actor;
  report.php = prepared.php; report.laravel = prepared.laravel;
  publisher = await mqtt.connectAsync(env.GEOSUR_MQTT_URL, { protocolVersion: 4, clientId: 'mqtt66-publicador',
    clean: true, reconnectPeriod: 0, connectTimeout: 3000 });
  publisher.on('error', error => traces.errors.push({ client: 'mqtt66-publicador', message: error.message }));

  await test('01 captura dos variables, puntos, UTC, procedencia y actor', async () => {
    const job = receiver('mqtt66-captura', 2); await subscribed(job);
    await publish(topics[0], payload(25.5)); await acked(job, 1);
    await publish(topics[1], payload(65));
    const r = await job.done; assert.equal(r.exit, 0); assert.match(r.output, /Mensajes: 2. Nuevas: 2. Repetidas: 0./);
    await acked(job, 2);
    const s = await snapshot(); assert.equal(s.lecturas.length, 2);
    for (const [i, l] of s.lecturas.entries()) {
      assert.equal(l.punto_id, i + 1); assert.equal(l.metrica, ['temperatura', 'humedad'][i]);
      assert.equal(Number(l.valor), [25.5, 65][i]); assert.equal(l.instante, '2026-10-27 12:00:00');
      assert.equal(l.hora_utc, '2026-10-27 12:00:00'); assert.equal(l.origen, 'simulado'); assert.equal(l.fuente, 'mqtt-local66');
      assert.equal(l.registrado_por, actor); assert.equal(Number(l.latitud), [-17.1, -17.2][i]); assert.equal(Number(l.longitud), [-63.1, -63.2][i]);
    }
    return { comando: r, filas: s.lecturas, pubacks: 2 };
  });
  await test('02 repetición y zona equivalente no duplican', async () => {
    const before = await snapshot(); const job = receiver('mqtt66-dedup', 2); await subscribed(job);
    await publish(topics[0], payload(25.5, '2026-10-27T08:00:00-04:00')); await acked(job, 1);
    await publish(topics[1], payload(65)); const r = await job.done; await acked(job, 2);
    assert.equal(r.exit, 0); assert.match(r.output, /Nuevas: 0. Repetidas: 2./);
    assert.deepEqual(await snapshot(), before); return { comando: r, pubacks: 2 };
  });
  await test('03 retained en dos arranques conserva instante y deduplica', async () => {
    const before = await snapshot();
    await publish(topics[0], payload(25.5), { retain: true }); await publish(topics[1], payload(65), { retain: true });
    for (let i = 0; i < 2; i++) {
      const job = receiver('mqtt66-retained'+i, 2); const r = await job.done; await acked(job, 2);
      assert.equal(r.exit, 0); assert.match(r.output, /Nuevas: 0. Repetidas: 2./);
    }
    assert.deepEqual(await snapshot(), before);
    for (const topic of topics) await publish(topic, '', { retain: true });
    return { pubacks: 4 };
  });
  await test('04 conflicto sin PUBACK conserva valor y commits previos', async () => {
    const job = receiver('mqtt66-conflicto', 2); await subscribed(job);
    await publish(topics[1], payload(64, '2026-10-27T12:01:00Z')); await acked(job, 1);
    await publish(topics[0], payload(26)); const r = await job.done;
    assert.equal(r.exit, 2); assert.match(r.output, /Se conservó el dato anterior/); assert.match(r.output, /Nuevas: 1. Repetidas: 0./);
    const s = await snapshot(); assert.equal(s.lecturas.length, 3); assert.equal(Number(s.lecturas[0].valor), 25.5);
    assert.equal(traces.acks.filter(a => a.client === job.id).length, 1); return { comando: r, pubacks: 1 };
  });
  for (const [name, body] of [
    ['05 JSON inválido', '{'], ['06 valor fuera de rango', payload(61)],
    ['07 fecha futura', payload(25, '2999-01-01T00:00:00Z')],
    ['08 instante sin zona', payload(25, '2026-10-27T12:00:00')],
    ['09 campos extra no controlan destino', JSON.stringify({ valor: 25, instante: '2026-10-27T12:00:00Z', punto: 'mqtt66-b' })],
    ['10 contenido superior a 4096 bytes', ' '.repeat(4097)],
  ]) {
    await test(name, async () => {
      const before = await snapshot(); const job = receiver('mqtt66-error'+results.length, 1); await subscribed(job);
      await publish(topics[0], body); const r = await job.done;
      assert.equal(r.exit, 2); assert.deepEqual(await snapshot(), before);
      assert.equal(traces.acks.filter(a => a.client === job.id).length, 0); return { comando: r, pubacks: 0 };
    });
  }
  await test('11 punto inexistente no se crea', async () => {
    const before = await snapshot(); const changed = structuredClone(map); changed[topics[0]].punto = 'mqtt66-no-existe';
    const job = receiver('mqtt66-sin-punto', 1, { GEOSUR_MQTT_TEMAS: JSON.stringify(changed) }); await subscribed(job);
    await publish(topics[0], payload(24)); const r = await job.done;
    assert.equal(r.exit, 2); assert.deepEqual(await snapshot(), before);
    assert.equal(traces.acks.filter(a => a.client === job.id).length, 0); return { comando: r, pubacks: 0 };
  });
  await test('12 punto inactivo no persiste ni confirma', async () => {
    const before = await snapshot(); await php('point', { activo: false }).done;
    try {
      const job = receiver('mqtt66-inactivo', 1); await subscribed(job); await publish(topics[1], payload(64));
      const r = await job.done; assert.equal(r.exit, 2); assert.deepEqual(await snapshot(), before);
      assert.equal(traces.acks.filter(a => a.client === job.id).length, 0); return { comando: r, pubacks: 0 };
    } finally { await php('point', { activo: true }).done; }
  });
  await test('13 revalida cuenta entre mensajes y conserva primero', async () => {
    const before = await snapshot(); const job = receiver('mqtt66-actor', 2); await subscribed(job);
    await publish(topics[0], payload(24, '2026-10-27T12:02:00Z')); await acked(job, 1);
    await php('actor', { activo: false }).done;
    try {
      await publish(topics[1], payload(63, '2026-10-27T12:02:00Z')); const r = await job.done;
      assert.equal(r.exit, 2); assert.match(r.output, /cuenta activa/);
      const s = await snapshot(); assert.equal(s.lecturas.length, before.lecturas.length+1);
      assert.equal(traces.acks.filter(a => a.client === job.id).length, 1); return { comando: r, pubacks: 1 };
    } finally { await php('actor', { activo: true }).done; }
  });
  await test('14 recepción deshabilitada no conecta', async () => {
    const before = await snapshot(); const job = receiver('mqtt66-apagado', 1, { GEOSUR_MQTT_HABILITADO: 'false' });
    const r = await job.done; assert.equal(r.exit, 2); assert.match(r.output, /deshabilitada/);
    assert(!traces.clients.includes(job.id)); assert.deepEqual(await snapshot(), before); return { comando: r };
  });
  await test('15 sin consentimiento explícito no conecta', async () => {
    const job = receiver('mqtt66-sin-conectar', 1, {}, { '--conectar': false }); const r = await job.done;
    assert.equal(r.exit, 2); assert(!traces.clients.includes(job.id)); return { comando: r };
  });
  await test('16 tema ajeno no llega; límite finito con cero capturas', async () => {
    const before = await snapshot(); const job = receiver('mqtt66-ajeno', 1, {}, { '--segundos': 2 }); await subscribed(job);
    await publish('geosur/mqtt66/ajeno', payload(23)); const r = await job.done;
    assert.equal(r.exit, 0); assert.match(r.output, /Mensajes: 0. Nuevas: 0. Repetidas: 0./);
    assert.deepEqual(await snapshot(), before); return { comando: r, pubacks: 0 };
  });
  await test('17 QoS 0 guarda sin prometer PUBACK', async () => {
    const before = await snapshot(); const job = receiver('mqtt66-qos0', 1); await subscribed(job);
    await publish(topics[1], payload(62, '2026-10-27T12:03:00Z'), { qos: 0 }); const r = await job.done;
    assert.equal(r.exit, 0); assert.equal((await snapshot()).lecturas.length, before.lecturas.length+1);
    assert.equal(traces.acks.filter(a => a.client === job.id).length, 0); return { comando: r, pubacks: 0 };
  });
  await test('18 sesión persistente entrega mensaje publicado desconectado', async () => {
    const before = await snapshot(); const id = 'mqtt66-persistente'; const first = receiver(id, 1); await subscribed(first);
    await publish(topics[0], payload(25.5)); assert.equal((await first.done).exit, 0); await acked(first, 1);
    await waitUntil(() => !aedes.clients[id], 'desconexión persistente');
    await publish(topics[1], payload(61, '2026-10-27T12:04:00Z'));
    const resumed = receiver(id, 1); const r = await resumed.done; await acked(resumed, 2);
    assert.equal(r.exit, 0); assert.match(r.output, /Nuevas: 1. Repetidas: 0./);
    assert.equal((await snapshot()).lecturas.length, before.lecturas.length+1); return { comando: r, pubacks: 2 };
  });
  await test('19 rechazo del broker no guarda', async () => {
    const before = await snapshot();
    aedes.authenticate = (client, username, password, cb) => cb(null, client.id !== 'mqtt66-rechazo');
    const job = receiver('mqtt66-rechazo', 1); const r = await job.done;
    assert.equal(r.exit, 2); assert.match(r.output, /rechazó la conexión/);
    assert.deepEqual(await snapshot(), before); return { comando: r, pubacks: 0 };
  });
  report.estadoFinal = await snapshot();
  report.ok = true;
} catch (error) {
  report.ok = false; report.error = (runtimeError ?? error).stack;
  console.error(error.stack); process.exitCode = 1;
} finally {
  try {
    await cleanup();
    if (report.broker) {
      const probe = net.connect({ host: '127.0.0.1', port: report.broker.puerto });
      report.limpieza.puertoCerrado = await new Promise(resolve => {
        probe.once('connect', () => { probe.destroy(); resolve(false); });
        probe.once('error', error => resolve(error.code === 'ECONNREFUSED'));
        probe.setTimeout(1000, () => { probe.destroy(); resolve(false); });
      });
      assert.equal(report.limpieza.puertoCerrado, true);
      assert.equal(report.limpieza.procesosPropios, 0);
    }
  } catch (error) {
    report.ok = false; report.errorLimpieza = error.stack; process.exitCode = 1;
  }
  report.fin = new Date().toISOString();
  report.huellas = {};
  for (const file of [
    'herramientas/datos/mqtt-local/ejecutar.mjs', 'herramientas/datos/mqtt-local/laravel.php',
    'herramientas/datos/mqtt-local/package.json', 'herramientas/datos/mqtt-local/package-lock.json',
    'web/app/Console/Commands/RecibirMqtt.php', 'web/app/Services/Datos/Mqtt/ClienteMqtt.php',
    'web/app/Services/Datos/Mqtt/ConfiguracionMqtt.php', 'web/app/Services/Datos/Mqtt/IngerirMensajeMqtt.php',
    'web/app/Services/Datos/RegistrarLecturas.php', 'web/app/Services/Datos/AutorizarIngestion.php',
  ]) {
    report.huellas[file] = createHash('sha256').update(await fs.readFile(path.join(root, file))).digest('hex');
  }
  await fs.writeFile(path.join(run, 'resultado.json'), JSON.stringify(report, null, 2)+'\n');
  console.log(JSON.stringify({ ok: report.ok, casos: results.length, resultado: path.join(run, 'resultado.json'), limpieza: report.limpieza }));
  process.removeListener('uncaughtException', abort); process.removeListener('unhandledRejection', abort);
  process.removeListener('SIGINT', onSignal); process.removeListener('SIGTERM', onSignal);
}
