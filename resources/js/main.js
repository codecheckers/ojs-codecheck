import { createApp } from 'vue';
import CodecheckManifestFiles from "./Components/CodecheckManifestFiles.vue";
import CodecheckRepositoryList from "./Components/CodecheckRepositoryList.vue";
import CodecheckReviewDisplay from "./Components/CodecheckReviewDisplay.vue";
import CodecheckMetadataForm from "./Components/CodecheckMetadataForm.vue";
import CodecheckMetadataFormWrapper from "./Components/CodecheckMetadataFormWrapper.vue";
import CodecheckCertificateIdentifier from "./Components/CodecheckCertificateIdentifier.vue";

// Register components with PKP registry
pkp.registry.registerComponent("CodecheckReviewDisplay", CodecheckReviewDisplay);
pkp.registry.registerComponent("CodecheckMetadataForm", CodecheckMetadataForm);
pkp.registry.registerComponent("CodecheckMetadataFormWrapper", CodecheckMetadataFormWrapper);
pkp.registry.registerComponent("CodecheckManifestFiles", CodecheckManifestFiles);
pkp.registry.registerComponent("CodecheckRepositoryList", CodecheckRepositoryList);
pkp.registry.registerComponent("CodecheckCertificateIdentifier", CodecheckCertificateIdentifier);

