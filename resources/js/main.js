import { createApp } from 'vue';
import CodecheckManifestFiles from "./Components/CodecheckManifestFiles.vue";
import CodecheckRepositoryList from "./Components/CodecheckRepositoryList.vue";

// Register Vue components
pkp.registry.registerComponent("CodecheckManifestFiles", CodecheckManifestFiles);
pkp.registry.registerComponent("CodecheckRepositoryList", CodecheckRepositoryList);

// Submission wizard field management
class CodecheckWizardManager {
  constructor() {
    this.textareas = {};
    this.vueApps = {};
    this.saveInProgress = false;
  }

  async loadSavedData() {
    const submissionId = this.getSubmissionId();
    if (!submissionId) return;

    try {
      const response = await fetch(`${pkp.context.apiBaseUrl}/submissions/${submissionId}`);
      const submission = await response.json();
      const publication = submission.publications.find(p => p.id === submission.currentPublicationId);
      
      if (publication) {
        this.setTextareaValue('codeRepository', publication.codeRepository);
        this.setTextareaValue('dataRepository', publication.dataRepository);
        this.setTextareaValue('manifestFiles', publication.manifestFiles);
        this.setTextareaValue('dataAvailabilityStatement', publication.dataAvailabilityStatement);
      }
    } catch (error) {
      console.error('CODECHECK: Failed to load saved data', error);
    }
  }

  setTextareaValue(name, value) {
    const textarea = document.querySelector(`textarea[name="${name}"]`);
    if (textarea && value) {
      textarea.value = value;
      this.textareas[name] = textarea;
    }
  }

  mountVueComponents() {
    this.mountComponent('manifestFiles', CodecheckManifestFiles, {
      label: 'Expected Output Files',
      description: 'List the main figures, tables, and results',
      isRequired: true,
    });

    this.mountComponent('codeRepository', CodecheckRepositoryList, {
      label: 'Code Repository URLs',
      description: 'Link(s) to your code repository',
    });

    this.mountComponent('dataRepository', CodecheckRepositoryList, {
      label: 'Data Repository URLs',
      description: 'Link(s) to your data repository',
    });
  }

  mountComponent(name, component, extraProps) {
    const textarea = document.querySelector(`textarea[name="${name}"]`);
    if (!textarea) return;

    const container = textarea.parentElement;
    const vueDiv = document.createElement('div');
    container.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';

    const app = createApp(component, {
      name,
      value: textarea.value,
      ...extraProps
    });

    app.mount(vueDiv);
    this.vueApps[name] = vueDiv;

    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
    });
  }

  async saveData() {
    if (this.saveInProgress) return;

    const submissionId = this.getSubmissionId();
    if (!submissionId) return;

    const data = {};
    ['codeRepository', 'dataRepository', 'manifestFiles', 'dataAvailabilityStatement'].forEach(field => {
      const textarea = document.querySelector(`textarea[name="${field}"]`);
      if (textarea && textarea.value) {
        data[field] = textarea.value;
      }
    });

    if (Object.keys(data).length === 0) return;

    this.saveInProgress = true;

    try {
      const submissionResponse = await fetch(`${pkp.context.apiBaseUrl}/submissions/${submissionId}`);
      const submission = await submissionResponse.json();
      const publicationId = submission.currentPublicationId;

      await fetch(
        `${pkp.context.apiBaseUrl}/submissions/${submissionId}/publications/${publicationId}`,
        {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-Csrf-Token': pkp.currentUser.csrfToken
          },
          body: JSON.stringify(data)
        }
      );
    } catch (error) {
      console.error('CODECHECK: Save failed', error);
    } finally {
      this.saveInProgress = false;
    }
  }

  getSubmissionId() {
    const match = window.location.search.match(/id=(\d+)/);
    return match ? match[1] : null;
  }

  setupButtonListener() {
    document.addEventListener('click', (e) => {
      const button = e.target.closest('button');
      if (button && (button.textContent.includes('Continue') || button.textContent.includes('Save for Later'))) {
        this.saveData();
      }
    }, true);
  }

  async init() {
    await this.loadSavedData();
    this.mountVueComponents();
    this.setupButtonListener();
  }
}

// Review section refresher
class CodecheckReviewRefresher {
  constructor() {
    this.refreshedPanels = new Set();
    this.observeStepChanges();
  }

  observeStepChanges() {
    setInterval(() => {
      if (this.isOnReviewStep()) {
        this.checkForReviewPanel();
      }
    }, 300);

    const observer = new MutationObserver(() => {
      if (this.isOnReviewStep()) {
        this.checkForReviewPanel();
      }
    });

    observer.observe(document.body, { 
      childList: true, 
      subtree: true 
    });
  }

