document.addEventListener('DOMContentLoaded', function() {
    // Find the manifest files textarea
    const manifestTextarea = document.querySelector('textarea[name="manifestFiles"]');
    if (!manifestTextarea) return;
    
    // Hide the original textarea
    manifestTextarea.style.display = 'none';
    
    // Create interactive interface
    const container = document.createElement('div');
    container.innerHTML = `
        <div id="manifestFilesInteractive">
            <div id="manifestFilesList"></div>
            <button type="button" onclick="addManifestFile()" style="background: #006798; font-size:.875rem; color: white; line-height:1.25rem; border: none; padding: .4375rem .75rem; border-radius: 4px; margin-top: 10px;">+ Add File</button>
        </div>
    `;
    
    manifestTextarea.parentNode.insertBefore(container, manifestTextarea.nextSibling);
    
    // Load existing data
    loadExistingManifestFiles();
});

let fileCount = 0;

function addManifestFile(filename = '', comment = '') {
    const list = document.getElementById('manifestFilesList');
    const fileDiv = document.createElement('div');
    fileDiv.className = 'manifest-file-row';
    fileDiv.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
    
    fileDiv.innerHTML = `
        <input type="text" placeholder="Filename (e.g., figures/figure1.png)" value="${filename}" 
               style="flex: 1; font-size:14px; padding: 6px; border: 1px solid #ccc; border-radius: 3px; height: 2.5rem;" 
               onchange="updateManifestData()">
        <input type="text" placeholder="Comment (e.g., Main result visualization)" value="${comment}" 
               style="flex: 2; font-size:14px; padding: 6px; border: 1px solid #ccc; border-radius: 3px; height: 2.5rem;" 
               onchange="updateManifestData()">
        <button type="button" onclick="removeManifestFile(this)" 
                style="background: #dc3545; color: white; border: none; font-size:.875rem; line-height:1.25rem; border: none; padding: .4375rem .75rem; border-radius: 3px;">Remove</button>
    `;
    
    list.appendChild(fileDiv);
    updateManifestData();
    fileCount++;
}

function removeManifestFile(button) {
    button.closest('.manifest-file-row').remove();
    updateManifestData();
}

function updateManifestData() {
    const rows = document.querySelectorAll('.manifest-file-row');
    const data = [];
    
    rows.forEach(row => {
        const inputs = row.querySelectorAll('input');
        const filename = inputs[0].value.trim();
        const comment = inputs[1].value.trim();
        
        if (filename) {
            data.push(filename + (comment ? ' - ' + comment : ''));
        }
    });
    
    const textarea = document.querySelector('textarea[name="manifestFiles"]');
    textarea.value = data.join('\n');
}

function loadExistingManifestFiles() {
    const textarea = document.querySelector('textarea[name="manifestFiles"]');
    const existing = textarea.value.trim();
    
    if (existing) {
        const lines = existing.split('\n');
        lines.forEach(line => {
            if (line.trim()) {
                const parts = line.split(' - ');
                addManifestFile(parts[0] || '', parts[1] || '');
            }
        });
    } else {
        // Add one empty row
        addManifestFile();
    }
}