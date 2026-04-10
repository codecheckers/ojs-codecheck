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
    indexHtmlFile: 'cypress/support/component-index.html'
  },
    e2e: {
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:8888/ojs',
    specPattern: 'cypress/tests/e2e/**/*.cy.js',
    supportFile: 'cypress/support/e2e.js'
  }
});