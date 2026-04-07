describe('YAML Generation Consistency', () => {
  
  let submissionId;
  
  before(() => {
    cy.ojsLogin('admin', 'admin');
    
    // Get any published submission dynamically
    cy.visit('/index.php/codecheck/submissions');
    cy.window().then((win) => {
      const csrfToken = win.pkp?.currentUser?.csrfToken;
      
      cy.request({
        method: 'GET',
        url: '/index.php/codecheck/api/v1/submissions?status[]=3&count=1',
        headers: { 'X-Csrf-Token': csrfToken }
      }).then((response) => {
        if (response.body.items && response.body.items.length > 0) {
          submissionId = response.body.items[0].id;
          cy.log(`Using submission ID: ${submissionId}`);
        } else {
          throw new Error('No published submissions found. Please publish at least one submission with CODECHECK metadata.');
        }
      });
    });
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
});