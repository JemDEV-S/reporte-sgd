import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const applicationPath = fileURLToPath(new URL('../', import.meta.url));
const commands = {
    scheduler: ['artisan', 'schedule:work'],
    worker: ['artisan', 'queue:work', 'ingesta', '--queue=sgd', '--sleep=3', '--timeout=3600', '--tries=0', '--memory=256', '--no-interaction'],
};
const role = process.argv[2];
if (!Object.hasOwn(commands, role)) {
    console.error('Uso: node scripts/servicio.mjs scheduler|worker');
    process.exit(1);
}

let child;
let restartTimer;
let stopping = false;
function start() {
    child = spawn('php', commands[role], { cwd: applicationPath, stdio: 'inherit', windowsHide: true });
    child.on('error', (error) => console.error(`[${role}] No se pudo iniciar el servicio: ${error.message}`));
    child.on('close', (code) => {
        if (stopping) process.exit(0);
        console.error(`[${role}] Proceso finalizado (${code}). Reinicio en 3 segundos.`);
        restartTimer = setTimeout(start, 3000);
    });
}
function stop() {
    if (stopping) return;
    stopping = true;
    clearTimeout(restartTimer);
    if (child?.exitCode === null && child.pid) {
        child.kill('SIGTERM');
        setTimeout(() => child.kill('SIGKILL'), 5000).unref();
    } else {
        process.exit(0);
    }
}
for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) process.on(signal, stop);
start();
