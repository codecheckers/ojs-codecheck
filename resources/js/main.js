import { createApp } from 'vue';
import CodecheckManifestFiles from "./Components/CodecheckManifestFiles.vue";
import CodecheckRepositoryList from "./Components/CodecheckRepositoryList.vue";
import CodecheckReviewDisplay from "./Components/CodecheckReviewDisplay.vue";
import CodecheckMetadataForm from "./Components/CodecheckMetadataForm.vue";
import CodecheckCertificateIdentifier from "./Components/CodecheckCertificateIdentifier.vue";
import CodecheckDataAndSoftwareAvailability from "./Components/CodecheckDataAndSoftwareAvailability.vue";

pkp.registry.registerComponent("CodecheckReviewDisplay", CodecheckReviewDisplay);
pkp.registry.registerComponent("CodecheckMetadataForm", CodecheckMetadataForm);
pkp.registry.registerComponent("CodecheckManifestFiles", CodecheckManifestFiles);
pkp.registry.registerComponent("CodecheckRepositoryList", CodecheckRepositoryList);
pkp.registry.registerComponent("CodecheckCertificateIdentifier", CodecheckCertificateIdentifier);
pkp.registry.registerComponent("CodecheckDataAndSoftwareAvailability", CodecheckDataAndSoftwareAvailability);

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
      return [
        {
          title: "WORKFLOW: CODECHECK",
          component: "CodecheckMetadataForm",
          props: { 
            submission: submission,
            canEdit: true
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

window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    mountCodecheckVueComponents();
  }, 1000);
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

  // Mount code repository component
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
      name: 'dataSoftwareAvailability',
      label: t('plugins.generic.codecheck.dataSoftwareAvail'),
      description: t('plugins.generic.codecheck.dataSoftwareAvail.description'),
      value: textarea.value,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('input', (e) => {
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
