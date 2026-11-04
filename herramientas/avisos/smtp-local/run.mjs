import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { randomBytes, createHash } from 'node:crypto';
import { createConnection } from 'node:net';
import { spawn, execFile } from 'node:child_process';
import { startCapture } from './smtp-capture.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../../..');
const args = process.argv.slice(2);
const option = (flag, fallback) => {
  const index = args.indexOf(flag);
  if (index < 0) return fallback;
  if (!args[index + 1] || args[index + 1].startsWith('--')) throw new Error('Missing value for ' + flag);
  return args[index + 1];
};
const php = option('--php', 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe');
const npmCli = option('--npm-cli', path.join(path.dirname(process.execPath), 'node_modules/npm/bin/npm-cli.js'));
const git = option('--git', 'C:/Program Files/Git/cmd/git.exe');
// El harness es autónomo: no depende de configuración personal de un editor.
function runHidden(executable, argv, options) {
  return new Promise((resolve, reject) => {
    execFile(executable, argv, { ...options, windowsHide: true, shell: false, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }, (error, stdout, stderr) => {
      if (error && typeof error.code !== 'number') return reject(error);
      resolve({ exitCode: error ? error.code : 0, stdout, stderr });
    });
  });
}
const privateRoot = path.join(root, 'tmp/smtp-local65');
await fs.mkdir(privateRoot, { recursive: true });
const privateDir = await fs.mkdtemp(path.join(privateRoot, 'run-'));
const deps = path.join(privateRoot, 'deps');
const evidence = path.join(root, 'docs/evidencias/smtp65');
await Promise.all([deps, evidence, 'cases', 'storage/logs', 'storage/framework/views', 'storage/framework/sessions',
  'storage/framework/cache/data', 'bootstrap'].map(folder => fs.mkdir(path.isAbsolute(folder) ? folder : path.join(privateDir, folder), { recursive: true })));

// Only OS plumbing is inherited; never inherit DB, mail, proxy, npm or application credentials.
const childEnv = {};
for (const [key, value] of Object.entries(process.env)) {
  if (/^(SystemRoot|WINDIR|TEMP|TMP|USERPROFILE|APPDATA|LOCALAPPDATA|HOMEDRIVE|HOMEPATH|NUMBER_OF_PROCESSORS|PROCESSOR_ARCHITECTURE)$/i.test(key)) childEnv[key] = value;
}
childEnv.PATH = path.dirname(process.execPath) + path.delimiter + path.dirname(php);
childEnv.PHP_INI_SCAN_DIR = '';
childEnv.COLUMNS = '100';
childEnv.LINES = '40';
const execute = async (exe, argv, extra = {}) => {
  const result = await runHidden(exe, argv, { cwd: root, env: childEnv, timeout: 60000, ...extra });
  if (result.exitCode !== 0) throw new Error(path.basename(exe) + ': ' + result.stderr + result.stdout);
  return result;
};
const hash = data => createHash('sha256').update(data).digest('hex');
const ini = path.join(privateDir, 'php.ini');
const extensionDir = path.join(path.dirname(php), 'ext').replaceAll('\\', '/');
await fs.writeFile(ini, 'extension_dir="' + extensionDir + '"\nextension=mbstring\nextension=pdo_sqlite\nextension=sqlite3\nextension=curl\nextension=openssl\ndate.timezone=UTC\n');
const disabledPhpFunctions = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
const phpArgs = ['-c', ini, '-d', 'disable_functions=' + disabledPhpFunctions.join(',')];
const phpLaunches = [];

// Every PHP call, including setup, version checks, PHPUnit and Pint, goes through this guard.
async function executePhpLogged(argv, env, label) {
  const stdoutPath = path.join(privateDir, label + '.stdout.log');
  const stderrPath = path.join(privateDir, label + '.stderr.log');
  const stdoutFile = await fs.open(stdoutPath, 'w');
  const stderrFile = await fs.open(stderrPath, 'w');
  const guardedEnv = { ...env, COLUMNS: '100', LINES: '40' };
  try {
    const child = spawn(php, [...phpArgs, ...argv], { cwd: path.join(root, 'web'), env: guardedEnv,
      windowsHide: true, shell: false, stdio: ['ignore', stdoutFile.fd, stderrFile.fd] });
    let timedOut = false;
    const timer = setTimeout(() => { timedOut = true; child.kill(); }, 180000);
    try {
      const exitCode = await new Promise((resolve, reject) => {
        child.once('error', reject);
        child.once('close', code => resolve(timedOut ? 124 : (code ?? 1)));
      });
      phpLaunches.push({ label, exitCode, timedOut, disabledFunctions: disabledPhpFunctions, columns: guardedEnv.COLUMNS, lines: guardedEnv.LINES });
      return { exitCode, stdout: await fs.readFile(stdoutPath, 'utf8'), stderr: await fs.readFile(stderrPath, 'utf8'), timedOut };
    } finally {
      clearTimeout(timer);
    }
  } finally {
    await stdoutFile.close();
    await stderrFile.close();
  }
}

await fs.access(path.join(root, 'web/vendor/autoload.php')); // independent local vendor is a prerequisite
await fs.writeFile(path.join(deps, '.npmrc'), 'registry=https://registry.npmjs.org/\nignore-scripts=true\n');
await fs.writeFile(path.join(privateRoot, 'empty.npmrc'), '');
await fs.writeFile(path.join(privateRoot, 'empty-global.npmrc'), '');
await fs.copyFile(path.join(here, 'package.json'), path.join(deps, 'package.json'));
await fs.copyFile(path.join(here, 'package-lock.json'), path.join(deps, 'package-lock.json'));
await execute(process.execPath, [npmCli, 'ci', '--prefix', deps, '--ignore-scripts', '--no-audit', '--no-fund',
  '--registry=https://registry.npmjs.org/', '--cache', path.join(privateRoot, 'npm-cache'),
  '--userconfig', path.join(privateRoot, 'empty.npmrc'), '--globalconfig', path.join(privateRoot, 'empty-global.npmrc')], { cwd: deps });

const phpProbe = await executePhpLogged(['-r',
  'echo json_encode(["version" => PHP_VERSION, "columns" => getenv("COLUMNS"), "lines" => getenv("LINES"), "availableFunctions" => array_values(array_filter(["exec", "shell_exec", "system", "passthru", "proc_open", "popen"], "function_exists"))]);'], childEnv, 'php-setup');
if (phpProbe.exitCode !== 0) throw new Error('Guarded PHP setup failed: ' + phpProbe.stderr);
const phpRuntime = JSON.parse(phpProbe.stdout);
if (phpRuntime.columns !== '100' || phpRuntime.lines !== '40' || phpRuntime.availableFunctions.length !== 0) {
  throw new Error('PHP console guards are required before booting Laravel.');
}

const snapshot = path.join(privateDir, 'smtp.json');
const capture = await startCapture(deps, snapshot);
let result;
let shutdown;
let testEnv;
try {
  const envValues = {
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_KEY: 'base64:' + randomBytes(32).toString('base64'),
    APP_URL: 'http://localhost', APP_NAME: 'GeoSur SMTP65', APP_MAINTENANCE_DRIVER: 'file',
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(privateDir, 'outbox.sqlite').replaceAll('\\', '/'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', LOG_CHANNEL: 'single',
    MAIL_MAILER: 'smtp', MAIL_SCHEME: 'smtp', MAIL_URL: 'null', MAIL_HOST: '127.0.0.1', MAIL_PORT: String(capture.state.port),
    MAIL_USERNAME: '', MAIL_PASSWORD: '', MAIL_TIMEOUT: '1', MAIL_EHLO_DOMAIN: 'cliente.example.test',
    MAIL_FROM_ADDRESS: 'avisos@example.test', MAIL_FROM_NAME: 'GeoSur SMTP65',
    AVISOS_PROCESAR: 'true', AVISOS_EMAIL_HABILITADO: 'true', AVISOS_EMAIL_MAILER: 'smtp',
    AVISOS_TELEGRAM_HABILITADO: 'false', AVISOS_WHATSAPP_HABILITADO: 'false', AVISOS_PUSH_HABILITADO: 'false',
    BCRYPT_ROUNDS: '4', BROADCAST_CONNECTION: 'null',
  };
  await fs.writeFile(path.join(privateDir, 'isolated.env'), Object.entries(envValues).map(([key, value]) => key + '=' + JSON.stringify(value)).join('\n') + '\n');
  await fs.writeFile(envValues.DB_DATABASE, '');
  testEnv = { ...childEnv, ...envValues, SMTP65_PRIVATE: privateDir, SMTP65_SNAPSHOT: snapshot,
    LARAVEL_STORAGE_PATH: path.join(privateDir, 'storage'),
    VIEW_COMPILED_PATH: path.join(privateDir, 'storage/framework/views'),
    APP_CONFIG_CACHE: path.relative(path.join(root, 'web'), path.join(privateDir, 'bootstrap/config.php')), APP_EVENTS_CACHE: path.relative(path.join(root, 'web'), path.join(privateDir, 'bootstrap/events.php')),
    APP_SERVICES_CACHE: path.relative(path.join(root, 'web'), path.join(privateDir, 'bootstrap/services.php')), APP_PACKAGES_CACHE: path.relative(path.join(root, 'web'), path.join(privateDir, 'bootstrap/packages.php')),
    APP_ROUTES_CACHE: path.relative(path.join(root, 'web'), path.join(privateDir, 'bootstrap/routes.php')), };
  result = await executePhpLogged([path.join(root, 'web/vendor/phpunit/phpunit/phpunit'),
    '--configuration', path.join(here, 'phpunit.xml'), '--log-junit', path.join(privateDir, 'phpunit.xml')], testEnv, 'phpunit');
  await fs.writeFile(path.join(evidence, 'phpunit.txt'), result.stdout + result.stderr);
  try { await fs.copyFile(path.join(privateDir, 'phpunit.xml'), path.join(evidence, 'phpunit.xml')); } catch (error) { if (error.code !== 'ENOENT') throw error; }
} finally {
  await capture.close();
  const portClosed = await new Promise(resolve => {
    const socket = createConnection({ host: '127.0.0.1', port: capture.state.port });
    socket.once('connect', () => { socket.destroy(); resolve(false); });
    socket.once('error', error => resolve(error.code === 'ECONNREFUSED'));
  });
  shutdown = { serverClosed: capture.state.closed, loopbackPortClosed: portClosed, openSmtpSessions: capture.state.openSessionsAtClose, separateBrokerProcess: false };
  await fs.writeFile(path.join(evidence, 'smtp.json'), JSON.stringify(capture.state, null, 2) + '\n');
  const cases = [];
  for (const filename of (await fs.readdir(path.join(privateDir, 'cases'))).sort()) cases.push(JSON.parse(await fs.readFile(path.join(privateDir, 'cases', filename), 'utf8')));
  const lock = await fs.readFile(path.join(here, 'package-lock.json'));
  const composerLock = JSON.parse(await fs.readFile(path.join(root, 'web/composer.lock'), 'utf8'));
  const head = (await execute(git, ['rev-parse', 'HEAD'])).stdout.trim();
  const branch = (await execute(git, ['branch', '--show-current'])).stdout.trim();
  const phpVersion = phpRuntime.version;
  const require = createRequire(path.join(deps, 'package.json'));
  const summary = { task: 132, originalTask: 65, scope: 'WEB', timestamp: new Date().toISOString(), head, branch, node: process.version, php: phpVersion,
    packages: Object.fromEntries(['smtp-server', 'nodemailer', 'ipv6-normalize', 'punycode.js'].map(name => [name, require(name + '/package.json').version])),
    composer: Object.fromEntries(composerLock.packages.filter(p => ['laravel/framework', 'symfony/mailer'].includes(p.name)).map(p => [p.name, p.version])),
    sourceHashes: Object.fromEntries(await Promise.all(['web/app/Services/Notificaciones/TransportarAviso.php',
      'herramientas/avisos/smtp-local/SmtpLocal65Test.php', 'herramientas/avisos/smtp-local/smtp-capture.mjs',
      'herramientas/avisos/smtp-local/run.mjs'].map(async file => [file, hash(await fs.readFile(path.join(root, file)))]))),
    phpConsoleGuards: { disabledFunctions: disabledPhpFunctions, columns: phpRuntime.columns, lines: phpRuntime.lines, availableFunctions: phpRuntime.availableFunctions, launches: phpLaunches },
    lockSha256: hash(lock), phpunitExitCode: result?.exitCode ?? null, cases, shutdown,
    limitations: ['Local SMTP protocol only; no external delivery or read receipt.',
      'Only typed SMTP 5xx within SmtpTransport::doSend are definite rejections; other exceptions stay uncertain.',
      'Uncertain case receives DATA but deliberately withholds final reply; not proof of accepted mail with lost acknowledgement.',
      'Second processor is reentrant in one PHP process; not an OS concurrency benchmark.',
      'No TLS/STARTTLS, AUTH, external accounts or providers tested.'] };
  await fs.writeFile(path.join(evidence, 'resultado.json'), JSON.stringify(summary, null, 2) + '\n');
}
console.log(result.stdout + result.stderr);
console.log('SMTP local cerrado: ' + JSON.stringify(shutdown));
console.log('Evidencias: docs/evidencias/smtp65/');
if (result.exitCode !== 0 || !shutdown.loopbackPortClosed || shutdown.openSmtpSessions !== 0) process.exitCode = 1;

// Run the existing regression only after closing the SMTP listener.
if (args.includes('--regresion') && result.exitCode === 0 && shutdown.loopbackPortClosed && shutdown.openSmtpSessions === 0) {
  let regression;
  try {
    for (const name of ['.env', '.env.testing']) {
      try { await fs.access(path.join(root, 'web', name)); throw new Error('Refusing existing web/' + name); }
      catch (error) { if (error.code !== 'ENOENT') throw error; }
    }
    const regressionEnv = { ...testEnv, DB_DATABASE: ':memory:', MAIL_MAILER: 'array',
      AVISOS_PROCESAR: 'false', AVISOS_EMAIL_HABILITADO: 'false', AVISOS_TELEGRAM_HABILITADO: 'false',
      AVISOS_WHATSAPP_HABILITADO: 'false', AVISOS_PUSH_HABILITADO: 'false' };
    const phpunit = await executePhpLogged([path.join(root, 'web/vendor/phpunit/phpunit/phpunit'),
      '--configuration', path.join(root, 'web/phpunit.xml'), '--do-not-cache-result',
      path.join(root, 'web/tests/Feature/AvisosExternosTest.php')], regressionEnv, 'regresion-php');
    await fs.writeFile(path.join(evidence, 'regresion-php.txt'), phpunit.stdout + phpunit.stderr);
    const pint = await executePhpLogged([path.join(root, 'web/vendor/laravel/pint/builds/pint'), '--test',
      path.join(root, 'web/app/Services/Notificaciones/TransportarAviso.php'), path.join(here, 'SmtpLocal65Test.php')], childEnv, 'pint');
    await fs.writeFile(path.join(evidence, 'pint.txt'), pint.stdout + pint.stderr);
    const js = await execute(process.execPath, ['--test', path.join(root, 'web/tests/Js/avisos.test.mjs'),
      path.join(root, 'web/tests/Js/push-avisos.test.mjs')]);
    await fs.writeFile(path.join(evidence, 'regresion-js.txt'), js.stdout + js.stderr);
    regression = { phpunit: { exitCode: phpunit.exitCode, timedOut: phpunit.timedOut },
      pint: { exitCode: pint.exitCode, timedOut: pint.timedOut }, js: { exitCode: js.exitCode } };
    if ([phpunit.exitCode, pint.exitCode, js.exitCode].some(code => code !== 0)) process.exitCode = 1;
  } catch (error) {
    regression = { error: error.message };
    process.exitCode = 1;
  } finally {
    const summary = JSON.parse(await fs.readFile(path.join(evidence, 'resultado.json'), 'utf8'));
    summary.regression = regression;
    summary.phpConsoleGuards.launches = phpLaunches;
    await fs.writeFile(path.join(evidence, 'resultado.json'), JSON.stringify(summary, null, 2) + '\n');
    console.log('Regresión con guardas: ' + JSON.stringify(regression));
  }
}
