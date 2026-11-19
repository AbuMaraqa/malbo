/**
 * Runs Vitest (`npm run test:js`) from the project folder with an upper-case drive letter.
 *
 * Windows: started from `d:\…` (as Git Bash and some editors give the folder), Vitest loads itself
 * from that path while the test files import it through `D:\…`, two copies that do not share their
 * state, and every suite fails on `describe` ("Cannot read properties of undefined (reading
 * 'config')"). Elsewhere the folder is left as it is. Arguments pass through: `npm run test:js -- Cart`.
 */
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url)).replace(/^[a-z](?=:)/, (drive) => drive.toUpperCase());
const vitest = fileURLToPath(new URL('../node_modules/vitest/vitest.mjs', import.meta.url)).replace(/^[a-z](?=:)/, (drive) => drive.toUpperCase());

const result = spawnSync(process.execPath, [vitest, 'run', ...process.argv.slice(2)], { cwd: root, stdio: 'inherit' });

process.exit(result.status ?? 1);
