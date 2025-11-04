<template>
  <div class="codecheck-metadata-form">
    <!-- Header Section -->
    <div class="codecheck-header">
      <div class="header-content">
        <h2 class="workflow-title">WORKFLOW: CODECHECK</h2>
        <div class="version-selector">
          <label class="version-label">CODECHECK config version</label>
          <select v-model="selectedVersion" class="version-select">
            <option value="latest">latest</option>
            <option value="1.0">v1.0</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Publication Type -->
    <div class="publication-section">
      <div class="radio-options">
        <label class="radio-option">
          <input type="radio" v-model="publicationType" value="doi" name="publication-type" />
          <span class="radio-label">check-published with a DOI (e.g., Zenodo)</span>
        </label>
        <label class="radio-option">
          <input type="radio" v-model="publicationType" value="separate" name="publication-type" />
          <span class="radio-label">check-published in separate section of the journal</span>
        </label>
      </div>
    </div>

    <!-- Manifest Section -->
    <div class="form-section">
      <div class="section-header">
        <h3 class="section-title">Manifest <span class="required">*</span></h3>
        <div style="position: relative; display: inline-block;">
          <button class="pkpButton codecheck-btn" @click="triggerFileUpload">+ File</button>
          <input 
            type="file" 
            :id="fileInputId"
            @change="handleFileUpload" 
            multiple 
            style="position: absolute; left: -9999px; opacity: 0;"
            accept=".pdf,.csv,.txt,.yml,.yaml,.json,.zip"
          >
        </div>
      </div>
      <p class="section-description">
        The output files and their descriptions created by the editorial workflow and considered in the CODECHECK
      </p>
      
      <table class="pkpTable manifest-table" v-if="uploadedFiles.length > 0">
        <thead>
          <tr>
            <th width="20"></th>
            <th>Output File</th>
            <th>Description</th>
            <th width="80"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(file, index) in uploadedFiles" :key="file.id" class="manifest-row">
            <td>
              <input type="checkbox" v-model="file.checked" class="file-checkbox" />
            </td>
            <td>
              <div class="file-info">
                <span class="file-name">{{ file.name }}</span>
                <span class="file-size">({{ formatFileSize(file.size) }})</span>
              </div>
            </td>
            <td>
              <input
                type="text"
                v-model="file.description"
                class="pkpFormField__input"
                placeholder="Some example description of this output file"
              />
            </td>
            <td>
              <button class="pkpButton codecheck-btn pkpButton--isWarnable codecheck-btn-warning" @click="removeUploadedFile(index)">×</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="empty-state">
        No files uploaded yet. Click "+ File" to upload output files.
      </div>
    </div>

    <!-- Paper Metadata -->
    <div class="form-section">
      <h3 class="section-title">Paper Metadata <span class="required">*</span></h3>
      
      <!-- Title -->
      <div class="field-group full-width">
        <label class="field-label">Title</label>
        <input
          type="text"
          v-model="metadata.title"
          class="pkpFormField__input full-width-input"
          required
        />
      </div>

      <!-- Authors -->
      <div class="field-group full-width">
        <div class="field-header">
          <label class="field-label">Authors</label>
          <button class="pkpButton codecheck-btn" @click="showAuthorModal">+ Author</button>
        </div>
        
        <div v-if="metadata.authors.length > 0" class="items-list">
          <div v-for="(author, index) in metadata.authors" :key="index" class="list-item">
            <div class="item-content">
              <div class="item-name">{{ author.name }}</div>
              <div class="item-orcid">{{ author.orcid }}</div>
            </div>
            <button class="pkpButton codecheck-btn pkpButton--isWarnable codecheck-btn-warning" @click="removeAuthor(index)">×</button>
          </div>
        </div>
        <div v-else class="empty-state">
          No authors added yet
        </div>
      </div>

      <!-- Reference -->
      <div class="field-group full-width">
        <label class="field-label">Reference (e.g. the DOI of the preprint)</label>
        <input
          type="text"
          v-model="metadata.reference"
          class="pkpFormField__input full-width-input"
          placeholder="https://doi.org/preprint_1"
        />
      </div>

      <!-- Source -->
      <div class="field-group full-width">
        <label class="field-label">Source of the checked material</label>
        <input
          type="text"
          v-model="metadata.source"
          class="pkpFormField__input full-width-input"
          placeholder="https://download.url/dataset/123456/v2"
        />
      </div>
    </div>

    <!-- CODECHECKers -->
    <div class="form-section">
      <div class="section-header">
        <h3 class="section-title">CODECHECKers <span class="required">*</span></h3>
        <button class="pkpButton codecheck-btn" @click="showCodecheckerModal">+ CODECHECKer</button>
      </div>
      
      <div v-if="metadata.codecheckers.length > 0" class="items-list">
        <div v-for="(checker, index) in metadata.codecheckers" :key="index" class="list-item">
          <div class="item-content">
            <div class="item-name">{{ checker.name }}</div>
            <div class="item-orcid">{{ checker.orcid }}</div>
          </div>
          <button class="pkpButton codecheck-btn pkpButton--isWarnable codecheck-btn-warning" @click="removeCodechecker(index)">×</button>
        </div>
      </div>
      <div v-else class="empty-state">
        No CODECHECKers added yet
      </div>
    </div>

    <!-- Certificate -->
    <div class="form-section">
      <h3 class="section-title full-width-title">
        CODECHECK certificate: where is it stored (e.g. the DOI) or upload new certificate 
        <span class="required">*</span>
      </h3>
      
      <div class="field-group full-width">
        <div class="certificate-input-group">
          <input
            type="text"
            v-model="metadata.certificateUrl"
            class="pkpFormField__input full-width-input"
            placeholder="https://doi.org/10.5281/zenodo.3624056"
          />
          <button class="pkpButton codecheck-btn" @click="uploadCertificate">Upload Certificate</button>
        </div>
      </div>

      <div class="field-group full-width">
        <label class="field-label">Summary of the CODECHECK</label>
        <textarea
          v-model="metadata.summary"
          class="pkpFormField__input pkpFormField__input--textarea full-width-input"
          rows="4"
          placeholder="The check was straightforward as all material was provided and documented well, but computations took about 3 hours to run."
        ></textarea>
      </div>
    </div>

    <!-- Repositories -->
    <div class="form-section">
      <div class="section-header">
        <h3 class="section-title">Repositories</h3>
        <button class="pkpButton codecheck-btn" @click="showRepositoryModal">+ Repository</button>
      </div>
      
      <div v-if="metadata.repositories.length > 0" class="repository-list">
        <div v-for="(repo, index) in metadata.repositories" :key="index" class="repository-item">
          <a :href="repo" target="_blank" class="repository-link">{{ repo }}</a>
          <button class="pkpButton codecheck-btn pkpButton--isWarnable codecheck-btn-warning" @click="removeRepository(index)">×</button>
        </div>
      </div>
      <div v-else class="empty-state">
        No repositories added yet
      </div>
    </div>

    <!-- Completion Time -->
    <div class="form-section">
      <label class="field-label full-width-label">Time the CODECHECK was completed</label>
      <input
        type="datetime-local"
        v-model="metadata.completionTime"
        class="pkpFormField__input full-width-input"
      />
    </div>

    <!-- Identifier -->
    <div class="form-section">
      <h3 class="section-title full-width-title">
        Certificate Identifier: CODECHECK Certificate ID, Venue Type and Venue Name 
        <span class="required">*</span>
      </h3>

      <div class="certificate-identifier-section">
        <div class="certificate-identifier-input-wrapper">
            <input
                type="text"
                v-model="metadata.identifier"
                placeholder="ID - e.g.: 2025-001"
                class="certificate-identifier-input"
                readonly
            />
            <select
                v-model="certificateIdentifier.venueType"
                class="certificate-identifier-select certificate-identifier-venue-types"
                :disabled="isIdentifierReserved"
            >
                <option disabled value="default" selected>Venue Type</option>
                <option v-for="type in certificateIdentifier.venueTypes" :key="type" :value="type">
                {{ type }}
                </option>
            </select>
            <select
                v-model="certificateIdentifier.venueName"
                class="certificate-identifier-select certificate-identifier-venue-names"
                :disabled="isIdentifierReserved"
            >
                <option disabled value="default" selected>Venue Name</option>
                <option v-for="name in certificateIdentifier.venueNames" :key="name" :value="name">
                {{ name }}
                </option>
            </select>
        </div>

        <div v-if="certificateIdentifier.issueUrl" class="certificate-identifier-link-wrapper">
            <a :href="certificateIdentifier.issueUrl" target="_blank">
                View GitHub Issue
            </a>
        </div>

        <div class="identifier-actions" id="certificate-identifier-button-wrapper">
            <button
                type="button"
                class="pkpButton codecheck-btn"
                :class="isIdentifierReserved ? 'bg-gray' : ''"
                :disabled="isIdentifierReserved"
                @click="reserveIdentifier"
            >
                Reserve Identifier
            </button>
            <button
                type="button"
                class="pkpButton codecheck-btn pkpButton--isWarnable codecheck-btn-warning"
                @click="showRemoveIdentifierModal"
            >
                Remove Identifier
            </button>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="form-footer">
      <button class="pkpButton codecheck-btn pkpButton--isPrimary preview-button codecheck-btn-primary" @click="previewYaml">
        Preview CODECHECK metadata file
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CodecheckMetadataForm',
  props: {
    submission: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    name: {type: String},
    value: {type: String},
  },
  data() {
    return {
      selectedVersion: 'latest',
      publicationType: 'doi',
      uploadedFiles: [],
      metadata: {
        title: '',
        authors: [],
        reference: '',
        source: '',
        codecheckers: [],
        certificateUrl: '',
        summary: '',
        repositories: [],
        completionTime: '',
        identifier: ''
      },
      // Further information neccesary for retrieving and reserving the Certificate Identifier
      certificateIdentifier: {
        venueType: 'default',
        venueName: 'default',
        venueTypes: [],
        venueNames: [],
        issueUrl: '',
      },
      fileCounter: 0,
      fileInputId: 'codecheck-file-input-' + Math.random().toString(36).substr(2, 9)
    }
  },
  computed: {
    // variable that stores if the Identifier was set and thus buttons should be disabled
    isIdentifierReserved() {
      return this.metadata.identifier.trim() !== '';
    }
  },
  mounted() {
    this.loadSubmissionData();
    this.getVenueData();
  },
  methods: {
    loadSubmissionData() {
      const submission = this.submission;
      
      if (submission && submission.publications && submission.publications[0]) {
        this.metadata.title = this.localizeSubmission(
          submission.publications[0].fullTitle,
          submission.locale
        );
        
        if (submission.publications[0].authors) {
          this.metadata.authors = submission.publications[0].authors.map(author => ({
            name: `${author.givenName} ${author.familyName}`.trim(),
            orcid: author.orcid ? `ORCID: ${author.orcid}` : ''
          }));
        }
      }
    },

    localizeSubmission(value, locale) {
      if (typeof value === 'object' && value[locale]) {
        return value[locale];
      }
      return value || '';
    },

    triggerFileUpload() {
      const fileInput = document.getElementById(this.fileInputId);
      if (fileInput) {
        fileInput.click();
      } else {
        console.error('File input not found with ID:', this.fileInputId);
        const form = document.querySelector('.codecheck-metadata-form');
        if (form) {
          const fallbackInput = form.querySelector('input[type="file"]');
          if (fallbackInput) {
            fallbackInput.click();
          }
        }
      }
    },

    handleFileUpload(event) {
      const files = event.target.files;
      if (!files || files.length === 0) return;
      
      for (let i = 0; i < files.length; i++) {
        this.uploadedFiles.push({
          id: this.fileCounter++,
          file: files[i],
          name: files[i].name,
          size: files[i].size,
          description: '',
          checked: false
        });
      }
      event.target.value = '';
    },

    removeUploadedFile(index) {
      if (confirm('Are you sure you want to remove this file?')) {
        this.uploadedFiles.splice(index, 1);
      }
    },

    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    showAuthorModal() {
      if (!this.canUsePkpModal()) {
        this.fallbackAuthorModal();
        return;
      }

      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      openDialog({
        title: "Add Author",
        message: `
          <div class="modal-form">
            <div class="modal-field">
              <label for="author-name" class="modal-label">Name *</label>
              <input type="text" id="author-name" class="modal-input" placeholder="Enter author name" />
            </div>
            <div class="modal-field">
              <label for="author-orcid" class="modal-label">ORCID</label>
              <input type="text" id="author-orcid" class="modal-input" placeholder="0000-0000-0000-0000" />
            </div>
          </div>
        `,
        actions: [
          {
            label: "Cancel",
            callback: (close) => close()
          },
          {
            label: "Add Author",
            isPrimary: true,
            callback: (close) => {
              this.addAuthorFromModal(close);
            }
          }
        ]
      });
    },

    addAuthorFromModal(close) {
      const nameInput = document.getElementById('author-name');
      const orcidInput = document.getElementById('author-orcid');
      
      const name = nameInput?.value || '';
      const orcid = orcidInput?.value || '';
      
      if (name.trim()) {
        this.metadata.authors.push({
          name: name.trim(),
          orcid: orcid ? `ORCID: ${orcid}` : ''
        });
      }
      close();
    },

    fallbackAuthorModal() {
      const name = prompt('Enter author name:');
      if (name && name.trim()) {
        const orcid = prompt('Enter ORCID (optional):');
        this.metadata.authors.push({
          name: name.trim(),
          orcid: orcid ? `ORCID: ${orcid}` : ''
        });
      }
    },

    removeAuthor(index) {
      if (confirm('Are you sure you want to remove this author?')) {
        this.metadata.authors.splice(index, 1);
      }
    },

    showCodecheckerModal() {
      if (!this.canUsePkpModal()) {
        this.fallbackCodecheckerModal();
        return;
      }

      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      openDialog({
        title: "Add CODECHECKer",
        message: `
          <div class="modal-form">
            <div class="modal-field">
              <label for="checker-name" class="modal-label">Name *</label>
              <input type="text" id="checker-name" class="modal-input" placeholder="Enter CODECHECKer name" />
            </div>
            <div class="modal-field">
              <label for="checker-orcid" class="modal-label">ORCID</label>
              <input type="text" id="checker-orcid" class="modal-input" placeholder="0000-0000-0000-0000" />
            </div>
          </div>
        `,
        actions: [
          {
            label: "Cancel",
            callback: (close) => close()
          },
          {
            label: "Add CODECHECKer",
            isPrimary: true,
            callback: (close) => {
              this.addCodecheckerFromModal(close);
            }
          }
        ]
      });
    },

    addCodecheckerFromModal(close) {
      const nameInput = document.getElementById('checker-name');
      const orcidInput = document.getElementById('checker-orcid');
      
      const name = nameInput?.value || '';
      const orcid = orcidInput?.value || '';
      
      if (name.trim()) {
        this.metadata.codecheckers.push({
          name: name.trim(),
          orcid: orcid ? `ORCID: ${orcid}` : ''
        });
      }
      close();
    },

    fallbackCodecheckerModal() {
      const name = prompt('Enter CODECHECKer name:');
      if (name && name.trim()) {
        const orcid = prompt('Enter ORCID (optional):');
        this.metadata.codecheckers.push({
          name: name.trim(),
          orcid: orcid ? `ORCID: ${orcid}` : ''
        });
      }
    },

    removeCodechecker(index) {
      if (confirm('Are you sure you want to remove this CODECHECKer?')) {
        this.metadata.codecheckers.splice(index, 1);
      }
    },

    showRepositoryModal() {
      if (!this.canUsePkpModal()) {
        this.fallbackRepositoryModal();
        return;
      }

      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      openDialog({
        title: "Add Repository",
        message: `
          <div class="modal-form">
            <div class="modal-field">
              <label for="repo-url" class="modal-label">Repository URL *</label>
              <input type="url" id="repo-url" class="modal-input" placeholder="https://github.com/codecheckers/example-workflow" />
            </div>
          </div>
        `,
        actions: [
          {
            label: "Cancel",
            callback: (close) => close()
          },
          {
            label: "Add Repository",
            isPrimary: true,
            callback: (close) => {
              this.addRepositoryFromModal(close);
            }
          }
        ]
      });
    },

    addRepositoryFromModal(close) {
      const urlInput = document.getElementById('repo-url');
      const url = urlInput?.value || '';
      
      if (url.trim()) {
        this.metadata.repositories.push(url.trim());
      }
      close();
    },

    fallbackRepositoryModal() {
      const url = prompt('Enter repository URL:');
      if (url && url.trim()) {
        this.metadata.repositories.push(url.trim());
      }
    },

    removeRepository(index) {
      if (confirm('Are you sure you want to remove this repository?')) {
        this.metadata.repositories.splice(index, 1);
      }
    },

    uploadCertificate() {
      alert('Certificate upload functionality will be implemented here.');
    },

    async getVenueData() {
      let codecheckApiUrl = pkp.context.apiBaseUrl.replace(/\/api\/v1\/?$/, '');
      codecheckApiUrl += '/codecheck_api';

      try {
          const response = await fetch(`${codecheckApiUrl}/getVenueData`, {
              method: 'POST',
              headers: {
              'Content-Type': 'application/json',
              'X-Csrf-Token': pkp.currentUser.csrfToken,
              },
          });
          const data = await response.json();

          if (data.success) {
              console.log('Success:', data.message);
              this.certificateIdentifier.venueTypes = data.venueTypes;
              this.certificateIdentifier.venueNames = data.venueNames;
              console.log('Venue types:', this.certificateIdentifier.venueTypes);
              console.log('Venue names:', this.certificateIdentifier.venueNames);
          } else {
              console.error('Error:', data.error);
          }
      } catch (error) {
          console.error('Failed to fetch venue data:', error);
      }
    },

    async reserveIdentifier() {
      if (this.certificateIdentifier.venueType === 'default' || this.certificateIdentifier.venueName === 'default') {
        alert('Please select both a Venue Type and a Venue Name.');
        return;
      }

      console.log(this.certificateIdentifier.venueType + ', ' + this.certificateIdentifier.venueName);

      let codecheckApiUrl = pkp.context.apiBaseUrl.replace(/\/api\/v1\/?$/, '');
      codecheckApiUrl += '/codecheck_api';

      try {
          const response = await fetch(`${codecheckApiUrl}/reserveIdentifier`, {
              method: 'POST',
              headers: {
              'Content-Type': 'application/json',
              'X-Csrf-Token': pkp.currentUser.csrfToken,
              },
              body: JSON.stringify({
                venueType: this.certificateIdentifier.venueType,
                venueName: this.certificateIdentifier.venueName,
              }),
          });
          const data = await response.json();

          if (data.success) {
              this.metadata.identifier = data.identifier;
              this.certificateIdentifier.issueUrl = data.issueUrl;
              this.$emit('update', this.metadata.identifier);
              alert(`New identifier reserved: ${data.identifier}`);
              console.log('New identifier reserved: ', data.identifier, data.issueUrl);
          } else {
              console.error('Error:', data.error);
          }
      } catch (error) {
          console.error('Request failed:', error);
      }
    },

    removeIdentifier(close) {
      this.metadata.identifier = '';
      this.certificateIdentifier.issueUrl = '';
      this.$emit('update', this.metadata.identifier);

      close();
    },

    showRemoveIdentifierModal() {
      if (!this.canUsePkpModal()) {
        this.fallbackCertificateIdentifierModal();
        return;
      }

      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      openDialog({
        title: "Remove Certificate Identifier",
        message: `
          <div class="modal-form">
            <div class="modal-field">
              <label for="repo-url" class="modal-label">Are you sure you want to remove this identifier?</label>
            </div>
          </div>
        `,
        actions: [
          {
            label: "No",
            callback: (close) => close()
          },
          {
            label: "Yes",
            isPrimary: true,
            callback: (close) => {
              this.removeIdentifier(close);
            }
          }
        ]
      });
    },

    fallbackCertificateIdentifierModal() {
      if(confirm('Are you sure you want to remove this identifier?')) {
        this.metadata.identifier = '';
        this.certificateIdentifier.issueUrl = '';
        this.$emit('update', this.metadata.identifier);
      }
    },

    previewYaml() {
      const yamlContent = this.generateYaml();
      
      if (!this.canUsePkpModal()) {
        this.openYamlInNewWindow(yamlContent);
        return;
      }

      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      openDialog({
        title: "CODECHECK Metadata Preview",
        message: `
          <div class="yaml-preview-container">
            <pre class="yaml-content">${this.escapeHtml(yamlContent)}</pre>
          </div>
        `,
        size: 'large',
        actions: [
          {
            label: "Close",
            callback: (close) => close()
          }
        ]
      });
    },

    openYamlInNewWindow(yamlContent) {
      const newWindow = window.open('', '_blank');
      newWindow.document.write(`
        <!DOCTYPE html>
        <html>
          <head>
            <title>CODECHECK Metadata Preview</title>
            <style>
              body { font-family: monospace; padding: 20px; background: #f5f5f5; }
              pre { background: white; padding: 20px; border-radius: 5px; border: 1px solid #ddd; }
            </style>
          </head>
          <body>
            <h2>CODECHECK Metadata Preview</h2>
            <pre>${this.escapeHtml(yamlContent)}</pre>
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
          </body>
        </html>
      `);
    },

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },

    generateYaml() {
      let yaml = `version: ${this.selectedVersion}
publication_type: ${this.publicationType}

manifest:
`;

      this.uploadedFiles.forEach(file => {
        if (file.name) {
          yaml += `  - file: "${file.name}"\n`;
          yaml += `    description: "${file.description || ''}"\n`;
          yaml += `    checked: ${file.checked}\n`;
        }
      });

      yaml += `\npaper:
  title: "${this.metadata.title || 'CHECK-PUB'}"
  authors:
`;

      this.metadata.authors.forEach(author => {
        yaml += `    - name: "${author.name}"\n`;
        if (author.orcid) {
          yaml += `      orcid: "${author.orcid}"\n`;
        }
      });

      yaml += `  reference: "${this.metadata.reference || ''}"\n`;
      yaml += `  source: "${this.metadata.source || ''}"\n`;

      yaml += `\ncodecheckers:
`;

      this.metadata.codecheckers.forEach(checker => {
        yaml += `  - name: "${checker.name}"\n`;
        if (checker.orcid) {
          yaml += `    orcid: "${checker.orcid}"\n`;
        }
      });

      yaml += `\ncertificate:
  url: "${this.metadata.certificateUrl || ''}"
  summary: "${this.metadata.summary || 'The check was straightforward as all material was provided and documented well, but computations took about 3 hours to run.'}"

repositories:
`;

      this.metadata.repositories.forEach(repo => {
        if (repo) {
          yaml += `  - "${repo}"\n`;
        }
      });

      yaml += `\ncompletion_time: "${this.metadata.completionTime || '2019-01-01T13:00:00'}"\n`;
      yaml += `identifier: "${this.metadata.identifier || ''}"\n`;

      return yaml;
    },

    canUsePkpModal() {
      return typeof pkp !== 'undefined' && pkp.modules && pkp.modules.useModal;
    }
  }
}
</script>

