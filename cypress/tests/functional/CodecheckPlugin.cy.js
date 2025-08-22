/**
 * @file cypress/tests/functional/CodecheckPlugin.cy.js
 *
 * Copyright (c) 2024 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 */

describe('CODECHECK plugin tests', function() {
    it('Sets up the testing environment', function() {
        cy.login('admin', 'admin', 'publicknowledge');

        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();

        // Find and enable the plugin
        cy.get('input[id^="select-cell-codecheckplugin-enabled"]').click();
        cy.get('div:contains(\'The plugin "CODECHECK Plugin" has been enabled.\')');
    });
    
    it('Configures the plugin', function() {
        cy.login('admin', 'admin', 'publicknowledge');

        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();

        // TODO: Add settings configuration tests when settings are implemented
        cy.get('a[id^="component-grid-settings-plugins-settingsplugingrid-category-generic-row-codecheckplugin-settings-button-"]', {timeout: 20_000}).as('settings');
        cy.waitJQuery();
        cy.wait(2000);
        cy.get('@settings').click({force: true});
        // TODO: Test actual settings form
    });
    
    it('Tests the article view', function() {
        // TODO: Test CODECHECK display on article page
        cy.visit('/index.php/publicknowledge/article/view/mwandenga-signalling-theory');
        // TODO: Add assertions for CODECHECK content
    });
});