import { createApp } from 'vue';
import CodecheckManifestFiles from "./Components/CodecheckManifestFiles.vue";
import CodecheckRepositoryList from "./Components/CodecheckRepositoryList.vue";
import CodecheckReviewDisplay from "./Components/CodecheckReviewDisplay.vue";

pkp.registry.registerComponent("CodecheckReviewDisplay", CodecheckReviewDisplay);

pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;

  workflowStore.extender.extendFn("getPrimaryItems", (primaryItems, args) => {
    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId === pkp.const.WORKFLOW_STAGE_ID_SUBMISSION
    ) {
      const submission = args.submission;
      
      if (submission?.codecheckOptIn) {
        return [
          ...primaryItems,
          {
            component: "CodecheckReviewDisplay",
            props: { submission: submission },
          },
        ];
      }
    }
    
    return primaryItems;
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
}