<style>
/* Global styles that will work in modal */
.codecheck-metadata-form {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #fff;
  border-radius: 4px;
  overflow: hidden;
}

.codecheck-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #ddd;
  background: #fff;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.workflow-title {
  margin: 0;
  font-size: inherit;
  font-weight: 700;
  text-transform: uppercase;
  line-height: 1.75;
  color: #333;
}

.version-selector {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.version-label {
  font-size: 13px;
  color: #666;
}

.version-select {
  padding: 0.25rem 0.5rem;
  border: 1px solid #ccc;
  border-radius: 3px;
  font-size: 13px;
}

.publication-section {
  padding: 1rem 1.5rem;
  background: #f5f5f5;
  border-bottom: 1px solid #ddd;
}

.radio-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.radio-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 14px;
}

.radio-label {
  color: #333;
}

.form-section {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e5e5;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #333;
}

.full-width-title {
  width: 100%;
}

.required {
  color: #d9534f;
}

.section-description {
  margin: 0 0 1rem 0;
  font-size: 13px;
  color: #666;
  font-style: italic;
}

.manifest-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

.manifest-table th,
.manifest-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #dee2e6;
}

.manifest-table th {
  background: #f8f9fa;
  font-weight: 600;
  font-size: 14px;
}

.manifest-row {
  border-bottom: 1px solid #dee2e6;
}

