import { createApp } from 'vue';
import CodecheckManifestFiles from "./Components/CodecheckManifestFiles.vue";
import CodecheckRepositoryList from "./Components/CodecheckRepositoryList.vue";
import CodecheckReviewDisplay from "./Components/CodecheckReviewDisplay.vue";
import CodecheckMetadataForm from "./Components/CodecheckMetadataForm.vue";
import CodecheckDataAndSoftwareAvailability from "./Components/CodecheckDataAndSoftwareAvailability.vue";
import CodecheckOrcidSection from "./Components/CodecheckOrcidSection.vue";

pkp.registry.registerComponent("CodecheckReviewDisplay", CodecheckReviewDisplay);
pkp.registry.registerComponent("CodecheckMetadataForm", CodecheckMetadataForm);
pkp.registry.registerComponent("CodecheckManifestFiles", CodecheckManifestFiles);
pkp.registry.registerComponent("CodecheckRepositoryList", CodecheckRepositoryList);
pkp.registry.registerComponent("CodecheckDataAndSoftwareAvailability", CodecheckDataAndSoftwareAvailability);
pkp.registry.registerComponent("CodecheckOrcidSection", CodecheckOrcidSection);

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;

  workflowStore.extender.extendFn("getMenuItems", (menuItems, args) => {
    const submission = args?.submission;
    const hasCodecheck = submission?.codecheckOptIn == true || submission?.codecheckOptIn == 1 || submission?.codecheckOptIn === "1";

    if (hasCodecheck) {
      const updatedMenuItems = [...menuItems];
      const workflowMenuItem = updatedMenuItems.find(item => item.key === 'workflow');
      
      if (workflowMenuItem && workflowMenuItem.items) {
        const codecheckItem = {
          key: 'codecheck',
          label: t('plugins.generic.codecheck.workflow.label'),
          state: { 
            primaryMenuItem: 'workflow',
            title: t('plugins.generic.codecheck.workflow.title'),
            stageId: 999
          }
        };
        
        const reviewIndex = workflowMenuItem.items.findIndex(
          item => item.state?.stageId === pkp.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW
        );
        
        if (reviewIndex >= 0) {
          workflowMenuItem.items.splice(reviewIndex + 1, 0, codecheckItem);
        } else {
          workflowMenuItem.items.push(codecheckItem);
        }
      }
      
      return updatedMenuItems;
    }
    
    return menuItems;
  });

  workflowStore.extender.extendFn("getPrimaryItems", (primaryItems, args) => {
    const submission = args?.submission;

    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId === 999
    ) {
      const orcidConfig = window.codecheckOrcidConfig ?? {};

      const items = [
        {
          title: "WORKFLOW: CODECHECK",
          component: "CodecheckMetadataForm",
          props: { 
            submission: submission,
            canEdit: true
          },
        }
      ];

      if (orcidConfig.enabled) {
        items.push({
          component: "CodecheckOrcidSection",
          props: {
            submission:   submission,
            orcidEnabled: orcidConfig.enabled,
            orcidAuthUrl: orcidConfig.authUrl,
            orcidApiType: orcidConfig.apiType,
            canAuthorise: false, // editors monitor status and trigger deposit; auth is done by the codechecker
          },
        });
      }

      return items;
    }
    
    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId === pkp.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW &&
      submission?.codecheckOptIn
    ) {
      return [
        ...primaryItems,
        {
          component: "CodecheckReviewDisplay",
          props: { submission: submission },
        },
      ];
    }
    
    return primaryItems;
  });
});

pkp.registry.storeExtend("fileManager_SUBMISSION_FILES", (piniaContext) => {
  const fileStore = piniaContext.store;
  
  const workflowStore = pkp.registry.getPiniaStore("workflow");
  const submission = workflowStore?.submission;
  
  if (!submission?.codecheckOptIn) {
    return;
  }

  fileStore.extender.extendFn("getColumns", (columns, args) => {
    const newColumns = [...columns];

    newColumns.splice(newColumns.length - 1, 0, {
      header: t("plugins.generic.codecheck.codecheckStatus"),
      component: "CodecheckFileStatus",
      props: {},
    });

    return newColumns;
  });

  fileStore.extender.extendFn("getItemActions", (originalResult, args) => {
    if (args.file) {
      return [
        ...originalResult,
        {
          label: t("plugins.generic.codecheck.markAsOutput"),
          name: "markCodecheckOutput",
          icon: "CheckCircle",
          actionFn: ({ file }) => {
            const { useModal } = pkp.modules.useModal;
            const { openDialog } = useModal();
            const { localize } = useLocalize();

            openDialog({
              title: t("plugins.generic.codecheck.markAsOutputTitle"),
              message: t("plugins.generic.codecheck.markAsOutputConfirm", { fileName: localize(file.name) }),
              actions: [
                {
                  label: t("common.yes"),
                  isPrimary: true,
                  callback: (close) => {
                    console.log("Marking file as CODECHECK output:", file);
                    close();
                  },
                },
                {
                  label: t("common.no"),
                  callback: (close) => {
                    close();
                  },
                },
              ],
            });
          },
        },
      ];
    }
    return originalResult;
  });
});

