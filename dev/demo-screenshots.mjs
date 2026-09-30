#!/usr/bin/env node
/**
 * Walk the live demo (dev/live-demo.md) in a browser and capture every view it
 * shows, numbered by walkthrough step, for backup slides.
 *
 *   make demo-screenshots                       # after `make demo-db` and `make serve`
 *   node dev/demo-screenshots.mjs [--base URL] [--out DIR] [--register] [--headed]
 *
 * It plays the demo rather than only visiting it: the author submits
 * submission 11, the editor saves its CODECHECK tab and records a status. So
 * run it on a freshly loaded demo dataset, and load it again afterwards before
 * presenting: `make demo-db FORCE=1`.
 *
 * --register also reserves a certificate identifier in the testing register
 * and captures the GitHub issue it opens. That creates a real issue and uses up
 * an identifier, so it is off unless asked for, and it needs the GitHub token
 * `make demo-db` stores. Without it, step 2 is captured up to
 * the reservation button: the form refuses to save without a certificate
 * identifier and its register issue, so the codechecker is added but not saved,
 * and the status views are taken from submission 9 instead.
 *
 * A view that cannot be captured is reported and skipped, so one changed
 * selector does not cost the rest of the set; the exit code is non-zero if any
 * was skipped.
 */

import { commandLine, launchBrowser, login } from './browser.mjs';
import { mkdir, rm } from 'node:fs/promises';
import path from 'node:path';

const { flag, option } = commandLine();

const base = option('--base', 'http://localhost:8350').replace(/\/$/, '');
const journal = `${base}/index.php/codecheck`;
const outDir = path.resolve(option('--out', 'dev/out/demo'));
const withRegister = flag('--register');

const workflow = (id) => `${journal}/dashboard/editorial?workflowSubmissionId=${id}&workflowMenuKey=codecheck`;

const browser = await launchBrowser(!flag('--headed'));
const skipped = [];
let shots = 0;

const WIDTH = 1600;
const SCREEN_HEIGHT = 1000;

/** A logged-in (or anonymous) browser window of its own, as in the walkthrough. */
async function openWindow(user, height = SCREEN_HEIGHT) {
  // The workflow's CODECHECK tab scrolls inside OJS's own panel, where a
  // full-page capture sees one screen; a tall window shows all of it.
  const context = await browser.newContext({ viewport: { width: WIDTH, height } });
  const page = await context.newPage();
  if (user) {
    // Every account's password is its username.
    await login(page, journal, user, user);
  }
  return page;
}

async function settle(page, ms = 1500) {
  // Short: a page that keeps polling would otherwise hold every step for 30 s.
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(ms);
}

/** Capture the page, or one element of it. */
async function shot(page, name, { element, fullPage = false, top = false } = {}) {
  const file = path.join(outDir, `${name}.png`);
  if (top) {
    // The editor's window is tall for the form; a dialog needs only its top.
    await page.screenshot({ path: file, clip: { x: 0, y: 0, width: WIDTH, height: SCREEN_HEIGHT } });
  } else if (element) {
    await page.locator(element).first().screenshot({ path: file });
  } else {
    await page.screenshot({ path: file, fullPage });
  }
  shots++;
  console.log(`  ${name}.png`);
}

/**
 * One view of the walkthrough, captured as `<name>.png` by the `capture` it is
 * handed; a failure is recorded and the run goes on.
 */
async function view(name, action) {
  try {
    await action((page, options) => shot(page, name, options));
  } catch (error) {
    skipped.push(`${name}: ${error.message.split('\n')[0]}`);
    console.log(`  ${name} — skipped`);
  }
}

const dialog = (page) => page.locator('[data-cy=dialog]').last();

/** Save the CODECHECK form, and fail the view if the form refused to. */
async function saveForm(page) {
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await page.locator('.save-message.success', { hasText: 'saved successfully' }).waitFor({ timeout: 20000 }).catch(async () => {
    const said = await page.locator('.save-message').first().textContent().catch(() => null);
    throw new Error(`the form did not save${said ? `: ${said.trim()}` : ''}`);
  });
  await settle(page, 2000);
}

async function closeDialog(page) {
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);
}