.file-checkbox {
  margin: 0;
}

.file-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.file-name {
  font-weight: 600;
  font-size: 14px;
}

.file-size {
  font-size: 12px;
  color: #6c757d;
}

.field-group {
  margin-bottom: 1.5rem;
}

.full-width {
  width: 100%;
}

.field-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.field-label {
  font-size: 14px;
  font-weight: 600;
  color: #333;
  display: block;
  margin-bottom: 0.5rem;
}

.full-width-label {
  width: 100%;
}

.full-width-input {
  width: 100%;
  box-sizing: border-box;
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.list-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
}

.item-content {
  flex: 1;
}

.item-name {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.item-orcid {
  font-size: 12px;
  color: #6c757d;
}

.empty-state {
  padding: 1rem;
  text-align: center;
  color: #6c757d;
  font-style: italic;
  background: #f8f9fa;
  border-radius: 4px;
}

.certificate-input-group {
  display: flex;
  gap: 0.75rem;
  align-items: flex-end;
}

.certificate-input-group input {
  flex: 1;
}

.repository-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.repository-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
}

.repository-link {
  color: #007ab2;
  text-decoration: none;
  font-size: 14px;
  word-break: break-all;
}

.repository-link:hover {
  text-decoration: underline;
  color: #005a87;
}

