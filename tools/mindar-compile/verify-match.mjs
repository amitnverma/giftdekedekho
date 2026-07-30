/**
 * Recognition self-test for a compiled .mind target.
 *
 * Usage:  node verify-match.mjs <target.mind> <query-image> [--verbose]
 *
 * Runs the same detect-then-match stages the browser runs on each camera frame,
 * but headlessly against a still image. Answers the question the trackability
 * score can only estimate: does this target actually recognise this photo?
 *
 * Use it to sanity-check a frame without holding a phone up to a screen, or to
 * work out whether a customer's "it doesn't scan" complaint is the target or the
 * conditions. A pass here is a necessary condition, not a substitute for the
 * live camera test — real cameras add motion blur, glare and perspective.
 *
 * Exit code 0 = matched, 1 = no match or error.
 */

import fs from 'fs';
import { createCanvas, loadImage } from 'canvas';
import { OfflineCompiler } from 'mind-ar/src/image-target/offline-compiler.js';
import { Detector } from 'mind-ar/src/image-target/detector/detector.js';
import { Matcher } from 'mind-ar/src/image-target/matching/matcher.js';
import * as tf from '@tensorflow/tfjs';

const [, , targetPath, queryPath, ...flags] = process.argv;
const verbose = flags.includes('--verbose');

if (!targetPath || !queryPath) {
  console.error('Usage: node verify-match.mjs <target.mind> <query-image> [--verbose]');
  process.exit(1);
}

/** Load an image as the greyscale buffer the detector expects. */
async function greyscale(path, maxEdge) {
  const image = await loadImage(fs.readFileSync(path));
  let { width, height } = image;
  if (maxEdge && Math.max(width, height) > maxEdge) {
    const scale = maxEdge / Math.max(width, height);
    width = Math.round(width * scale);
    height = Math.round(height * scale);
  }
  const canvas = createCanvas(width, height);
  const context = canvas.getContext('2d');
  context.drawImage(image, 0, 0, width, height);
  const { data } = context.getImageData(0, 0, width, height);

  const grey = new Uint8Array(width * height);
  for (let i = 0; i < grey.length; i++) {
    const o = i * 4;
    grey[i] = Math.floor((data[o] + data[o + 1] + data[o + 2]) / 3);
  }
  return { data: grey, width, height };
}

async function main() {
  const compiler = new OfflineCompiler();
  const dataList = compiler.importData(fs.readFileSync(targetPath).buffer);
  if (!dataList.length) {
    console.error('FAIL  The .mind file is empty or was built by a different compiler version.');
    process.exit(1);
  }

  const { matchingData, targetImage } = dataList[0];
  console.log(`target    ${targetImage.width}x${targetImage.height}, ${matchingData.length} keyframes`);

  // Simulate a camera frame: phone cameras deliver something in this ballpark,
  // and the query size affects matching, so don't feed the full-resolution photo.
  const query = await greyscale(queryPath, 640);
  console.log(`query     ${query.width}x${query.height}`);

  const detector = new Detector(query.width, query.height);
  const featurePoints = tf.tidy(() => {
    const input = tf
      .tensor(query.data, [query.data.length], 'float32')
      .reshape([query.height, query.width]);
    return detector.detect(input).featurePoints;
  });
  console.log(`detected  ${featurePoints.length} feature points in the query image`);

  const matcher = new Matcher(query.width, query.height);
  const result = matcher.matchDetection(matchingData, featurePoints);

  if (result.keyframeIndex === -1) {
    console.log('\nFAIL  No match. This target will not trigger on this image.');
    process.exit(1);
  }

  console.log(`matched   keyframe #${result.keyframeIndex} with ${result.screenCoords.length} corresponding points`);
  if (verbose) {
    for (let i = 0; i < Math.min(5, result.screenCoords.length); i++) {
      const s = result.screenCoords[i];
      const w = result.worldCoords[i];
      console.log(`          screen(${s.x.toFixed(1)}, ${s.y.toFixed(1)}) -> world(${w.x.toFixed(1)}, ${w.y.toFixed(1)})`);
    }
  }
  console.log('\nPASS  Target recognised the image.');
}

main().catch((err) => {
  console.error('FAIL  ' + (err && err.message ? err.message : String(err)));
  process.exit(1);
});