async function openCodecheckTab(page, id) {
  await page.goto(workflow(id), { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.codecheck-metadata-form', { timeout: 30000 });
  await settle(page, 2500);
}


// The walkthrough submits 11 and records statuses. On anything but a freshly
// loaded demo that would write into the test dataset's fixtures, so stop.
const editor = await openWindow('admin', 3200);
const draft = await editor.request.get(`${journal}/api/v1/submissions/11`);
if (!draft.ok() || !(await draft.json()).submissionProgress) {
  console.error('Submission 11 is not the demo\'s draft. Load the demo first: make demo-db FORCE=1');
  await browser.close();
  process.exit(2);
}

await rm(outDir, { recursive: true, force: true });
await mkdir(outDir, { recursive: true });
console.log(`Capturing the demo at ${journal} into ${outDir}`);

// --- 0. CODECHECK in 30 seconds -------------------------------------------

const reader = await openWindow(null);
await view('00-info-page', async (capture) => {
  await reader.goto(`${journal}/codecheck/info`);
  await settle(reader);
  await capture(reader, { fullPage: true });
});

// --- 1. The author submits ------------------------------------------------

const author = await openWindow('fostermann');

await view('1a-start-form-opt-in', async (capture) => {
  await author.goto(`${journal}/submission`);
  await settle(author);
  await author.getByText('Yes, I want my paper to be codechecked').first().scrollIntoViewIfNeeded();
  await capture(author, { fullPage: true });
});

await view('1b-draft-details', async (capture) => {
  await author.goto(`${journal}/submission?id=11`);
  await author.waitForSelector('input[type=url]', { timeout: 30000 });
  await settle(author, 2500);
  await capture(author);
});

await view('1c-codecheck-section-filled', async (capture) => {
  const urls = author.locator('input[type=url]');
  await urls.nth(0).fill('https://github.com/nuest/reproducible-research-giscience-longitudinal-study');
  await author.getByText('+ Add URL').click();
  await urls.nth(1).fill('https://doi.org/10.5281/zenodo.21097308');
  // No comment: the plugin stores it as part of the file name for now.
  await author.fill('input[placeholder^="Filename"]', 'outputs/AGILE_pre_post.png');
  await author.fill('textarea[placeholder^="Describe where"]',
    'Code and data are available on GitHub and archived on Zenodo.');
  await capture(author, { element: '#codecheck-submission-fields' });
});

await view('1d-review-step', async (capture) => {
  // Details → Upload Files → Contributors → For the Editors → Review
  for (let step = 0; step < 4; step++) {
    await author.getByRole('button', { name: 'Continue' }).last().click();
    await settle(author, 2000);
  }
  await capture(author, { fullPage: true });
});

await view('1e-submitted', async (capture) => {
  await author.getByRole('button', { name: 'Submit' }).last().click();
  await author.waitForTimeout(1000);
  const confirm = author.locator('[role=dialog] button', { hasText: /Submit|Yes|OK/ }).last();
  if (await confirm.count()) {
    await confirm.click();
  }
  await settle(author, 3000);
  await capture(author);
});

// --- 2. The editor gets it checked, and the register follows --------------


await view('2a-dashboard', async (capture) => {
  await editor.goto(`${journal}/dashboard/editorial?currentViewId=active`);
  await settle(editor, 3000);
  await capture(editor, { top: true });
});

await view('2b-codecheck-tab-new-submission', async (capture) => {
  await openCodecheckTab(editor, 11);
  await capture(editor, { element: '.codecheck-metadata-form' });
});

await view('2c-certificate-identifier', async (capture) => {
  await capture(editor, { element: '.certificate-identifier-section' });
});

let issueUrl = null;
if (withRegister) {
  await view('2d-identifier-reserved', async (capture) => {
    await editor.locator('.dropbtn').first().click();
    await editor.locator('.dropdown-checkbox-input input[type=checkbox]').first().check();
    await editor.getByRole('button', { name: 'Reserve Identifier Automatically' }).click();
    // A register with no identifier yet asks first; so may the reservation itself.
    const yes = editor.locator('[data-cy=dialog] button', { hasText: /Yes/ });
    if (await yes.count()) {
      await yes.last().click();
    }
    await editor.waitForSelector('text=View GitHub Issue', { timeout: 60000 });
    issueUrl = await editor.getByText('View GitHub Issue').first().getAttribute('href');
    // The form saves nothing without a summary; saving records the first status.
    await editor.locator('textarea[placeholder^="Successfully reproduced"]').fill('Check in progress.');
    await saveForm(editor);
    await capture(editor, { element: '.certificate-identifier-section' });
  });
}

await view('2e-codechecker-refused', async (capture) => {
  await editor.locator('.field-label', { hasText: /codechecker/i }).locator('..').locator('.btn-add').first().click();
  await dialog(editor).locator('input[id^=codecheck-checker-name]').fill('Josiah Carberry');
  await dialog(editor).locator('input[id^=codecheck-checker-orcid]').fill('0000-0002-1825-0098');
  await dialog(editor).locator('.modal-actions button', { hasText: 'Add' }).click();
  await dialog(editor).locator('.modal-field-error').waitFor();
  await capture(editor, { element: '[data-cy=dialog]' });
});

await view('2f-codechecker-added', async (capture) => {
  // ORCID's own test identity, with its correct check digit.
  await dialog(editor).locator('input[id^=codecheck-checker-orcid]').fill('0000-0002-1825-0097');
  await dialog(editor).locator('.modal-actions button', { hasText: 'Add' }).click();
  await editor.waitForTimeout(800);
  if (withRegister) {
    await saveForm(editor);
  }
  await capture(editor, { element: '.codecheck-metadata-form' });
});

await view('2g-status-change-dialog', async (capture) => {
  // Without a reservation 11 could not be saved, so it has no status to change.
  await openCodecheckTab(editor, withRegister ? 11 : 9);
  await editor.getByRole('button', { name: 'Change', exact: true }).first().click();
  const select = dialog(editor).locator('select');
  await select.selectOption('plugins.generic.codecheck.status.stalled.author');
  await capture(editor, { element: '[data-cy=dialog]' });
  if (withRegister) {
    await dialog(editor).locator('.modal-actions button', { hasText: 'Change' }).click();
    await settle(editor, 3000);
  } else {
    // 9 is only borrowed: its check stays running, which is what 3d refuses.
    await closeDialog(editor);
  }
});

await view('2h-status-history', async (capture) => {
  await editor.getByRole('button', { name: 'History', exact: true }).first().click();
  await editor.waitForTimeout(1500);
  await capture(editor, { element: '[data-cy=dialog]' });
  await closeDialog(editor);
});

if (issueUrl) {
  await view('2i-register-issue', async (capture) => {
    const github = await openWindow(null);
    await github.goto(issueUrl, { waitUntil: 'domcontentloaded' });
    await settle(github, 3000);
    await capture(github, { fullPage: true });
    await github.context().close();
  });
}

// --- 3. Publishing with CODECHECK ------------------------------------------

await view('3a-completed-check', async (capture) => {
  await openCodecheckTab(editor, 8);
  await capture(editor, { element: '.codecheck-metadata-form' });
});

await view('3b-yaml-preview', async (capture) => {
  await editor.locator('[data-testid="preview-yaml-button"]').click();
  await editor.locator('.yaml-preview-content').waitFor({ timeout: 20000 });
  await capture(editor, { element: '[data-cy=dialog]' });
  await closeDialog(editor);
});

await view('3c-hide-refused', async (capture) => {
  await editor.locator('.repository-item .repo-hidden-checkbox').first().click();
  await editor.waitForTimeout(1000);
  await capture(editor, { element: '.codecheck-metadata-form' });
});

await view('3d-publish-refused', async (capture) => {
  await editor.goto(`${journal}/dashboard/editorial?workflowSubmissionId=9&workflowMenuKey=publication_titleAbstract`,
    { waitUntil: 'domcontentloaded' });
  await settle(editor, 3000);
  await editor.getByRole('button', { name: 'Schedule For Publication' }).first().click();
  await settle(editor, 3000);
  await capture(editor, { top: true });
  await closeDialog(editor);
});

// --- 4. What readers see ---------------------------------------------------

/** A public page, the whole of it, in the reader's window. */
const readerView = (name, urlPath) => view(name, async (capture) => {
  await reader.goto(`${journal}/${urlPath}`);
  await settle(reader);
  await capture(reader, { fullPage: true });
});

await readerView('4a-issue-toc', 'issue/view/1');
await readerView('4b-article-2', 'article/view/2');
await view('4c-article-2-sidebar', async (capture) => {
  await capture(reader, { element: '[data-testid="codecheck-article-sidebar"]' });
});
await readerView('4d-article-7-private-repository', 'article/view/7');
await readerView('4e-article-10-in-progress', 'article/view/10');

// --- 5. Optional: journal configuration ------------------------------------

await view('5a-settings', async (capture) => {
  await editor.goto(`${journal}/management/settings/website`, { waitUntil: 'domcontentloaded' });
  // The row's action links are collapsed, so the link is clicked from the page.
  const link = editor.locator('a[href*="verb=settings"][href*="plugin=codecheckplugin"]').first();
  await link.waitFor({ state: 'attached', timeout: 30000 });
  await link.evaluate((element) => element.click());
  await editor.locator('form#codecheckSettings').waitFor({ timeout: 30000 });
  await settle(editor, 1500);
  await capture(editor, { element: 'form#codecheckSettings' });
});

await browser.close();

console.log(`\n${shots} screenshots in ${outDir}`);
if (skipped.length) {
  console.log(`${skipped.length} view(s) skipped:`);
  skipped.forEach((line) => console.log(`  ${line}`));
  process.exit(1);
}
