import {execFileSync, spawnSync} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../../..');
const reportFile = path.join(root, 'tmp/mqtt-local66/ci-informe.json');
fs.mkdirSync(path.dirname(reportFile), {recursive: true});
fs.rmSync(reportFile, {force: true});
const php = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(["exe" => PHP_BINARY, "ini" => php_ini_loaded_file()]);'], {encoding: 'utf8', windowsHide: true, shell: false}));
if (!php.ini || !path.isAbsolute(php.exe) || !fs.existsSync(php.ini)) throw new Error('Se necesita PHP configurado con SQLite y mbstring.');
const nodeDir = path.dirname(process.execPath);
const npmCli = process.platform === 'win32'
  ? path.join(nodeDir, 'node_modules/npm/bin/npm-cli.js')
  : path.resolve(nodeDir, '../lib/node_modules/npm/bin/npm-cli.js');
if (!fs.existsSync(npmCli)) throw new Error('No se encontró el archivo JavaScript de npm junto al runtime.');
const started = Date.now();
const result = spawnSync(process.execPath, [path.join(here, 'ejecutar.mjs'), '--php', php.exe, '--php-ini', php.ini, '--npm-cli', npmCli, '--instalar'], {
  cwd: root, windowsHide: true, shell: false, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024,
  stdio: ['ignore', 'pipe', 'pipe'], timeout: 240000,
});
process.stdout.write(result.stdout ?? '');
process.stderr.write(result.stderr ?? '');
if (result.error) throw result.error;
// Publicar solo el informe de este arranque; nunca reutilizar la evidencia histórica de docs/.
try {
  const lastLine = (result.stdout ?? '').trim().split(/\r?\n/).at(-1);
  const output = JSON.parse(lastLine);
  const source = path.resolve(output.resultado);
  const relative = path.relative(path.join(root, 'tmp/mqtt-local66/runs'), source);
  if (path.isAbsolute(relative) || relative === '..' || relative.startsWith('..' + path.sep) || path.basename(source) !== 'resultado.json') {
    throw new Error('El informe no pertenece al directorio privado de esta prueba.');
  }
  const report = JSON.parse(fs.readFileSync(source, 'utf8'));
  if (!Number.isFinite(Date.parse(report.inicio)) || Date.parse(report.inicio) < started - 1000) {
    throw new Error('El informe no corresponde a la ejecución actual.');
  }
  if (result.status === 0 && (report.ok !== true || report.casos?.length !== 19 || report.casos.some(test => test.ok !== true))) {
    throw new Error('La salida no acredita los 19 escenarios correctos.');
  }
  report.ci = {plataforma: process.platform, revisionGitHub: process.env.GITHUB_SHA ?? null, ejecucionGitHub: process.env.GITHUB_RUN_ID ?? null};
  fs.writeFileSync(reportFile, JSON.stringify(report, null, 2) + '\n');
  console.log('Informe de esta ejecución: ' + path.relative(root, reportFile));
} catch (error) {
  if (result.status === 0) throw error;
  console.error('No se publicó un informe completo: ' + error.message);
}
process.exit(result.status ?? 1);
