import { defineConfig } from 'cypress';

export default defineConfig({
  component: {
    devServer: {
      framework: 'vue',
      bundler: 'vite',
      viteConfig: {
        configFile: 'vite.config.js'
      }
    },
    specPattern: 'cypress/tests/component/**/*.cy.js',
    supportFile: 'cypress/support/component.js',
    indexHtmlFile: 'cypress/support/component-index.html',
    setupNodeEvents(on, config) {
      on('task', {
        log(message) {
          console.log(message);
          return null;
        }
      });
    }
  },
  e2e: {
    // Default: the local dev server started by `make serve` (see README,
    // "Local development environment"). CI and other setups override this:
    //   CYPRESS_BASE_URL=http://localhost:8888/ojs npm run test:e2e
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:8350',
    specPattern: 'cypress/tests/e2e/**/*.cy.js',
    supportFile: 'cypress/support/e2e.js',
    // Screenshot specs under cypress/tests/visual/ are run separately via
    // `make screenshots`, which overrides specPattern.
    screenshotsFolder: 'cypress/screenshots',
    trashAssetsBeforeRuns: true,
    // Wide enough for the editorial workflow's three-column layout and the
    // article sidebar. Override with CYPRESS_VIEWPORT_WIDTH / _HEIGHT — a key
    // set here cannot be overridden by --config on the command line, but
    // environment variables do win.
    viewportWidth: 1920,
    viewportHeight: 1200,
    setupNodeEvents(on, config) {
      // cy.screenshot() crops to the browser *window*, not the viewport, and
      // under `cypress run` that window defaults to 1280x720 — which silently
      // cut off the dashboard's CODECHECK column and the workflow sidebar.
      // Size the window to whatever viewport is actually in effect.
      on('before:browser:launch', (browser, launchOptions) => {
        const width = config.viewportWidth;
        const height = config.viewportHeight;

        if (browser.name === 'electron') {
          // Electron preferences set the inner size exactly.
          launchOptions.preferences.width = width;
          launchOptions.preferences.height = height;
        } else if (browser.family === 'chromium') {
          // --window-size is the outer size; pad for the headless frame.
          launchOptions.args.push(`--window-size=${width},${height + 120}`);
        } else if (browser.family === 'firefox') {
          launchOptions.args.push('--width', String(width), '--height', String(height + 120));
        }

        return launchOptions;
      });

      return config;
    }
  }
});
