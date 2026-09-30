#!/usr/bin/env node
/**
 * Render the repository's social preview image (#41).
 *
 *   node dev/social-preview/render.mjs [output.png]
 *   make social-preview
 *
 * Opens social-preview.html beside this script at 1280x640 and writes a PNG,
 * by default to dev/out/social-preview.png. Needs no OJS: the page is static.
 * Upload the result by hand under the repository's Settings -> Social preview;
 * GitHub has no API for it.
 */

import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const source = path.join(here, 'social-preview.html');
const output = path.resolve(process.argv[2] ?? path.join(here, '..', 'out', 'social-preview.png'));

// Same fallback as dev/inspect.mjs: the bundled Chromium is version locked to
// the playwright package, so use the system browser when it is missing.
async function launchBrowser() {
  try {
    return await chromium.launch();
  } catch (bundledError) {
    for (const channel of ['chrome', 'chromium']) {
      try {
        return await chromium.launch({ channel });
      } catch {
        // try the next channel
      }
    }
    throw bundledError;
  }
}

await mkdir(path.dirname(output), { recursive: true });
const browser = await launchBrowser();
try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 640 } });
  await page.goto(pathToFileURL(source).href, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: output });
  console.log(output);
} finally {
  await browser.close();
}