// Extend workflow store to add CODECHECK tab/section
pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;

  // Modify the workflow menu to add CODECHECK inside the Workflow tab
  workflowStore.extender.extendFn("getMenuItems", (menuItems, args) => {
    const submission = args?.submission;
        
    const hasCodecheck = submission?.codecheckOptIn;
    console.log("Extending workflow menu, hasCodecheck:", submission);
    if (hasCodecheck) {
      // Clone the menu items
      const updatedMenuItems = [...menuItems];
      
      // Find the Workflow menu item
      const workflowMenuItem = updatedMenuItems.find(item => item.key === 'workflow');
      
      if (workflowMenuItem && workflowMenuItem.items) {
        
        // Add CODECHECK to workflow items (after Review)
        const codecheckItem = {
          key: 'codecheck',
          label: 'CODECHECK',
          state: { 
            primaryMenuItem: 'workflow',
            stageId: 999  // Custom stage ID
          }
        };
        
        // Find Review index and insert after it
        const reviewIndex = workflowMenuItem.items.findIndex(
          item => item.state?.stageId === pkp.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW
        );
        
        if (reviewIndex >= 0) {
          workflowMenuItem.items.splice(reviewIndex + 1, 0, codecheckItem);
        } else {
          // If Review not found, just add to end
          workflowMenuItem.items.push(codecheckItem);
        }
        
      }
      
      return updatedMenuItems;
    }
    
    return menuItems;
  });

  // Render CODECHECK metadata form when selected
  workflowStore.extender.extendFn("getPrimaryItems", (primaryItems, args) => {
    const submission = args?.submission;
        
    // Show CODECHECK form when stageId is 999
    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId === 999
    ) {
      return [
        {
          component: "CodecheckMetadataFormWrapper",
          props: { 
            submission: submission,
            canEdit: true
          },
        }
      ];
    }
    
    // Keep your existing review stage code...
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

// File manager extensions for CODECHECK
pkp.registry.storeExtend("fileManager_SUBMISSION_FILES", (piniaContext) => {
  const fileStore = piniaContext.store;
  
  // Only extend for submissions with CODECHECK
  const workflowStore = pkp.registry.getPiniaStore("workflow");
  const submission = workflowStore?.submission;
  
  if (!submission?.codecheckOptIn) {
    return;
  }

  fileStore.extender.extendFn("getColumns", (columns, args) => {
    const newColumns = [...columns];
    
    const { useLocalize } = pkp.modules.useLocalize;
    const { t } = useLocalize();

    // Add CODECHECK status column before actions
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
          label: "Mark as CODECHECK Output",
          name: "markCodecheckOutput",
          icon: "CheckCircle",
          actionFn: ({ file }) => {
            const { useModal } = pkp.modules.useModal;
            const { useLocalize } = pkp.modules.useLocalize;

            const { openDialog } = useModal();
            const { localize } = useLocalize();

            openDialog({
              title: "Mark as CODECHECK Output",
              message: `Do you want to mark "${localize(file.name)}" as a CODECHECK output file?`,
              actions: [
                {
                  label: "Yes",
                  isPrimary: true,
                  callback: (close) => {
                    // TODO: Implement marking file as CODECHECK output
                    console.log("Marking file as CODECHECK output:", file);
                    close();
                  },
                },
                {
                  label: "No",
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

// Mount Vue components for traditional form fields
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    mountCodecheckVueComponents();
  }, 1000);
});

function mountCodecheckVueComponents() {
  // Mount manifest files component
  const manifestContainer = document.querySelector('textarea[name="manifestFiles"]')?.parentElement;
  if (manifestContainer) {
    const textarea = manifestContainer.querySelector('textarea');
    const vueDiv = document.createElement('div');
    manifestContainer.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';
    
    createApp(CodecheckManifestFiles, {
      name: 'manifestFiles',
      label: 'Expected Output Files',
      description: 'List the main figures, tables, and results',
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
      label: 'Code Repository URLs',
      description: 'Link(s) to your code repository',
      value: textarea.value,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }
  
  // Mount data repository component
  const dataRepoContainer = document.querySelector('textarea[name="dataRepository"]')?.parentElement;
  if (dataRepoContainer) {
    const textarea = dataRepoContainer.querySelector('textarea');
    const vueDiv = document.createElement('div');
    dataRepoContainer.insertBefore(vueDiv, textarea);
    textarea.style.display = 'none';
    
    createApp(CodecheckRepositoryList, {
      name: 'dataRepository',
      label: 'Data Repository URLs',
      description: 'Link(s) to your data repository',
      value: textarea.value,
    }).mount(vueDiv);
    
    vueDiv.addEventListener('update', (e) => {
      textarea.value = e.detail;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  // Mount Certificate Identifier Component to the Submission Form
  const certificateIdentifierContainer = document.querySelector('textarea[name="retrieveReserveCertificateIdentifier"]')?.parentElement;
  if (certificateIdentifierContainer) {
      const textarea = certificateIdentifierContainer.querySelector('textarea');
      const vueDiv = document.createElement('div');
      certificateIdentifierContainer.insertBefore(vueDiv, textarea);
      textarea.style.display = 'none';

      createApp(CodecheckCertificateIdentifier, {
          name: 'retrieveReserveCertificateIdentifier',
          label: 'Certificate Identifier',
          description: 'CODECHECK Certificate ID, Venue Type and Venue Name',
          value: textarea.value,
      }).mount(vueDiv);

      vueDiv.addEventListener('update', (e) => {
          textarea.value = e.detail;
          textarea.dispatchEvent(new Event('input', { bubbles: true }));
      });
  }

  // Mount CODECHECK Metadata Form Component in the editorial Workflow
  /*const metadataFormContainer = document.querySelector('#codecheck-metadata-container');
  if (metadataFormContainer) {
    const input = metadataFormContainer.querySelector('div');
    const vueDiv = document.createElement('div');
    metadataFormContainer.insertBefore(vueDiv, input);
    input.style.display = 'none';

    createApp(CodecheckMetadataForm, {
      name: 'metadataForm',
      label: 'Metadata Form',
      description: 'Form with CODECHECK Metadata',
      value: '' // or initial value from textarea if you have one
    }).mount(vueDiv);
  }*/
}

// Add CODECHECK file status component
const CodecheckFileStatus = {
  template: `
    <pkp-table-cell>
      <span class="codecheck-status" :class="statusClass">{{ statusText }}</span>
    </pkp-table-cell>
  `,
  props: ['file'],
  computed: {
    statusText() {
      // TODO: Implement actual status checking
      return 'Pending';
    },
    statusClass() {
      return 'status-pending';
    }
  }
};

pkp.registry.registerComponent("CodecheckFileStatus", CodecheckFileStatus);

console.log("CODECHECK plugin initialized successfully");
