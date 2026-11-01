import {execFileSync, spawnSync} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../../..');
const php = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(["exe" => PHP_BINARY, "ini" => php_ini_loaded_file()]);'], {encoding: 'utf8', windowsHide: true, shell: false}));
if (!php.ini || !path.isAbsolute(php.exe) || !fs.existsSync(php.ini)) throw new Error('Se necesita PHP configurado con SQLite y mbstring.');
const nodeDir = path.dirname(process.execPath);
const npmCli = process.platform === 'win32'
  ? path.join(nodeDir, 'node_modules/npm/bin/npm-cli.js')
  : path.resolve(nodeDir, '../lib/node_modules/npm/bin/npm-cli.js');
if (!fs.existsSync(npmCli)) throw new Error('No se encontró el archivo JavaScript de npm junto al runtime.');
const result = spawnSync(process.execPath, [path.join(here, 'ejecutar.mjs'), '--php', php.exe, '--php-ini', php.ini, '--npm-cli', npmCli, '--instalar'], {
  cwd: root, windowsHide: true, shell: false, stdio: 'inherit', timeout: 240000,
});
if (result.error) throw result.error;
process.exit(result.status ?? 1);