.identifier-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.form-footer {
  padding: 1.5rem;
  background: #f8f9fa;
  text-align: right;
}

.preview-button {
  min-width: 220px;
  font-weight: 600;
}

/* PKP Button styles */
.codecheck-btn {
  display: inline-block;
  padding: 0.1rem 1rem;
  border: 1px solid #007ab2;
  border-radius: 3px;
  background: #007ab2;
  color: white;
  text-decoration: none;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.codecheck-btn:hover {
  background: #005a87;
  border-color: #005a87;
}

.codecheck-btn-primary {
  background: #007ab2;
  border-color: #007ab2;
}

.codecheck-btn-warning {
  background: #d9534f;
  border-color: #d9534f;
}

.codecheck-btn-warning:hover {
  background: #c9302c;
  border-color: #c9302c;
}

/* Form field styles */
.pkpFormField__input {
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 3px;
  font-size: 14px;
  box-sizing: border-box;
}

.pkpFormField__input--textarea {
  min-height: 100px;
  resize: vertical;
  font-family: inherit;
}

.pkpFormField__input:focus {
  outline: none;
  border-color: #007ab2;
  box-shadow: 0 0 0 2px rgba(0, 122, 178, 0.2);
}

/* Modal form styles */
.modal-form {
  padding: 1rem 0;
}

.modal-field {
  margin-bottom: 1rem;
}

.modal-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #333;
}

