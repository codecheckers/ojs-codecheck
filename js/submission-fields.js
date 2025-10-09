document.addEventListener('DOMContentLoaded', function() {    
    // Initialize manifest files
    const manifestTextarea = document.querySelector('textarea[name="manifestFiles"]');
    if (manifestTextarea) {
        initializeManifestFiles(manifestTextarea);
    }
    
    // Initialize code repositories
    const codeRepoTextarea = document.querySelector('textarea[name="codeRepository"]');
    if (codeRepoTextarea) {
        initializeRepositoryList(codeRepoTextarea, 'code');
    }
    
    // Initialize data repositories
    const dataRepoTextarea = document.querySelector('textarea[name="dataRepository"]');
    if (dataRepoTextarea) {
        initializeRepositoryList(dataRepoTextarea, 'data');
    }

    // Initialize Certificate Identifier
    const certificateIdentifierTextarea = document.querySelector('textarea[name="retrieveReserveCertificateIdentifier"]');
    if (certificateIdentifierTextarea) {
        initializeCertificateIdentiferSection(certificateIdentifierTextarea);
    }
});

// =========================================================
// RETRIEVE AND RESERVE CERTIFICATE IDENTIFIER FUNCTIONALITY
// =========================================================

function initializeCertificateIdentiferSection(textarea) {
    textarea.style.display = 'none';

    const container = document.createElement('div');
    container.innerHTML = `
        <div id="certificateIdentiferSection">
            <div 
                style="
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    justify-content: center;
                "
            >
                <input
                    type="text"
                    name="certificateIdentifierInput"
                    placeholder="e.g.: 2025-001"
                    style="flex: 1; font-size:14px; padding: 6px; border: 1px solid #ccc; border-radius: 3px; height: 2.5rem;" 
                >
                <select
                    id="venueTypes"
                    style="font-size:14px; padding: 6px; border: 1px solid #ccc; border-radius: 3px; height: 2.5rem; background: #fff;"
                >
                    <option>workshop</option>
                </select>
                <select
                    id="venueNames"
                    style="font-size:14px; padding: 6px; border: 1px solid #ccc; border-radius: 3px; height: 2.5rem; background: #fff;"
                >
                    <option>check-nl</option>
                </select>
            </div>
            <div
                style="
                    padding-top: 0.5rem;
                "
            >
                <button
                    type="button"
                    onclick="reserveIdentifier()"
                    style="background: #006798; font-size:.875rem; color: white; line-height:1.25rem; border: none; padding: .4375rem .75rem; border-radius: 4px;"
                >
                    Reserve Identifier
                </button>
                <button
                    type="button"
                    onclick="removeIdentifier()" 
                    style="background: #dc3545; color: white; border: none; font-size:.875rem; line-height:1.25rem; padding: .4375rem .75rem; border-radius: 3px;"
                >
                    Remove Identifier
                </button>
            </div>
        </div>
    `;

    textarea.parentNode.insertBefore(container, textarea.nextSibling);
}

function reserveIdentifier() {
    // TODO: Ajax call to CertificateRetrievingAndCreation.php with function that handles that
    // TODO: Return new identifier inside the input of the Certificate Identifier

}

function removeIdentifier() {
    // TODO: Remove Identifier from inside of the input
    const input = document.querySelector('input[name="certificateIdentifierInput"]');
    if (input) {
        input.value = '';
    }
}

// ============================================
// MANIFEST FILES FUNCTIONALITY
// ============================================

function initializeManifestFiles(textarea) {
    textarea.style.display = 'none';
    
    const container = document.createElement('div');
    container.innerHTML = `
        <div id="manifestFilesInteractive">
            <div id="manifestFilesList"></div>
            <button type="button" onclick="addManifestFile()" style="background: #006798; font-size:.875rem; color: white; line-height:1.25rem; border: none; padding: .4375rem .75rem; border-radius: 4px; margin-top: 10px;">+ Add File</button>
        </div>
    `;
    
    textarea.parentNode.insertBefore(container, textarea.nextSibling);
    loadExistingManifestFiles();
}

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
        addManifestFile();
    }
}

// ============================================
// REPOSITORY URLs FUNCTIONALITY
// ============================================

function initializeRepositoryList(textarea, type) {
    textarea.style.display = 'none';
    
    const container = document.createElement('div');
    container.innerHTML = `
        <div id="${type}RepositoryInteractive">
            <div id="${type}RepositoryList"></div>
            <button type="button" onclick="addRepository('${type}')" 
                    style="background: #006798; font-size:.875rem; color: white; line-height:1.25rem; border: none; padding: .4375rem .75rem; border-radius: 4px; margin-top: 10px;">
                + Add ${type === 'code' ? 'Code' : 'Data'} Repository
            </button>
        </div>
    `;
    
    textarea.parentNode.insertBefore(container, textarea.nextSibling);
    loadExistingRepositories(type);
}

function addRepository(type, url = '') {
    const list = document.getElementById(`${type}RepositoryList`);
    const repoDiv = document.createElement('div');
    repoDiv.className = 'repository-row';
    repoDiv.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
    
    repoDiv.innerHTML = `
        <input type="text" placeholder="Repository URL (e.g., https://github.com/username/repo)" value="${url}" 
               style="flex: 1; font-size:14px; padding: 6px; border: 1px solid #ccc; border-radius: 3px; height: 2.5rem;" 
               onchange="updateRepositoryData('${type}')">
        <button type="button" onclick="removeRepository(this, '${type}')" 
                style="background: #dc3545; color: white; border: none; font-size:.875rem; line-height:1.25rem; padding: .4375rem .75rem; border-radius: 3px;">
            Remove
        </button>
    `;
    
    list.appendChild(repoDiv);
    updateRepositoryData(type);
}

function removeRepository(button, type) {
    button.closest('.repository-row').remove();
    updateRepositoryData(type);
}

function updateRepositoryData(type) {
    const rows = document.querySelectorAll(`#${type}RepositoryList .repository-row`);
    const data = [];
    
    rows.forEach(row => {
        const input = row.querySelector('input');
        const url = input.value.trim();
        
        if (url) {
            data.push(url);
        }
    });
    
    const textarea = document.querySelector(`textarea[name="${type}Repository"]`);
    textarea.value = data.join('\n');
}

function loadExistingRepositories(type) {
    const textarea = document.querySelector(`textarea[name="${type}Repository"]`);
    const existing = textarea.value.trim();
    
    if (existing) {
        const lines = existing.split('\n');
        lines.forEach(line => {
            if (line.trim()) {
                addRepository(type, line.trim());
            }
        });
    } else {
        addRepository(type);
    }
}