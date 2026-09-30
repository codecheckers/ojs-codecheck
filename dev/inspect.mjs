#!/usr/bin/env node
/**
 * Ad-hoc page inspection against the local OJS dev server.
 *
 * Not a test suite — a debugging tool. It logs in, opens a URL, and writes a
 * screenshot, the rendered HTML, and the console/network log to dev/out/ so a
 * page can be examined without a browser.
 *
 *   node dev/inspect.mjs <url> [options]
 *   make inspect URL=http://localhost:8350/index.php/codecheck/dashboard/editorial
 *
 * Options:
 *   --user <name>     OJS user to log in as (default: admin)
 *   --pass <pass>     password (default: admin)
 *   --no-login        skip the login step (public pages)
 *   --out <name>      basename for the output files (default: derived from URL)
 *   --wait <ms>       extra settle time before capture (default: 2000)
 *   --headed          run with a visible browser
 *   --selector <sel>  wait for this selector before capturing
 *   --width <px>      viewport width (default: 1920 — wide enough for the
 *                     editorial dashboard, which Cypress screenshots clip)
 *   --height <px>     viewport height (default: 1200)
 *
 * Requires the dev server to be running (`make serve`).
 */

import { commandLine, launchBrowser, login } from './browser.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const args = process.argv.slice(2);

if (!args.length || args[0].startsWith('-')) {
  console.error('usage: node dev/inspect.mjs <url> [--user u] [--pass p] [--no-login] [--out name] [--wait ms] [--headed] [--selector sel]');
  process.exit(1);
}

const url = args[0];

const { flag, option } = commandLine(args);

const user = option('--user', 'admin');
const pass = option('--pass', 'admin');
const settle = Number(option('--wait', '2000'));
const selector = option('--selector', null);
const outDir = path.join(process.cwd(), 'dev', 'out');

const outName = option(
  '--out',
  new URL(url).pathname.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'page'
);

const origin = new URL(url).origin;

await mkdir(outDir, { recursive: true });

const headless = !flag('--headed');

const browser = await launchBrowser(headless);
const context = await browser.newContext({
  viewport: {
    width: Number(option('--width', '1920')),
    height: Number(option('--height', '1200')),
  },
});
const page = await context.newPage();

const consoleLines = [];
page.on('console', (msg) => consoleLines.push(`[${msg.type()}] ${msg.text()}`));
page.on('pageerror', (err) => consoleLines.push(`[pageerror] ${err.message}`));
page.on('requestfailed', (req) =>
  consoleLines.push(`[requestfailed] ${req.method()} ${req.url()} — ${req.failure()?.errorText}`)
);
page.on('response', (res) => {
  if (res.status() >= 400) {
    consoleLines.push(`[http ${res.status()}] ${res.url()}`);
  }
});

try {
  if (!flag('--no-login')) {
    // The journal path is part of the login URL; derive it from the target URL
    // when possible so this works for any journal.
    const journalMatch = new URL(url).pathname.match(/\/index\.php\/([^/]+)/);
    const journal = journalMatch ? journalMatch[1] : 'codecheck';

    await login(page, `${origin}/index.php/${journal}`, user, pass);
    console.log(`logged in as ${user}`);
  }

  await page.goto(url, { waitUntil: 'domcontentloaded' });

  if (selector) {
    await page.waitForSelector(selector, { timeout: 20000 });
  }

  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(settle);

  const screenshotPath = path.join(outDir, `${outName}.png`);
  const htmlPath = path.join(outDir, `${outName}.html`);
  const logPath = path.join(outDir, `${outName}.log`);

  await page.screenshot({ path: screenshotPath, fullPage: true });
  await writeFile(htmlPath, await page.content(), 'utf8');
  await writeFile(logPath, consoleLines.join('\n') + '\n', 'utf8');

  console.log(`title:      ${await page.title()}`);
  console.log(`screenshot: ${screenshotPath}`);
  console.log(`html:       ${htmlPath}`);
  console.log(`log:        ${logPath} (${consoleLines.length} lines)`);

  const problems = consoleLines.filter((l) => /^\[(error|pageerror|requestfailed|http )/.test(l));
  if (problems.length) {
    console.log(`\n${problems.length} problem(s):`);
    problems.slice(0, 20).forEach((l) => console.log(`  ${l}`));
  }
} finally {
  await browser.close();
}
