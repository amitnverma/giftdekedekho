/**
 * Copies the browser-side AR libraries into public/js/vendor/mindar and rewrites
 * their bare import specifiers to relative paths.
 *
 * Usage:  node vendor-assets.mjs
 *
 * Why vendor instead of using a CDN:
 *   - A CDN import needs the phone to have working internet on whatever Wi-Fi
 *     it happens to be on. Ours is a camera page people open from a printed
 *     card, often in a shop or a living room with flaky signal.
 *   - Resolving mind-ar's bare "three" import from a CDN needs an import map,
 *     which needs iOS 16.4+.
 *   - If either failed, nothing ran and the page died silently.
 * Rewriting the specifiers removes the import map too, so the page needs neither
 * internet nor a recent iOS.
 *
 * Re-run this after changing the mind-ar or three version in package.json.
 * The three version is pinned deliberately: mind-ar 1.2.5 imports sRGBEncoding,
 * which was removed from three in later releases, and newer three also splits
 * itself into three.module.js + three.core.js.
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const NODE_MODULES = path.join(HERE, 'node_modules');
const DEST = path.resolve(HERE, '..', '..', 'public', 'js', 'vendor', 'mindar');

const MINDAR_DIST = path.join(NODE_MODULES, 'mind-ar', 'dist');
const ENTRY = 'mindar-image-three.prod.js';

/**
 * Rewrites bare specifiers so no import map is required.
 *
 * Both quote styles must be handled: mind-ar's bundled dist uses double quotes,
 * but three's examples/jsm sources (CSS3DRenderer) use single quotes. Matching
 * only one style silently leaves a bare "three" behind, which fails at runtime.
 */
const REWRITES = [
  [/from\s*["']three\/addons\/renderers\/CSS3DRenderer\.js["']/g, 'from"./CSS3DRenderer.js"'],
  [/from\s*["']three["']/g, 'from"./three.module.js"'],
];

/** Any import specifier that is not relative, absolute or a full URL. */
const BARE_SPECIFIER = /from\s*["']([^"'.\/][^"']*)["']/g;

function fail(message) {
  console.error('FAIL  ' + message);
  process.exit(1);
}

/** Collect the entry file plus every relative chunk it pulls in, recursively. */
function collectChunks(dir, entry, seen = new Set()) {
  if (seen.has(entry)) return seen;
  const full = path.join(dir, entry);
  if (!fs.existsSync(full)) fail('Missing dist file: ' + entry);
  seen.add(entry);

  const source = fs.readFileSync(full, 'utf8');
  for (const match of source.matchAll(/from\s*"(\.\/[^"]+)"/g)) {
    collectChunks(dir, match[1].replace(/^\.\//, ''), seen);
  }
  return seen;
}

function main() {
  if (!fs.existsSync(MINDAR_DIST)) fail('mind-ar is not installed. Run "npm ci" first.');

  const threeBuild = path.join(NODE_MODULES, 'three', 'build', 'three.module.js');
  const css3d = path.join(NODE_MODULES, 'three', 'examples', 'jsm', 'renderers', 'CSS3DRenderer.js');
  if (!fs.existsSync(threeBuild)) fail('three is not installed. Run "npm ci" first.');
  if (!fs.existsSync(css3d)) fail('three/examples CSS3DRenderer.js not found.');

  const threeVersion = JSON.parse(
    fs.readFileSync(path.join(NODE_MODULES, 'three', 'package.json'), 'utf8')
  ).version;

  // mind-ar 1.2.5 imports sRGBEncoding, removed from three after r0.15x.
  const threeSource = fs.readFileSync(threeBuild, 'utf8');
  if (!/\bsRGBEncoding\b/.test(threeSource)) {
    fail(`three ${threeVersion} does not export sRGBEncoding, which mind-ar needs. ` +
         'Pin an older three in package.json (0.155.0 is known good).');
  }
  if (/from\s*['"]\.\/three\.core\.js['"]/.test(threeSource)) {
    fail(`three ${threeVersion} splits into three.core.js, which this script does not handle. ` +
         'Pin three 0.155.0 in package.json.');
  }

  fs.mkdirSync(DEST, { recursive: true });

  const files = [...collectChunks(MINDAR_DIST, ENTRY)].map((name) => ({
    from: path.join(MINDAR_DIST, name), name,
  }));
  files.push({ from: threeBuild, name: 'three.module.js' });
  files.push({ from: css3d, name: 'CSS3DRenderer.js' });

  let rewritten = 0;
  for (const file of files) {
    let source = fs.readFileSync(file.from, 'utf8');
    for (const [pattern, replacement] of REWRITES) {
      if (pattern.test(source)) {
        source = source.replace(pattern, replacement);
        rewritten++;
      }
    }
    fs.writeFileSync(path.join(DEST, file.name), source);
    console.log(`  ${String(Math.round(source.length / 1024) + 'KB').padStart(8)}  ${file.name}`);
  }

  // Nothing may remain that a bare-specifier resolver (i.e. an import map)
  // would be needed for.
  const leftovers = [];
  for (const file of files) {
    const source = fs.readFileSync(path.join(DEST, file.name), 'utf8');
    for (const match of source.matchAll(BARE_SPECIFIER)) {
      if (!/^https?:/.test(match[1])) {
        leftovers.push(file.name + ' -> ' + match[1]);
      }
    }
  }
  if (leftovers.length) {
    fail('Bare specifiers remain (an import map would still be required):\n  ' + leftovers.join('\n  '));
  }

  console.log(`\nPASS  ${files.length} files vendored to public/js/vendor/mindar`);
  console.log(`      three ${threeVersion}, ${rewritten} specifier rewrite(s), no import map needed.`);
}

main();
