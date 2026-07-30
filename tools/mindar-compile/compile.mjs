/**
 * Compiles a single photo into a MindAR .mind image target and reports how well
 * that photo is likely to track.
 *
 * Usage:  node compile.mjs <input-image> <output.mind>
 *
 * The result is printed as one JSON line prefixed with RESULT_MARKER. Anything
 * else on stdout/stderr (TensorFlow.js prints an unavoidable startup banner) is
 * ignored by the PHP caller, so library chatter can never corrupt the payload.
 *
 * Exit code is 0 on success, 1 on failure — a failure still emits a marked JSON
 * line carrying the error message so the admin panel can show something useful.
 */

import fs from 'fs';
import path from 'path';

const RESULT_MARKER = '__GDD_RESULT__';

// MindAR's own guidance is that targets around 1000px on the long edge give the
// best speed/quality trade-off. Larger images take much longer to compile and
// yield no tracking benefit, so scale down first. Aspect ratio is preserved
// because the .mind stores the target's dimensions and the AR plane uses them.
const MAX_EDGE = 1024;

function emit(payload, exitCode) {
  process.stdout.write('\n' + RESULT_MARKER + JSON.stringify(payload) + '\n');
  process.exit(exitCode);
}

function fail(message, detail) {
  emit({ ok: false, error: message, detail: detail ?? null }, 1);
}

/**
 * Turns the compiler's raw feature counts into a 0-100 trackability score.
 *
 * Calibrated against real product photos vs. deliberately untrackable images:
 *   real photos        1700-3300 matching points, 15-31 level-0 tracking points
 *   blurry low-contrast      121 matching points,  2 tracking points
 *   flat gradient              0 matching points,  0 tracking points
 *
 * The gap is roughly two orders of magnitude, so simple normalised thresholds
 * separate usable photos from ones that must be swapped before printing.
 */
function scoreTrackability(matchingTotal, trackingLevel0) {
  const matchComponent = Math.min(matchingTotal / 1500, 1);
  const trackComponent = Math.min(trackingLevel0 / 15, 1);
  const score = Math.round(100 * (0.5 * matchComponent + 0.5 * trackComponent));

  let flag = 'poor';
  if (score >= 60) flag = 'good';
  else if (score >= 30) flag = 'fair';

  return { score, flag };
}

async function main() {
  const [, , inputPath, outputPath] = process.argv;

  if (!inputPath || !outputPath) {
    fail('Usage: node compile.mjs <input-image> <output.mind>');
  }
  if (!fs.existsSync(inputPath)) {
    fail('Input image not found.', inputPath);
  }

  const outputDir = path.dirname(outputPath);
  if (!fs.existsSync(outputDir)) {
    fail('Output directory does not exist.', outputDir);
  }

  // Imported lazily so a missing/broken toolchain surfaces as our JSON error
  // rather than an unparseable Node module-resolution stack trace.
  let OfflineCompiler, loadImage, createCanvas;
  try {
    ({ OfflineCompiler } = await import('mind-ar/src/image-target/offline-compiler.js'));
    ({ loadImage, createCanvas } = await import('canvas'));
  } catch (err) {
    fail('MindAR compiler toolchain is not installed. Run "npm ci" in tools/mindar-compile.', String(err && err.message));
  }

  const startedAt = Date.now();

  let image;
  try {
    image = await loadImage(fs.readFileSync(inputPath));
  } catch (err) {
    fail('Could not read the image. Is it a valid JPG or PNG?', String(err && err.message));
  }

  const originalWidth = image.width;
  const originalHeight = image.height;

  // Downscale onto a canvas when needed; the compiler accepts anything with
  // width/height that can be drawn to a 2D context.
  let target = image;
  const longestEdge = Math.max(originalWidth, originalHeight);
  if (longestEdge > MAX_EDGE) {
    const scale = MAX_EDGE / longestEdge;
    const width = Math.round(originalWidth * scale);
    const height = Math.round(originalHeight * scale);
    const canvas = createCanvas(width, height);
    canvas.getContext('2d').drawImage(image, 0, 0, width, height);
    target = canvas;
  }

  let data;
  const compiler = new OfflineCompiler();
  try {
    // Progress goes to stderr so it can never be mistaken for the result line.
    data = await compiler.compileImageTargets([target], (percent) => {
      process.stderr.write(`progress ${Math.round(percent)}\n`);
    });
  } catch (err) {
    fail('Target compilation failed.', String(err && err.message));
  }

  const entry = data[0];
  const matchingTotal = entry.matchingData.reduce(
    (sum, keyframe) => sum + keyframe.maximaPoints.length + keyframe.minimaPoints.length,
    0
  );
  const trackingPoints = entry.trackingData.map((level) => level.points.length);
  const trackingLevel0 = trackingPoints[0] ?? 0;

  // A photo with no usable features still produces a valid (useless) .mind
  // file. Refuse to write it — a frame that can never match is worse than a
  // clear error, because it would look "generated" in the queue.
  if (matchingTotal === 0 || trackingLevel0 === 0) {
    fail(
      'This photo has no distinct features to track. Use a sharper, higher-contrast photo with more detail.',
      `matchingPoints=${matchingTotal} trackingPoints=${trackingLevel0}`
    );
  }

  try {
    fs.writeFileSync(outputPath, Buffer.from(compiler.exportData()));
  } catch (err) {
    fail('Could not write the .mind target file. Check directory permissions.', String(err && err.message));
  }

  const { score, flag } = scoreTrackability(matchingTotal, trackingLevel0);

  emit({
    ok: true,
    score,
    flag,
    metrics: {
      matching_points: matchingTotal,
      tracking_points: trackingPoints,
      keyframes: entry.matchingData.length,
      compiled_width: target.width,
      compiled_height: target.height,
      original_width: originalWidth,
      original_height: originalHeight,
      target_bytes: fs.statSync(outputPath).size,
      elapsed_ms: Date.now() - startedAt,
      compiler: 'mind-ar@1.2.5',
    },
  }, 0);
}

main().catch((err) => fail('Unexpected compiler error.', String(err && err.message)));