.modal-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 3px;
  font-size: 14px;
  box-sizing: border-box;
}

.modal-input:focus {
  outline: none;
  border-color: #007ab2;
  box-shadow: 0 0 0 2px rgba(0, 122, 178, 0.2);
}

/* YAML Preview styles */
.yaml-preview-container {
  max-height: 500px;
  overflow-y: auto;
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 4px;
}

.yaml-content {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 12px;
  line-height: 1.4;
  margin: 0;
  white-space: pre-wrap;
  word-wrap: break-word;
}

a {
    word-break: break-all;
}

.certificate-identifier-input-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.certificate-identifier-input {
    flex: 1;
    font-size:14px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    height: 2.5rem;
}

.certificate-identifier-select:disabled {
    /* Centeres the Text in the select */
    text-align: center;
    text-align-last: center;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: none !important; /* removes arrow background */
    background-color: #868686;
    color: #ffffff;
    cursor: not-allowed;
    pointer-events: none;
    opacity: 0.6;
    font-weight: 600;
}

.certificate-identifier-venue-types {
    font-size:14px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    height: 2.5rem;
    background: #fff;
}

.certificate-identifier-venue-names {
    font-size:14px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    height: 2.5rem;
    background: #fff;
}

#certificate-identifier-button-wrapper {
    padding-top: 0.5rem;
}

.certificate-identifier-button {
    font-size:.875rem;
    color: white;
    line-height:1.25rem;
    border: none;
    padding: .4375rem .75rem;
    border-radius: 4px;
    margin-right: 0.5rem;
    font-weight: 600;
    cursor: pointer;
}

.bg-blue {
    background: #006798;
}

.bg-blue:hover {
  background: #005580;
}

.bg-red {
    background: #dc3545;
}

.bg-red:hover {
  background: #c82333;
}

.bg-gray {
    background: #868686;
}

.certificate-identifier-button:disabled {
    opacity: 0.6;
    pointer-events: none;
    cursor: not-allowed;
}
</style>