// -----------------------------------------------------------------------
// Submission wizard: save/load field data via API
// -----------------------------------------------------------------------
class CodecheckWizardManager {
  constructor() {
    this.textareas = {};
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
      if (!button) return;
      if (button.id !== 'cancelSubmission') {
        this.saveData();
      }
    }, true);
  }

  async init() {
    await this.loadSavedData();
    this.setupButtonListener();
  }
}

// -----------------------------------------------------------------------
// Submission wizard: refresh review panel data
// -----------------------------------------------------------------------
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

    observer.observe(document.body, { childList: true, subtree: true });
  }

  isOnReviewStep() {
    const allSteps = document.querySelectorAll('.pkpStep');
    for (const step of allSteps) {
      const hasReviewPanels = step.querySelectorAll('.submissionWizard__reviewPanel').length >= 3;
      const isVisible = !step.hasAttribute('hidden');
      if (hasReviewPanels && isVisible) return true;
    }
    return false;
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
          setTimeout(() => { this.refreshReviewData(panel); }, 200);
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
        body.innerHTML += `<div class="submissionWizard__reviewPanel__item"><h4>${this.escapeHtml(t('plugins.generic.codecheck.codeRepository'))}</h4><div class="review-value"><p>${this.escapeHtml(publication.codeRepository).replace(/\n/g, '<br>')}</p></div></div>`;
      }
      if (publication.dataRepository) {
        hasData = true;
        body.innerHTML += `<div class="submissionWizard__reviewPanel__item"><h4>${this.escapeHtml(t('plugins.generic.codecheck.dataRepository'))}</h4><div class="review-value"><p>${this.escapeHtml(publication.dataRepository).replace(/\n/g, '<br>')}</p></div></div>`;
      }
      if (publication.manifestFiles) {
        hasData = true;
        body.innerHTML += `<div class="submissionWizard__reviewPanel__item"><h4>${this.escapeHtml(t('plugins.generic.codecheck.manifestFiles.label'))}</h4><div class="review-value"><pre>${this.escapeHtml(publication.manifestFiles)}</pre></div></div>`;
      }
      if (publication.dataAvailabilityStatement) {
        hasData = true;
        body.innerHTML += `<div class="submissionWizard__reviewPanel__item"><h4>${this.escapeHtml(t('plugins.generic.codecheck.dataAvailability'))}</h4><div class="review-value"><div>${publication.dataAvailabilityStatement}</div></div></div>`;
      }
      if (!hasData) {
        body.innerHTML = `<div class="submissionWizard__reviewPanel__item"><p class="description" style="color: #d00a0a;"><em>${this.escapeHtml(t('plugins.generic.codecheck.noDataFound'))}</em></p></div>`;
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

// -----------------------------------------------------------------------
// Initialization
// -----------------------------------------------------------------------
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(async () => {
    const manager = new CodecheckWizardManager();
    await manager.init();
    mountCodecheckVueComponents();
  }, 100);

  setTimeout(() => {
    new CodecheckReviewRefresher();

    if (window.codecheckReviewerData) {
      const tab3Link = document.querySelector('#reviewTabs ul li:nth-child(3) a');
      if (tab3Link) {
        const badge = document.createElement('span');
        badge.style.cssText = 'margin-left: 0.4rem; font-size: 0.7rem; background: #008033; color: white; padding: 0.1rem 0.3rem; border-radius: 3px; vertical-align: middle;';
        badge.textContent = 'CODECHECK';
        tab3Link.appendChild(badge);
      }
    }
  }, 1000);

  const observer = new MutationObserver(() => {
    const step3 = document.querySelector('#reviewStep3');
    if (step3 && step3.children.length > 0 && !document.querySelector('#codecheck-reviewer-form')) {
      window.mountCodecheckReviewerForm();
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });
});

