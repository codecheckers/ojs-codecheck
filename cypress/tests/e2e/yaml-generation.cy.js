describe('YAML Generation Consistency', () => {
  
  // A submission with a *complete* CODECHECK: the preview and download are gated
  // on a certificate being present. This used to ask the API for "any published
  // submission", which stopped being well defined once the dataset gained one
  // whose check is still under way (see pending-check.cy.js).
  const submissionId = 2;

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });
  it('Preview YAML should match download YAML (both use PHP)', () => {
    // Visit the CODECHECK form
    cy.visit(`/index.php/codecheck/dashboard/editorial?currentViewId=published&workflowSubmissionId=${submissionId}&workflowMenuKey=codecheck`);
    
    // Wait for form to load
    cy.get('.codecheck-metadata-form', { timeout: 10000 }).should('exist');
    
    // Get CSRF token from the loaded page
    cy.window().then((win) => {
      const csrfToken = win.pkp?.currentUser?.csrfToken;
      expect(csrfToken).to.exist;
      
      // Call PHP endpoint to get YAML (download)
      cy.request({
        method: 'GET',
        url: `/index.php/codecheck/api/v1/codecheck/yaml?submissionId=${submissionId}`,
        headers: { 
          'X-Csrf-Token': csrfToken
        }
      }).then((response) => {
        
        expect(response.status).to.eq(200);
        const downloadYaml = response.body.yaml;
        
        // Scroll to and click preview button
        cy.get('[data-testid="preview-yaml-button"]', { timeout: 5000 })
          .scrollIntoView()
          .should('be.visible')
          .click();
        
        // Wait for modal to appear
        cy.get('.yaml-preview-content', { timeout: 5000 }).should('be.visible');
        
        // Extract YAML from preview
        cy.get('.yaml-preview-content').invoke('text').then((previewYaml) => {
          
          // Both use PHP, should be exactly identical
          expect(previewYaml.trim()).to.equal(downloadYaml.trim());
        });
      });
    });
  });
  
  it('writes several repositories as a YAML list, not one joined string', () => {
    // Issue #154. Submission 2 carries two public repositories, so it is the one
    // that shows whether the generated file is usable: they used to be joined
    // into `repository: urlA, urlB`, a single scalar no consumer can resolve.
    // The specification takes "A URL or a list of URLs".
    const SUBMISSION_WITH_TWO_REPOSITORIES = 2;

    // cy.ojsLogin() restores a session without loading a page, and
    // cy.getCsrfToken() reads the token off the current one, so a visit first.
    cy.visit('/index.php/codecheck/submissions');

    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: `/index.php/codecheck/api/v1/codecheck/yaml?submissionId=${SUBMISSION_WITH_TWO_REPOSITORIES}`,
        headers: { 'X-Csrf-Token': csrfToken },
      }).then((response) => {
        expect(response.status).to.eq(200);

        const yaml = response.body.yaml;
        const repositorySection = yaml.match(/^repository:.*(?:\n(?:[ \t]+|-).*)*/m)[0];

        expect(repositorySection).to.match(
          /^repository:\s*\n\s*- https:\/\/github\.com\/IainDaviesMaths\/Reproduction-Hancock\s*\n\s*- https:\/\/github\.com\/codecheckers\/Reproduction-Hancock/
        );
      });
    });
  });

  it('Preview button should be disabled when required fields are missing', () => {
    cy.visit(`/index.php/codecheck/dashboard/editorial?currentViewId=published&workflowSubmissionId=${submissionId}&workflowMenuKey=codecheck`);
    
    cy.get('.codecheck-metadata-form', { timeout: 10000 }).should('exist');
    
    cy.window().then((win) => {
      const csrfToken = win.pkp?.currentUser?.csrfToken;
      
      // Clear a required field (certificate)
      cy.get('button').contains('Remove').then(($btn) => {
        if ($btn.length > 0) {
          cy.wrap($btn).click();
          cy.get('button').contains('Yes').click({ force: true });
          
          // Preview button should be disabled
          cy.get('[data-testid="preview-yaml-button"]')
            .scrollIntoView()
            .should('be.disabled');
        }
      });
    });
  });

  it('Preview button should be disabled when form has unsaved changes', () => {
    cy.visit(`/index.php/codecheck/dashboard/editorial?currentViewId=published&workflowSubmissionId=${submissionId}&workflowMenuKey=codecheck`);
    
    cy.get('.codecheck-metadata-form', { timeout: 10000 }).should('exist');
    
    // Initially enabled
    cy.get('[data-testid="preview-yaml-button"]')
      .scrollIntoView()
      .should('not.be.disabled');
    
    // Edit field
    cy.contains('label', 'Summary of the CODECHECK')
      .parent()
      .find('textarea')
      .clear()
      .type('Modified summary');
    
    // Should be disabled
    cy.get('[data-testid="preview-yaml-button"]')
      .should('be.disabled');
  });
});