import { createApp, reactive } from 'vue';
import CodecheckManifestFiles from "./Components/CodecheckManifestFiles.vue";
import CodecheckRepositoryList from "./Components/CodecheckRepositoryList.vue";
import CodecheckReviewDisplay from "./Components/CodecheckReviewDisplay.vue";
import CodecheckDataAndSoftwareAvailability from "./Components/CodecheckDataAndSoftwareAvailability.vue";
import CodecheckMetadataForm from './Components/CodecheckMetadataForm.vue';
import CodecheckStatusForm from './Components/CodecheckStatusForm.vue';
import CodecheckGithubIssueDisplay from "./Components/CodecheckGithubIssueDisplay.vue";

pkp.registry.registerComponent("CodecheckReviewDisplay", CodecheckReviewDisplay);
pkp.registry.registerComponent("CodecheckMetadataForm", CodecheckMetadataForm);
pkp.registry.registerComponent("CodecheckManifestFiles", CodecheckManifestFiles);
pkp.registry.registerComponent("CodecheckRepositoryList", CodecheckRepositoryList);
pkp.registry.registerComponent("CodecheckDataAndSoftwareAvailability", CodecheckDataAndSoftwareAvailability);
pkp.registry.registerComponent("CodecheckStatusForm", CodecheckStatusForm);
pkp.registry.registerComponent("CodecheckGithubIssueDisplay", CodecheckGithubIssueDisplay);

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;

  workflowStore.codecheck = reactive({
    registerIssueDisplayUpdateEvent: null,
    certificateIdentifier: null,
    issue: null,
    statusUpdateEvent: null,
  });

  workflowStore.extender.extendFn("getMenuItems", (menuItems, args) => {
    const submission = args?.submission;
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
  });

  workflowStore.extender.extendFn("getPrimaryItems", (primaryItems, args) => {
    const submission = args?.submission;
        
    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId === 999
    ) {
      return [
        {
          title: "WORKFLOW: CODECHECK",
          component: "CodecheckMetadataForm",
          props: { 
            submission: submission,
            canEdit: true,
            // Pass the journal mode so the form can show the opt-in warning box (Issue #30)
            codecheckMode: window.codecheckDashboardConfig?.codecheckMode ?? 'opt-in',
          },
        }
      ];
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

  workflowStore.extender.extendFn("getSecondaryItems", (sidebarItems, args) => {
    const store = pkp.registry.stores?.workflow;
    const submission = args?.submission;

    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId === 999
    ) {
      return [
        {
          component: "CodecheckStatusForm",
          props: {
            submission: submission,
            canEdit: true
          },
        },
        {
          component: "CodecheckGithubIssueDisplay",
          props: {
            submission: submission,
            certificateIdentifier: workflowStore?.codecheck?.certificateIdentifier,
            issue: workflowStore?.codecheck?.issue,
          },
        },
      ];
    }

    return sidebarItems;
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

// Submission wizard field management
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
      
      // Save on any button click except cancel
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
    // Find the review step container
    const allSteps = document.querySelectorAll('.pkpStep');
    
    for (const step of allSteps) {
      // Check if this step contains review panels AND is not hidden
      const hasReviewPanels = step.querySelectorAll('.submissionWizard__reviewPanel').length >= 3;
      const isVisible = !step.hasAttribute('hidden');
      
      if (hasReviewPanels && isVisible) {
        return true;
      }
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
            <h4>${this.escapeHtml(t('plugins.generic.codecheck.codeRepository'))}</h4>
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
            <h4>${this.escapeHtml(t('plugins.generic.codecheck.dataRepository'))}</h4>
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
            <h4>${this.escapeHtml(t('plugins.generic.codecheck.manifestFiles.label'))}</h4>
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
            <h4>${this.escapeHtml(t('plugins.generic.codecheck.dataAvailability'))}</h4>
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
              <em>${this.escapeHtml(t('plugins.generic.codecheck.noDataFound'))}</em>
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

// Initialize — mount Vue components only after WizardManager has loaded saved data
// into textareas, so components receive the correct initial values
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    new CodecheckReviewRefresher();
  }, 200);

  setTimeout(async () => {
    const manager = new CodecheckWizardManager();
    await manager.init();
    mountCodecheckVueComponents();
  }, 100);
});

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

console.log("CODECHECK plugin initialized successfully");

// -----------------------------------------------------------------------
// Issue #30: Dashboard CODECHECK status column
//
// Injects a CODECHECK column into the editorial submissions dashboard.
// Each cell fetches from api/v1/codecheck/metadata and shows:
//   - certificate id (green)  if a CODECHECK is complete
//   - "Add" link              if no CODECHECK certificate exists
//
// Controlled by the showDashboardColumn plugin setting (default: true).
// window.codecheckDashboardConfig is injected by CodecheckPlugin.php.
// -----------------------------------------------------------------------
const DashboardCellCodecheck = {
  name: 'DashboardCellCodecheck',
  props: {
    item: { type: Object, required: true }
  },
  data() {
    return {
      codecheckData: null,
      loading: true,
    };
  },
  async mounted() {
    try {
      const apiUrl = pkp.context.apiBaseUrl + 'codecheck/metadata?submissionId=' + this.item.id;
      const response = await fetch(apiUrl, {
        headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
      });
      const data = await response.json();
      if (data.codecheck) {
        this.codecheckData = data.codecheck;
      }
    } catch (e) {
      // no codecheck data available
    } finally {
      this.loading = false;
    }
  },
  computed: {
    hasCertificate() {
      return !!this.codecheckData?.certificate;
    },
    workflowUrl() {
      return this.item.urlEditorialWorkflow + '&workflowMenuKey=codecheck';
    }
  },
  template: `
    <pkp-table-cell>
      <span v-if="loading" style="color:#888;font-size:0.85em;">...</span>
      <span v-else-if="hasCertificate" style="color:#008033;font-weight:600;">
        ✓ {{ codecheckData.certificate }}
      </span>
      <a v-else
        :href="workflowUrl"
        class="pkpButton inline-flex relative items-center gap-x-1 border-transparent hover:enabled:underline disabled:text-disabled text-lg-medium text-primary border-light hover:text-hover disabled:text-disabled py-[0.4375rem] px-3 border rounded -ms-3">
        {{ t('plugins.generic.codecheck.dashboard.add') }}
      </a>
    </pkp-table-cell>
  `
};

pkp.registry.registerComponent("DashboardCellCodecheck", DashboardCellCodecheck);

pkp.registry.storeExtend("dashboard", (piniaContext) => {
  // Read setting inside the callback so it's evaluated when the store loads,
  // not at script load time when window.codecheckDashboardConfig may not exist yet.
  if (!(window.codecheckDashboardConfig ?? { showDashboardColumn: true }).showDashboardColumn) {
    return;
  }

  const dashboardStore = piniaContext.store;

  dashboardStore.extender.extendFn("getColumns", (columns, args) => {
    const newColumns = [...columns];
    newColumns.splice(newColumns.length - 1, 0, {
      id:        "codecheck",
      header:    t('plugins.generic.codecheck.dashboard.columnHeader'),
      component: "DashboardCellCodecheck",
      sortable:  false,
    });
    return newColumns;
  });
});