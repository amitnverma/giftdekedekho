/**
 * Localhost-only HTTP wrapper around compile.mjs, for hosts where PHP's
 * shell_exec/proc_open is disabled.
 *
 * Usage:  node server.mjs
 * Env:    GDD_AR_PORT   (default 9077)
 *         GDD_AR_HOST   (default 127.0.0.1 — do not bind publicly)
 *         GDD_AR_TOKEN  (shared secret; must match ar_compiler_token in config/local.php)
 *
 * Protocol:
 *   GET  /health                     -> {"ok":true,"service":"gdd-mindar-compile"}
 *   POST /compile  {input, output}   -> the same JSON payload compile.mjs emits
 *
 * `input` and `output` are absolute paths on the server's own filesystem — the
 * PHP side has already written the uploaded photo to disk, so no image bytes
 * cross the wire. Both paths are required to sit inside GDD_AR_ROOT.
 */

import http from 'http';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawn } from 'child_process';

const RESULT_MARKER = '__GDD_RESULT__';
const HERE = path.dirname(fileURLToPath(import.meta.url));

const PORT = parseInt(process.env.GDD_AR_PORT || '9077', 10);
const HOST = process.env.GDD_AR_HOST || '127.0.0.1';
const TOKEN = process.env.GDD_AR_TOKEN || '';
// Confines both paths to the application tree (tools/mindar-compile -> app root).
const ROOT = path.resolve(process.env.GDD_AR_ROOT || path.join(HERE, '..', '..'));

// One compile at a time. Each run is CPU-bound for several seconds, so letting
// them pile up would starve the walk-in flow that a customer is waiting on.
let busy = false;

function send(res, status, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function withinRoot(candidate) {
  const resolved = path.resolve(candidate);
  return resolved === ROOT || resolved.startsWith(ROOT + path.sep);
}

function runScript(script, args) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [path.join(HERE, script), ...args], {
      cwd: HERE,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    let stdout = '';
    child.stdout.on('data', (chunk) => { stdout += chunk; });
    child.stderr.resume(); // progress lines — drained so the pipe never fills

    const timer = setTimeout(() => child.kill('SIGKILL'), 120000);

    child.on('close', () => {
      clearTimeout(timer);
      const line = stdout.split('\n').find((l) => l.startsWith(RESULT_MARKER));
      if (!line) {
        resolve({ ok: false, error: 'Compiler produced no result.' });
        return;
      }
      try {
        resolve(JSON.parse(line.slice(RESULT_MARKER.length)));
      } catch (err) {
        resolve({ ok: false, error: 'Compiler result was not valid JSON.' });
      }
    });

    child.on('error', (err) => {
      clearTimeout(timer);
      resolve({ ok: false, error: 'Could not start the compiler.', detail: String(err.message) });
    });
  });
}

const server = http.createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    send(res, 200, { ok: true, service: 'gdd-mindar-compile' });
    return;
  }

  if (req.method !== 'POST' || (req.url !== '/compile' && req.url !== '/bundle')) {
    send(res, 404, { ok: false, error: 'Not found.' });
    return;
  }
  const action = req.url;

  if (TOKEN === '' || req.headers['x-gdd-token'] !== TOKEN) {
    send(res, 401, { ok: false, error: 'Unauthorized.' });
    return;
  }

  let body = '';
  let tooLarge = false;
  req.on('data', (chunk) => {
    body += chunk;
    // A bundle request carries one path per frame, so allow more room than a
    // compile request needs while still bounding the payload.
    if (body.length > 262144) { tooLarge = true; req.destroy(); }
  });

  req.on('end', async () => {
    if (tooLarge) return;

    let parsed;
    try {
      parsed = JSON.parse(body);
    } catch (err) {
      send(res, 400, { ok: false, error: 'Invalid JSON body.' });
      return;
    }

    const { output } = parsed;
    if (typeof output !== 'string' || !output || !withinRoot(output)) {
      send(res, 400, { ok: false, error: '"output" must be a path inside the application directory.' });
      return;
    }

    let script, args;

    if (action === '/compile') {
      const { input } = parsed;
      if (typeof input !== 'string' || !input || !withinRoot(input)) {
        send(res, 400, { ok: false, error: '"input" must be a path inside the application directory.' });
        return;
      }
      script = 'compile.mjs';
      args = [input, output];
    } else {
      const inputs = parsed.inputs;
      if (!Array.isArray(inputs) || inputs.length === 0) {
        send(res, 400, { ok: false, error: '"inputs" must be a non-empty array of paths.' });
        return;
      }
      if (!inputs.every((p) => typeof p === 'string' && p && withinRoot(p))) {
        send(res, 400, { ok: false, error: 'Every input path must be inside the application directory.' });
        return;
      }
      script = 'bundle-targets.mjs';
      args = [output, ...inputs];
    }

    if (busy) {
      send(res, 429, { ok: false, error: 'Compiler is busy. Try again in a moment.' });
      return;
    }

    busy = true;
    try {
      const result = await runScript(script, args);
      send(res, result.ok ? 200 : 422, result);
    } finally {
      busy = false;
    }
  });
});

server.listen(PORT, HOST, () => {
  process.stdout.write(`gdd-mindar-compile listening on http://${HOST}:${PORT} (root: ${ROOT})\n`);
  if (TOKEN === '') {
    process.stderr.write('WARNING: GDD_AR_TOKEN is not set — every request will be rejected.\n');
  }
});
