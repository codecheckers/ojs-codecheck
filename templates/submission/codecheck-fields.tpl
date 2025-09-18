{* Initial checkbox for submission start *}
{if !$showCodecheckDetails}
<fieldset class="codecheck-initial">
    <legend>{translate key="plugins.generic.codecheck.title"}</legend>
    <div class="section">
        <label class="sub_label">
            <input type="checkbox" id="codecheckOptIn" name="codecheckOptIn" value="1" {if $codecheckOptIn}checked{/if} onchange="toggleCodecheckDetails()">
            {translate key="plugins.generic.codecheck.optIn.description"}
            <a href="https://codecheck.org.uk/" target="_blank">CODECHECK</a>
        </label>
    </div>
</fieldset>
{/if}

{* Detailed fields for submission steps *}
{if $showCodecheckDetails}
<fieldset class="codecheck-details">
    <legend>{translate key="plugins.generic.codecheck.details.title"}</legend>
    
    <div class="section">
        <label class="sub_label" for="codeRepository">
            {translate key="plugins.generic.codecheck.codeRepository"} *
        </label>
        <input type="url" id="codeRepository" name="codeRepository" 
               value="{$codeRepository|escape}" 
               placeholder="https://github.com/username/repository" 
               class="text" required>
        <span class="instruct">{translate key="plugins.generic.codecheck.codeRepository.description"}</span>
    </div>
    
    <div class="section">
        <label class="sub_label" for="dataRepository">
            {translate key="plugins.generic.codecheck.dataRepository"}
        </label>
        <input type="url" id="dataRepository" name="dataRepository" 
               value="{$dataRepository|escape}" 
               placeholder="https://zenodo.org/record/123456" 
               class="text">
        <span class="instruct">{translate key="plugins.generic.codecheck.dataRepository.description"}</span>
    </div>
    
    <div class="section">
        <label class="sub_label" for="dependencies">
            {translate key="plugins.generic.codecheck.dependencies"} *
        </label>
        <textarea id="dependencies" name="dependencies" class="text" rows="3" required
                  placeholder="Python 3.8, pandas 1.2.0, numpy 1.19.0">{$dependencies|escape}</textarea>
        <span class="instruct">{translate key="plugins.generic.codecheck.dependencies.description"}</span>
    </div>
    
    <div class="section">
        <label class="sub_label" for="executionInstructions">
            {translate key="plugins.generic.codecheck.executionInstructions"} *
        </label>
        <textarea id="executionInstructions" name="executionInstructions" class="text" rows="4" required
                  placeholder="1. Install: pip install -r requirements.txt&#10;2. Run: python main.py">{$executionInstructions|escape}</textarea>
        <span class="instruct">{translate key="plugins.generic.codecheck.executionInstructions.description"}</span>
    </div>
    
    <div class="section">
        <button type="button" class="button" onclick="previewCodecheckYml()">
            {translate key="plugins.generic.codecheck.preview"}
        </button>
    </div>
</fieldset>
{/if}

<script>
{literal}
function toggleCodecheckDetails() {
    const checkbox = document.getElementById('codecheckOptIn');
    // This will be handled by form submission
    console.log('CODECHECK opt-in:', checkbox.checked);
}

function previewCodecheckYml() {
    const title = document.querySelector('input[name="title"]')?.value || 'Submission Title';
    const codeRepo = document.getElementById('codeRepository')?.value || '';
    const dataRepo = document.getElementById('dataRepository')?.value || '';
    const dependencies = document.getElementById('dependencies')?.value || '';
    const instructions = document.getElementById('executionInstructions')?.value || '';
    
    const yaml = `---
title: "${title}"
repositories:
  - type: "code"
    url: "${codeRepo}"
  - type: "data"  
    url: "${dataRepo}"
dependencies: |
  ${dependencies}
execution_instructions: |
  ${instructions}
---`;
    
    showPreviewModal(yaml);
}

function showPreviewModal(yaml) {
    const backdrop = document.createElement('div');
    backdrop.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); z-index: 1050; display: flex;
        align-items: center; justify-content: center;
    `;
    
    const modal = document.createElement('div');
    modal.style.cssText = `
        background: white; padding: 20px; border-radius: 5px; 
        max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    `;
    
    modal.innerHTML = `
        <h4>codecheck.yml Preview</h4>
        <pre style="background: #f8f9fa; padding: 15px; border-radius: 3px; white-space: pre-wrap;">${yaml}</pre>
        <button onclick="this.closest('[style*=fixed]').remove()" class="button">Close</button>
    `;
    
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
}
{/literal}
</script>