// -----------------------------------------------------------------------
// Reviewer page: mount CODECHECK form inside tab 3 (Download & Review).
// -----------------------------------------------------------------------
function mountCodecheckReviewerForm() {
  if (!window.codecheckReviewerData) return;

  const step3 = document.querySelector('#reviewStep3');
  if (!step3) return;
  if (document.querySelector('#codecheck-reviewer-form')) return;
  if (step3.children.length === 0) return;

  const reviewerData = window.codecheckReviewerData;
  const submission = {
    id: reviewerData.submissionId,
    codecheckOptIn: reviewerData.codecheckOptIn,
  };

  const details = document.createElement('details');
  details.id = 'codecheck-reviewer-form';
  details.style.cssText = 'margin-top: 2rem; border: 1px solid #ddd; border-radius: 4px; background: #fff;';

  const summary = document.createElement('summary');
  summary.style.cssText = 'padding: 1rem; font-weight: 600; font-size: 1rem; cursor: pointer; list-style: none; display: flex; align-items: center; gap: 0.5rem; background: #f8f8f8; border-radius: 4px;';
  summary.innerHTML = '<span style="color: #008033;">&#10003;</span> CODECHECK Documentation';

  const content = document.createElement('div');
  content.style.cssText = 'padding: 1rem;';

  details.appendChild(summary);
  details.appendChild(content);

  const form = document.querySelector('#reviewStep3Form');
  if (form) {
    form.after(details);
  } else {
    step3.appendChild(details);
  }

  const metadataDiv = document.createElement('div');
  content.appendChild(metadataDiv);
  const metadataApp = createApp(CodecheckMetadataForm, {
    submission: submission,
    canEdit: true,
  });
  metadataApp.component('pkp-button', pkp.registry.getComponent('PkpButton'));
  metadataApp.mount(metadataDiv);

  const orcid = reviewerData.orcid ?? {};
  if (orcid.enabled) {
    window.codecheckOrcidConfig = orcid;
    const orcidDiv = document.createElement('div');
    content.appendChild(orcidDiv);
    const orcidApp = createApp(CodecheckOrcidSection, {
      submission:   submission,
      orcidEnabled: orcid.enabled,
      orcidAuthUrl: orcid.authUrl,
      orcidApiType: orcid.apiType,
      canAuthorise: true, // codechecker authorises from their reviewer form
    });
    orcidApp.component('pkp-button', pkp.registry.getComponent('PkpButton'));
    orcidApp.mount(orcidDiv);
  }
}
window.mountCodecheckReviewerForm = mountCodecheckReviewerForm;

// -----------------------------------------------------------------------
// Submission wizard: mount Vue components into textareas
// -----------------------------------------------------------------------
function mountCodecheckVueComponents() {
  const manifestContainer = document.querySelector('textarea[name="manifestFiles"]')?.parentElement;
  if (manifestContainer) {
    const textarea = manifestContainer.querySelector('textarea');
    const vueDiv = document.createElement('div');
    manifestContainer.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';
    
    createApp(CodecheckManifestFiles, {
      name: 'manifestFiles',
      label: t('plugins.generic.codecheck.manifestFiles.label'),
      description: t('plugins.generic.codecheck.manifestFiles.description'),
      value: textarea.value,
      isRequired: true,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  const codeRepoContainer = document.querySelector('textarea[name="codeRepository"]')?.parentElement;
  if (codeRepoContainer) {
    const textarea = codeRepoContainer.querySelector('textarea');
    const vueDiv = document.createElement('div');
    codeRepoContainer.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';
    
    createApp(CodecheckRepositoryList, {
      name: 'codeRepository',
      label: t('plugins.generic.codecheck.codeRepository'),
      description: t('plugins.generic.codecheck.codeRepository.description'),
      value: textarea.value,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }
  
  const dataRepoContainer = document.querySelector('textarea[name="dataRepository"]')?.parentElement;
  if (dataRepoContainer) {
    const textarea = dataRepoContainer.querySelector('textarea');
    const vueDiv = document.createElement('div');
    dataRepoContainer.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';
    
    createApp(CodecheckRepositoryList, {
      name: 'dataRepository',
      label: t('plugins.generic.codecheck.dataRepository'),
      description: t('plugins.generic.codecheck.dataRepository.description'),
      value: textarea.value,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  const dataAndSoftwareAvailabilityContainer = document.querySelector('textarea[name="dataAvailabilityStatement"]')?.parentElement;
  if (dataAndSoftwareAvailabilityContainer) {
    const textarea = dataAndSoftwareAvailabilityContainer.querySelector('textarea');
    const vueDiv = document.createElement('div');
    dataAndSoftwareAvailabilityContainer.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';
    
    createApp(CodecheckDataAndSoftwareAvailability, {
      name: 'dataAvailabilityStatement',
      label: t('plugins.generic.codecheck.dataSoftwareAvailability'),
      description: t('plugins.generic.codecheck.dataSoftwareAvailability.description'),
      value: textarea.value,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }
}

const CodecheckFileStatus = {
  template: `
    <pkp-table-cell>
      <span class="codecheck-status" :class="statusClass">{{ statusText }}</span>
    </pkp-table-cell>
  `,
  props: ['file'],
  computed: {
    statusText() {
      if (this.file.codecheckOutput) {
        return t("plugins.generic.codecheck.status.marked");
      }
      return t("plugins.generic.codecheck.status.notMarked");
    },
    statusClass() {
      return this.file.codecheckOutput ? 'status-marked' : 'status-not-marked';
    }
  }
};

pkp.registry.registerComponent("CodecheckFileStatus", CodecheckFileStatus);