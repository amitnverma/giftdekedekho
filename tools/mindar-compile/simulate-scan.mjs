/**
 * Simulates what the browser actually does when scanning, headlessly.
 *
 * Usage:  node simulate-scan.mjs <target.mind> <photo> [--width=1280] [--height=720]
 *
 * verify-match.mjs answers "can this target be recognised at all" by searching a
 * whole image. That is more generous than reality and can pass on a target that
 * never fires on a phone.
 *
 * This tool reproduces the real pipeline instead. MindAR does NOT search the full
 * camera frame: Controller._detectAndMatch calls CropDetector.detectMoving(),
 * which searches a single crop of
 *
 *     cropSize = 2 ^ round(log2(min(width, height) / 2))
 *
 * ...and slides that window through 9 positions around the centre, one per frame.
 * So on a 1280x720 camera only a 256x256 window is examined per frame, and the
 * printed photo has to occupy a large, central part of the view.
 *
 * It then runs the same Matcher and Estimator the controller's worker runs. The
 * only stage not covered is frame-to-frame Tracker.track(), which uses WebGL-only
 * kernels and cannot run under Node — but detection is what decides whether
 * anything happens at all, which is the failure being diagnosed.
 *
 * Output is the smallest share of the frame the photo can fill and still be
 * recognised: the number that tells a customer how close to hold the phone.
 */

import fs from 'fs';
import { createCanvas, loadImage } from 'canvas';
import { OfflineCompiler } from 'mind-ar/src/image-target/offline-compiler.js';
import { CropDetector } from 'mind-ar/src/image-target/detector/crop-detector.js';
import { Matcher } from 'mind-ar/src/image-target/matching/matcher.js';
import { Estimator } from 'mind-ar/src/image-target/estimation/estimator.js';
import 'mind-ar/src/image-target/detector/kernels/cpu/index.js';
import * as tf from '@tensorflow/tfjs';

const args = process.argv.slice(2);
const targetPath = args[0];
const photoPath = args[1];
const flag = (name, fallback) => {
  const hit = args.find((a) => a.startsWith('--' + name + '='));
  return hit ? parseInt(hit.split('=')[1], 10) : fallback;
};
const INPUT_WIDTH = flag('width', 1280);
const INPUT_HEIGHT = flag('height', 720);

// How much of the frame's short edge the photo spans, largest first.
const FILL_RATIOS = [0.95, 0.85, 0.75, 0.65, 0.55, 0.45, 0.35, 0.25];

if (!targetPath || !photoPath) {
  console.error('Usage: node simulate-scan.mjs <target.mind> <photo> [--width=1280] [--height=720]');
  process.exit(1);
}

/** Render a simulated camera frame with the photo centred at a given size. */
function renderFrame(photo, fillRatio) {
  const canvas = createCanvas(INPUT_WIDTH, INPUT_HEIGHT);
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#787878';               // neutral wall behind the frame
  ctx.fillRect(0, 0, INPUT_WIDTH, INPUT_HEIGHT);

  const shortEdge = Math.min(INPUT_WIDTH, INPUT_HEIGHT);
  const drawH = Math.round(shortEdge * fillRatio);
  const drawW = Math.round(drawH * (photo.width / photo.height));
  ctx.drawImage(photo, (INPUT_WIDTH - drawW) / 2, (INPUT_HEIGHT - drawH) / 2, drawW, drawH);

  const { data } = ctx.getImageData(0, 0, INPUT_WIDTH, INPUT_HEIGHT);
  const grey = new Float32Array(INPUT_WIDTH * INPUT_HEIGHT);
  for (let i = 0; i < grey.length; i++) {
    const o = i * 4;
    // Matches InputLoader._loadInput: fromPixels().mean(2), values 0-255.
    grey[i] = (data[o] + data[o + 1] + data[o + 2]) / 3;
  }
  return { grey, drawW, drawH };
}

async function main() {
  const compiler = new OfflineCompiler();
  const dataList = compiler.importData(fs.readFileSync(targetPath).buffer);
  if (!dataList.length) {
    console.error('FAIL  Empty or incompatible .mind file.');
    process.exit(1);
  }
  const { matchingData, targetImage } = dataList[0];
  const photo = await loadImage(fs.readFileSync(photoPath));

  // Exactly how Controller builds its intrinsics.
  const fovy = (45.0 * Math.PI) / 180;
  const f = (INPUT_HEIGHT / 2) / Math.tan(fovy / 2);
  const projectionTransform = [
    [f, 0, INPUT_WIDTH / 2],
    [0, f, INPUT_HEIGHT / 2],
    [0, 0, 1],
  ];

  const cropDetector = new CropDetector(INPUT_WIDTH, INPUT_HEIGHT);
  const matcher = new Matcher(INPUT_WIDTH, INPUT_HEIGHT);
  const estimator = new Estimator(projectionTransform);

  console.log(`camera frame   ${INPUT_WIDTH}x${INPUT_HEIGHT}`);
  console.log(`crop window    ${cropDetector.cropSize}x${cropDetector.cropSize}  (only this much is searched per frame)`);
  console.log(`target         ${targetImage.width}x${targetImage.height}, ${matchingData.length} keyframes`);
  console.log(`photo          ${photo.width}x${photo.height}`);
  console.log('');
  console.log('  photo fills   photo px     frames to match   feature pts   result');

  let smallestPass = null;

  for (const ratio of FILL_RATIOS) {
    const { grey, drawW, drawH } = renderFrame(photo, ratio);

    let matchedOnFrame = null;
    let featureCount = 0;

    // One full crop cycle is 9 frames — give it two so a match is not missed
    // purely because of which position the window happened to start at.
    for (let frame = 0; frame < 18; frame++) {
      const matched = tf.tidy(() => {
        const inputT = tf.tensor(grey, [grey.length], 'float32').reshape([INPUT_HEIGHT, INPUT_WIDTH]);
        const { featurePoints } = cropDetector.detectMoving(inputT);
        featureCount = featurePoints.length;
        if (featurePoints.length === 0) return false;

        const { keyframeIndex, screenCoords, worldCoords } = matcher.matchDetection(matchingData, featurePoints);
        if (keyframeIndex === -1) return false;

        // The controller only counts it as a match when a pose can be estimated.
        return !!estimator.estimate({ screenCoords, worldCoords });
      });

      if (matched) { matchedOnFrame = frame + 1; break; }
    }

    const verdict = matchedOnFrame !== null ? 'MATCH' : 'no match';
    console.log(
      '  ' + String(Math.round(ratio * 100) + '%').padEnd(13) +
      String(drawW + 'x' + drawH).padEnd(13) +
      String(matchedOnFrame ?? '-').padEnd(18) +
      String(featureCount).padEnd(14) +
      verdict
    );

    if (matchedOnFrame !== null) smallestPass = ratio;
  }

  console.log('');
  if (smallestPass === null) {
    console.log('FAIL  Never matched at any size. This target will not fire on a phone.');
    process.exit(1);
  }
  console.log(`PASS  Recognised down to the photo filling ~${Math.round(smallestPass * 100)}% of the frame's short edge.`);
  console.log('      Below that, the phone has to be held closer.');
}

main().catch((err) => {
  console.error('FAIL  ' + (err && err.message ? err.message : String(err)));
  process.exit(1);
});
