/**
 * Combines several compiled .mind targets into one file, for the public
 * "scan anything" page.
 *
 * Usage:  node bundle-targets.mjs <output.mind> <input1.mind> <input2.mind> ...
 *
 * This merges already-compiled data rather than recompiling: each target costs
 * about 4.5 seconds to compile but only a couple of milliseconds to decode and
 * re-encode, so a bundle of 25 frames rebuilds in well under a second instead of
 * nearly two minutes. The .mind format is a msgpack array of per-target entries,
 * so concatenating those arrays is all that is required — the entries themselves
 * are untouched and match exactly as they did individually.
 *
 * Target order in the output defines the anchor index the browser reports on a
 * match, so the caller's list order is preserved and echoed back in the result.
 *
 * Emits one marked JSON line, same convention as compile.mjs.
 */

import fs from 'fs';
import path from 'path';
import { OfflineCompiler } from 'mind-ar/src/image-target/offline-compiler.js';

const RESULT_MARKER = '__GDD_RESULT__';

function emit(payload, exitCode) {
  process.stdout.write('\n' + RESULT_MARKER + JSON.stringify(payload) + '\n');
  process.exit(exitCode);
}

function fail(message, detail) {
  emit({ ok: false, error: message, detail: detail ?? null }, 1);
}

const [, , outputPath, ...inputPaths] = process.argv;

if (!outputPath || inputPaths.length === 0) {
  fail('Usage: node bundle-targets.mjs <output.mind> <input.mind> [...]');
}
if (!fs.existsSync(path.dirname(outputPath))) {
  fail('Output directory does not exist.', path.dirname(outputPath));
}

const startedAt = Date.now();
const merged = [];
const included = [];
const skipped = [];

for (const inputPath of inputPaths) {
  if (!fs.existsSync(inputPath)) {
    // A missing target must not abandon the whole bundle — the other frames
    // are still perfectly scannable, and the caller is told what was dropped.
    skipped.push({ path: inputPath, reason: 'file not found' });
    continue;
  }
  try {
    const compiler = new OfflineCompiler();
    const dataList = compiler.importData(fs.readFileSync(inputPath).buffer);
    if (!dataList.length) {
      skipped.push({ path: inputPath, reason: 'empty or built by another compiler version' });
      continue;
    }
    for (const entry of dataList) {
      merged.push(entry);
      included.push(inputPath);
    }
  } catch (err) {
    skipped.push({ path: inputPath, reason: String(err && err.message) });
  }
}

if (merged.length === 0) {
  fail('No usable targets to bundle.', JSON.stringify(skipped));
}

try {
  const out = new OfflineCompiler();
  out.data = merged;
  fs.writeFileSync(outputPath, Buffer.from(out.exportData()));
} catch (err) {
  fail('Could not write the bundle.', String(err && err.message));
}

emit({
  ok: true,
  // Index in this array is the anchor index the browser will report.
  included,
  skipped,
  metrics: {
    targets: merged.length,
    bytes: fs.statSync(outputPath).size,
    elapsed_ms: Date.now() - startedAt,
  },
}, 0);