  isOnReviewStep() {
    const wizardSteps = document.querySelectorAll('.submissionWizard__step');
    
    for (const step of wizardSteps) {
      if (step.classList.contains('isActive') || step.classList.contains('isCurrent')) {
        const stepText = step.textContent.toLowerCase();
        if (stepText.includes('review') || stepText.includes('submit')) {
          return true;
        }
      }
    }

    const reviewPanels = document.querySelectorAll('.submissionWizard__reviewPanel');
    const visiblePanels = Array.from(reviewPanels).filter(panel => {
      const rect = panel.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });

    return visiblePanels.length > 2;
  }

  checkForReviewPanel() {
    const allH3s = document.querySelectorAll('.submissionWizard__reviewPanel h3');
    
    for (const h3 of allH3s) {
      if (h3.textContent.includes('CODECHECK')) {
        const panel = h3.closest('.submissionWizard__reviewPanel');
        
        if (!panel) continue;
        
        const rect = panel.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) continue;
        
        const panelContent = panel.innerHTML.substring(0, 100);
        
        if (!this.refreshedPanels.has(panelContent)) {
          this.refreshedPanels.add(panelContent);
          
          setTimeout(() => {
            this.refreshReviewData(panel);
          }, 200);
          
          return;
        }
      }
    }
  }

  async refreshReviewData(panel) {
    const submissionId = this.getSubmissionId();
    if (!submissionId) return;

    try {
      const response = await fetch(`${pkp.context.apiBaseUrl}/submissions/${submissionId}`);
      const submission = await response.json();
      
      const publication = submission.publications?.find(p => p.id === submission.currentPublicationId);
      if (!publication) return;

      const body = panel.querySelector('.submissionWizard__reviewPanel__body');
      if (!body) return;

      body.innerHTML = '';
      
      let hasData = false;
      
      if (publication.codeRepository) {
        hasData = true;
        body.innerHTML += `
          <div class="submissionWizard__reviewPanel__item">
            <h4>${this.escapeHtml('Code Repository URLs')}</h4>
            <div class="review-value">
              <p>${this.escapeHtml(publication.codeRepository).replace(/\n/g, '<br>')}</p>
            </div>
          </div>
        `;
      }
      
      if (publication.dataRepository) {
        hasData = true;
        body.innerHTML += `
          <div class="submissionWizard__reviewPanel__item">
            <h4>${this.escapeHtml('Data Repository URLs')}</h4>
            <div class="review-value">
              <p>${this.escapeHtml(publication.dataRepository).replace(/\n/g, '<br>')}</p>
            </div>
          </div>
        `;
      }
      
      if (publication.manifestFiles) {
        hasData = true;
        body.innerHTML += `
          <div class="submissionWizard__reviewPanel__item">
            <h4>${this.escapeHtml('Expected Output Files')}</h4>
            <div class="review-value">
              <pre>${this.escapeHtml(publication.manifestFiles)}</pre>
            </div>
          </div>
        `;
      }
      
      if (publication.dataAvailabilityStatement) {
        hasData = true;
        body.innerHTML += `
          <div class="submissionWizard__reviewPanel__item">
            <h4>${this.escapeHtml('Data and Software Availability')}</h4>
            <div class="review-value">
              <div>${publication.dataAvailabilityStatement}</div>
            </div>
          </div>
        `;
      }
      
      if (!hasData) {
        body.innerHTML = `
          <div class="submissionWizard__reviewPanel__item">
            <p class="description" style="color: #d00a0a;">
              <em>No CODECHECK data found.</em>
            </p>
          </div>
        `;
      }
    } catch (error) {
      console.error('CODECHECK: Failed to refresh review data', error);
    }
  }

  escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  getSubmissionId() {
    const match = window.location.search.match(/id=(\d+)/);
    return match ? match[1] : null;
  }
}

// Initialize
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    new CodecheckReviewRefresher();
  }, 200);

  setTimeout(() => {
    const manager = new CodecheckWizardManager();
    manager.init();
  }, 100);
});

// File status component for workflow
const CodecheckFileStatus = {
  template: `
    <pkp-table-cell>
      <span class="codecheck-status" :class="statusClass">{{ statusText }}</span>
    </pkp-table-cell>
  `,
  props: ['file'],
  computed: {
    statusText() {
      return 'Pending';
    },
    statusClass() {
      return 'status-pending';
    }
  }
};

pkp.registry.registerComponent("CodecheckFileStatus", CodecheckFileStatus);