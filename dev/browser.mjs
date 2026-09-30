/**
 * Browser launch shared by the Playwright scripts in dev/.
 *
 * Prefer Playwright's bundled Chromium, but fall back to the system Chrome so
 * the scripts work without `npx playwright install` — the bundled build is
 * version locked to the playwright package and gets out of step after an
 * upgrade.
 */

import { chromium } from 'playwright';

export async function launchBrowser(headless = true) {
  try {
    return await chromium.launch({ headless });
  } catch (bundledError) {
    for (const channel of ['chrome', 'chromium']) {
      try {
        const browser = await chromium.launch({ headless, channel });
        console.log(`(using system ${channel}; run 'npx playwright install chromium' for the bundled build)`);
        return browser;
      } catch {
        // try the next channel
      }
    }
    throw bundledError;
  }
}

/** `--name value` options and `--name` flags from the command line. */
export function commandLine(args = process.argv.slice(2)) {
  return {
    flag: (name) => args.includes(name),
    option: (name, fallback) => {
      const i = args.indexOf(name);
      return i !== -1 && args[i + 1] ? args[i + 1] : fallback;
    },
  };
}

/** Log into a journal, `journalUrl` being `…/index.php/<journal>`. */
export async function login(page, journalUrl, user, pass) {
  await page.goto(`${journalUrl}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle').catch(() => {});
}
