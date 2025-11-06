<template>
  <div class="codecheck-metadata-form">
    <div v-if="loading" class="loading-state">
      <span class="pkpSpinner"></span>
      <p>{{ t('common.loading') }}</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button class="pkpButton" @click="loadData">{{ t('common.retry') }}</button>
    </div>

    <div v-else-if="dataLoaded">
      <div class="codecheck-header">
        <div class="header-content">
          <div class="version-selector">
            <label class="version-label">{{ t('plugins.generic.codecheck.configVersion') }}</label>
            <select v-model="metadata.configVersion" class="version-select">
              <option value="latest">latest</option>
              <option value="1.0">1.0</option>
            </select>
          </div>
        </div>
      </div>

      <div class="publication-section">
        <div class="radio-options">
          <label class="radio-option">
            <input type="radio" v-model="metadata.publicationType" value="doi" name="publication-type" />
            <span class="radio-label">{{ t('plugins.generic.codecheck.publishWithDOI') }}</span>
          </label>
          <label class="radio-option">
            <input type="radio" v-model="metadata.publicationType" value="separate" name="publication-type" />
            <span class="radio-label">{{ t('plugins.generic.codecheck.publishSeparate') }}</span>
          </label>
        </div>
      </div>

      <div class="form-section read-only-section">
        <h3 class="section-title">{{ t('plugins.generic.codecheck.paperMetadata.title') }}</h3>
        
        <div class="info-grid">
          <div class="info-item">
            <label class="info-label">{{ t('common.title') }}:</label>
            <div class="info-value">{{ submissionData.title || t('common.notAvailable') }}</div>
          </div>

          <div class="info-item">
            <label class="info-label">{{ t('plugins.generic.codecheck.paperMetadata.authors') }}:</label>
            <div class="info-value">
              <template v-if="submissionData.authors && submissionData.authors.length > 0">
                <div v-for="(author, index) in submissionData.authors" :key="'author-' + index" class="author-item">
                  {{ author.name || t('common.unknown') }}
                  <span v-if="author.orcid" class="orcid-badge">{{ author.orcid }}</span>
                </div>
              </template>
              <em v-else>{{ t('plugins.generic.codecheck.paperMetadata.noAuthors') }}</em>
            </div>
          </div>

          <div class="info-item" v-if="submissionData.doi">
            <label class="info-label">{{ t('metadata.property.displayName.doi') }}:</label>
            <div class="info-value">
              <a :href="'https://doi.org/' + submissionData.doi" target="_blank">
                {{ submissionData.doi }}
              </a>
            </div>
          </div>

          <div class="info-item" v-if="submissionData.codeRepository">
            <label class="info-label">{{ t('plugins.generic.codecheck.codeRepository') }}:</label>
            <div class="info-value">
              <a :href="submissionData.codeRepository" target="_blank">
                {{ submissionData.codeRepository }}
              </a>
            </div>
          </div>

          <div class="info-item" v-if="submissionData.dataRepository">
            <label class="info-label">{{ t('plugins.generic.codecheck.dataRepository') }}:</label>
            <div class="info-value">
              <a :href="submissionData.dataRepository" target="_blank">
                {{ submissionData.dataRepository }}
              </a>
            </div>
          </div>

          <div class="info-item" v-if="submissionData.manifestFiles">
            <label class="info-label">{{ t('plugins.generic.codecheck.manifestFiles.label') }}:</label>
            <div class="info-value">
              <pre class="manifest-preview">{{ submissionData.manifestFiles }}</pre>
            </div>
          </div>
        </div>
      </div>

      <div class="form-section form-details">
        <h3 class="section-title">{{ t('plugins.generic.codecheck.details.title') }}</h3>
        <p class="section-description">{{ t('plugins.generic.codecheck.workflow.description') }}</p>

        <div class="field-group">
          <div class="field-header">
            <label class="field-label">{{ t('plugins.generic.codecheck.manifest.title') }} <span class="required">*</span></label>
            <button type="button" class="pkpButton btn-add" @click="triggerFileUpload">{{ t('plugins.generic.codecheck.manifestFiles.addFile') }}</button>
            <input 
              type="file" 
              ref="fileInput"
              @change="handleFileUpload" 
              multiple 
              style="position: absolute; left: -9999px; opacity: 0;"
              accept=".pdf,.csv,.txt,.yml,.yaml,.json,.zip,.png,.jpg"
            />
          </div>
          <p class="field-description">{{ t('plugins.generic.codecheck.manifest.subtitle') }}</p>
          
          <table class="pkpTable manifest-table" v-if="metadata.manifest && metadata.manifest.length > 0">
            <thead>
              <tr>
                <th width="20"></th>
                <th>{{ t('plugins.generic.codecheck.manifest.outputFile') }}</th>
                <th>{{ t('plugins.generic.codecheck.manifest.description') }}</th>
                <th width="80"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(file, index) in metadata.manifest" :key="'manifest-' + index" class="manifest-row">
                <td>
                  <input type="checkbox" v-model="file.checked" class="file-checkbox" />
                </td>
                <td>
                  <div class="file-info">
                    <a 
                      href="#" 
                      @click.prevent="downloadFile(file.filePath, file.file)"
                      class="file-name file-link"
                      v-if="file.filePath"
                      :title="t('plugins.generic.codecheck.downloadFile')"
                    >
                      {{ file.file }}
                    </a>
                    <span v-else class="file-name">{{ file.file }}</span>
                    <span class="file-size" v-if="file.size">({{ formatFileSize(file.size) }})</span>
                  </div>
                </td>
                <td>
                  <input
                    type="text"
                    v-model="file.comment"
                    class="pkpFormField__input"
                    :placeholder="t('plugins.generic.codecheck.manifestFiles.commentPlaceholder')"
                  />
                </td>
                <td>
                  <button 
                    type="button"
                    class="pkpButton codecheck-btn pkpButton--close" 
                    @click="removeManifestFile(index)"
                  >×</button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty-state">
            {{ t('plugins.generic.codecheck.manifest.emptyState') }}
          </div>
        </div>

        <div class="field-group">
          <div class="field-header">
            <label class="field-label">{{ t('plugins.generic.codecheck.repositories.title') }}</label>
            <button type="button" class="pkpButton btn-add" @click="addRepository">{{ t('plugins.generic.codecheck.repositories.add') }}</button>
          </div>
          <p class="field-description">{{ t('plugins.generic.codecheck.repositories.description') }}</p>
          
          <div v-if="repositories.length > 0" class="repository-list">
            <div v-for="(repo, index) in repositories" :key="'repo-' + index" class="repository-item">
              <input
                type="url"
                v-model="repositories[index]"
                class="pkpFormField__input"
                :placeholder="t('plugins.generic.codecheck.repository.placeholder')"
              />
              <button 
                type="button"
                class="pkpButton codecheck-btn pkpButton--close" 
                @click="removeRepository(index)"
              >×</button>
            </div>
          </div>
          <div v-else class="empty-state">
            {{ t('plugins.generic.codecheck.repositories.emptyState') }}
          </div>
        </div>

        <div class="field-group">
          <div class="field-header">
            <label class="field-label">{{ t('plugins.generic.codecheck.codecheckers.title') }} <span class="required">*</span></label>
            <button type="button" class="pkpButton btn-add" @click="showCodecheckerModal">{{ t('plugins.generic.codecheck.codecheckers.add') }}</button>
          </div>
          
          <div v-if="metadata.codecheckers && metadata.codecheckers.length > 0" class="items-list codecheckers-list">
            <div v-for="(checker, index) in metadata.codecheckers" :key="'checker-' + index" class="list-item">
              <div class="item-content">
                <div class="item-name">{{ checker.name }}</div>
                <div class="item-orcid" v-if="checker.orcid">ORCID: {{ checker.orcid }}</div>
              </div>
              <button 
                type="button"
                class="pkpButton codecheck-btn pkpButton--close" 
                @click="removeCodechecker(index)"
              >×</button>
            </div>
          </div>
          <div v-else class="empty-state">
            {{ t('plugins.generic.codecheck.codecheckers.emptyState') }}
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">{{ t('plugins.generic.codecheck.certificate.summary') }} <span class="required">*</span></label>
          <p class="field-description">{{ t('plugins.generic.codecheck.certificate.summaryDescription') }}</p>
          <textarea
            v-model="metadata.summary"
            class="pkpFormField__input pkpFormField__input--textarea full-width"
            rows="6"
            :placeholder="t('plugins.generic.codecheck.certificate.summaryPlaceholder')"
          ></textarea>
        </div>

        <div class="field-group">
          <label class="field-label">{{ t('plugins.generic.codecheck.certificate.reportUrl') }}</label>
          <p class="field-description">{{ t('plugins.generic.codecheck.certificate.reportUrlDescription') }}</p>
          <input
            type="url"
            v-model="metadata.reportUrl"
            class="pkpFormField__input full-width"
            placeholder="https://zenodo.org/record/12345"
          />
        </div>

        <div class="field-group">
          <label class="field-label">{{ t('plugins.generic.codecheck.completionTime.label') }}</label>
          <input
            type="datetime-local"
            v-model="metadata.checkTime"
            class="pkpFormField__input full-width"
          />
        </div>
      </div>

      <div class="form-section identifier-section">
        <h3 class="section-title">{{ t('plugins.generic.codecheck.identifier.title') }} <span class="required">*</span></h3>
        
        <div class="field-group">
          <input
            type="text"
            v-model="metadata.certificate"
            class="pkpFormField__input full-width"
            :placeholder="t('plugins.generic.codecheck.identifier.label')"
          />
        </div>
        
        <div class="identifier-actions">
          <button type="button" class="pkpButton codecheck-btn" @click="saveIdentifier">{{ t('plugins.generic.codecheck.identifier.save') }}</button>
          <button type="button" class="pkpButton codecheck-btn" @click="reserveIdentifier">{{ t('plugins.generic.codecheck.identifier.reserve') }}</button>
          <button type="button" class="pkpButton codecheck-btn pkpButton--isWarnable" @click="removeIdentifier">{{ t('plugins.generic.codecheck.identifier.remove') }}</button>
        </div>
      </div>

      <div class="form-footer">
        <div class="footer-actions">
          <button 
            type="button"
            class="pkpButton codecheck-btn" 
            @click="previewYaml"
            :disabled="!canPreview"
          >
            {{ t('plugins.generic.codecheck.previewYaml') }}
          </button>
          <button 
            type="button"
            class="pkpButton codecheck-btn pkpButton--isPrimary" 
            @click="saveMetadata"
            :disabled="saving"
          >
            {{ saving ? t('common.saving') : t('common.save') }}
          </button>
        </div>
        <div v-if="saveMessage" class="save-message" :class="saveMessageType">
          {{ saveMessage }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const { useLocalize } = pkp.modules.useLocalize;

export default {
  name: 'CodecheckMetadataForm',
  props: {
    submission: {
      type: Object,
      required: true
    },
    canEdit: {
      type: Boolean,
      default: true
    }
  },
  setup() {
    const { t } = useLocalize();
    return { t };
  },
  data() {
    return {
      loading: true,
      saving: false,
      dataLoaded: false,
      error: null,
      saveMessage: '',
      saveMessageType: '',
      repositories: [],
      submissionData: {
        id: null,
        title: '',
        authors: [],
        doi: '',
        codeRepository: '',
        dataRepository: '',
        manifestFiles: '',
        dataAvailabilityStatement: ''
      },
      metadata: {
        configVersion: 'latest',
        publicationType: 'doi',
        manifest: [],
        repository: '',
        codecheckers: [],
        certificate: '',
        checkTime: '',
        summary: '',
        reportUrl: ''
      }
    }
  },
  computed: {
    canPreview() {
      return this.metadata.manifest.length > 0 && 
             this.metadata.codecheckers.length > 0 &&
             this.metadata.certificate;
    }
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      this.loading = true;
      this.error = null;
      this.dataLoaded = false;
      
      try {
        if (!this.submission || !this.submission.id) {
          throw new Error('Invalid submission object');
        }

        const submissionId = this.submission.id;
        const pathParts = window.location.pathname.split('/');
        const contextName = pathParts[3];
        const apiUrl = `${window.location.origin}/ojs/index.php/${contextName}/codecheck/metadata?submissionId=${submissionId}`;
        
        const response = await fetch(apiUrl, {
          headers: {
            'X-Csrf-Token': pkp.currentUser.csrfToken
          }
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        
        this.submissionData = {
          id: data.submission?.id || submissionId,
          title: data.submission?.title || '',
          authors: Array.isArray(data.submission?.authors) ? data.submission.authors : [],
          doi: data.submission?.doi || '',
          codeRepository: data.submission?.codeRepository || '',
          dataRepository: data.submission?.dataRepository || '',
          manifestFiles: data.submission?.manifestFiles || '',
          dataAvailabilityStatement: data.submission?.dataAvailabilityStatement || ''
        };
        
        if (data.codecheck && typeof data.codecheck === 'object') {
          this.metadata = {
            configVersion: data.codecheck.configVersion || data.codecheck.config_version || 'latest',
            publicationType: data.codecheck.publicationType || data.codecheck.publication_type || 'doi',
            manifest: Array.isArray(data.codecheck.manifest) ? data.codecheck.manifest : 
                      (typeof data.codecheck.manifest === 'string' ? JSON.parse(data.codecheck.manifest) : []),
            repository: data.codecheck.repository || '',
            codecheckers: Array.isArray(data.codecheck.codecheckers) ? data.codecheck.codecheckers : 
                          (typeof data.codecheck.codecheckers === 'string' ? JSON.parse(data.codecheck.codecheckers) : []),
            certificate: data.codecheck.certificate || '',
            checkTime: data.codecheck.checkTime || data.codecheck.check_time ? 
                      this.formatDateTimeLocal(data.codecheck.checkTime || data.codecheck.check_time) : '',
            summary: data.codecheck.summary || '',
            reportUrl: data.codecheck.reportUrl || data.codecheck.report_url || ''
          };
          
          if (data.codecheck.repository) {
            this.repositories = data.codecheck.repository.split(',').map(r => r.trim()).filter(r => r);
          }
        }
        
        this.dataLoaded = true;
        
      } catch (error) {
        console.error('Load error:', error);
        this.error = this.t('plugins.generic.codecheck.loadError') + ': ' + error.message;
      } finally {
        this.loading = false;
      }
    },

    triggerFileUpload() {
      this.$refs.fileInput.click();
    },

    async handleFileUpload(event) {
      const files = event.target.files;
      if (!files || files.length === 0) return;
      
      this.saving = true;
      this.saveMessage = this.t('plugins.generic.codecheck.uploadingFiles');
      
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        try {
          const result = await this.uploadFile(file);
          
          if (result.success) {
            this.metadata.manifest.push({
              file: result.filename,
              size: result.size,
              filePath: result.filePath,
              comment: '',
              checked: false
            });
          } else {
            this.showMessage('Failed to upload: ' + file.name, 'error');
          }
        } catch (error) {
          console.error('Upload error:', error);
          this.showMessage('Failed to upload: ' + file.name, 'error');
        }
      }
      
      event.target.value = '';
      this.saving = false;
      this.saveMessage = '';
    },

    async uploadFile(file) {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('submissionId', this.submission.id);
      
      const pathParts = window.location.pathname.split('/');
      const contextName = pathParts[3];
      const uploadUrl = `${window.location.origin}/ojs/index.php/${contextName}/codecheck/upload`;
      
      const response = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'X-Csrf-Token': pkp.currentUser.csrfToken
        },
        body: formData
      });
      
      if (!response.ok) {
        throw new Error('Upload failed');
      }
      
      return await response.json();
    },

    downloadFile(filePath, fileName) {
      if (!filePath) {
        alert('File path not available');
        return;
      }
      
      const pathParts = window.location.pathname.split('/');
      const contextName = pathParts[3];
      const downloadUrl = `${window.location.origin}/ojs/index.php/${contextName}/codecheck/download?file=${encodeURIComponent(filePath)}`;
      
      window.open(downloadUrl, '_blank');
    },

    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    removeManifestFile(index) {
      if (confirm(this.t('plugins.generic.codecheck.manifest.removeConfirm'))) {
        this.metadata.manifest.splice(index, 1);
      }
    },

    addRepository() {
      this.repositories.push('');
    },

    removeRepository(index) {
      if (confirm(this.t('plugins.generic.codecheck.repositories.removeConfirm'))) {
        this.repositories.splice(index, 1);
      }
    },

    showCodecheckerModal() {
      if (this.canUsePkpModal()) {
        this.showPkpCodecheckerModal();
      } else {
        this.showFallbackCodecheckerModal();
      }
    },

    canUsePkpModal() {
      return typeof pkp !== 'undefined' && pkp.modules && pkp.modules.useModal;
    },

    showPkpCodecheckerModal() {
      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      const modalHtml = '<div class="modal-form">' +
        '<div class="modal-field">' +
        '<label for="checker-name" class="modal-label">' + this.t('plugins.generic.codecheck.codecheckers.enterName') + '</label>' +
        '<input type="text" id="checker-name" class="modal-input" placeholder="' + this.t('plugins.generic.codecheck.codecheckers.enterName') + '" />' +
        '</div>' +
        '<div class="modal-field">' +
        '<label for="checker-orcid" class="modal-label">' + this.t('plugins.generic.codecheck.codecheckers.enterOrcid') + '</label>' +
        '<input type="text" id="checker-orcid" class="modal-input" placeholder="0000-0000-0000-0000" />' +
        '</div>' +
        '</div>';

      openDialog({
        title: this.t('plugins.generic.codecheck.codecheckers.addCodechecker'),
        message: modalHtml,
        actions: [
          {
            label: this.t('plugins.generic.codecheck.modal.cancel'),
            callback: (close) => close()
          },
          {
            label: this.t('plugins.generic.codecheck.modal.add'),
            isPrimary: true,
            callback: (close) => {
              const nameInput = document.getElementById('checker-name');
              const orcidInput = document.getElementById('checker-orcid');
              
              const name = nameInput?.value || '';
              const orcid = orcidInput?.value || '';
              
              if (name.trim()) {
                this.metadata.codecheckers.push({
                  name: name.trim(),
                  orcid: orcid.trim()
                });
              }
              close();
            }
          }
        ]
      });
    },

    showFallbackCodecheckerModal() {
      const name = prompt(this.t('plugins.generic.codecheck.codecheckers.enterName'));
      if (name && name.trim()) {
        const orcid = prompt(this.t('plugins.generic.codecheck.codecheckers.enterOrcid'));
        this.metadata.codecheckers.push({
          name: name.trim(),
          orcid: orcid ? orcid.trim() : ''
        });
      }
    },

    removeCodechecker(index) {
      if (confirm(this.t('plugins.generic.codecheck.codecheckers.removeConfirm'))) {
        this.metadata.codecheckers.splice(index, 1);
      }
    },

    saveIdentifier() {
      if (this.metadata.certificate) {
        alert(this.t('plugins.generic.codecheck.identifier.saved', { identifier: this.metadata.certificate }));
      } else {
        alert(this.t('plugins.generic.codecheck.identifier.enterFirst'));
      }
    },

    reserveIdentifier() {
      const year = new Date().getFullYear();
      const number = String(Math.floor(Math.random() * 9999)).padStart(4, '0');
      this.metadata.certificate = `CODECHECK-${year}-${number}`;
      alert(this.t('plugins.generic.codecheck.identifier.reserved', { identifier: this.metadata.certificate }));
    },

    removeIdentifier() {
      if (confirm(this.t('plugins.generic.codecheck.identifier.removeConfirm'))) {
        this.metadata.certificate = '';
      }
    },

    async saveMetadata() {
      if (!this.validateForm()) {
        return;
      }

      this.saving = true;
      this.saveMessage = '';

      try {
        const dataToSave = {
          config_version: this.metadata.configVersion,
          publication_type: this.metadata.publicationType,
          manifest: this.metadata.manifest,
          repository: this.repositories.join(', '),
          codecheckers: this.metadata.codecheckers,
          certificate: this.metadata.certificate,
          check_time: this.metadata.checkTime,
          summary: this.metadata.summary,
          report_url: this.metadata.reportUrl
        };

        console.log('Saving CODECHECK data:', dataToSave);

        const submissionId = this.submission.id;
        const pathParts = window.location.pathname.split('/');
        const contextName = pathParts[3];
        const apiUrl = `${window.location.origin}/ojs/index.php/${contextName}/codecheck/metadata?submissionId=${submissionId}`;
        
        const response = await fetch(apiUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Csrf-Token': pkp.currentUser.csrfToken
          },
          body: JSON.stringify(dataToSave)
        });

        if (!response.ok) {
          throw new Error('Failed to save');
        }

        this.showMessage(this.t('plugins.generic.codecheck.savedSuccessfully'), 'success');
      } catch (error) {
        console.error('Save error:', error);
        this.showMessage(this.t('plugins.generic.codecheck.saveFailed'), 'error');
      } finally {
        this.saving = false;
      }
    },

    async previewYaml() {
      try {
        const yamlContent = this.generateYamlContent();
        
        if (this.canUsePkpModal()) {
          this.showYamlModal(yamlContent);
        } else {
          this.showYamlFallback(yamlContent);
        }
        
      } catch (error) {
        this.showMessage(this.t('plugins.generic.codecheck.yamlPreviewFailed'), 'error');
      }
    },

    generateYamlContent() {
      const yaml = [];
      
      yaml.push('---');
      yaml.push('version: https://codecheck.org.uk/spec/config/' + this.metadata.configVersion + '/');
      yaml.push('paper:');
      yaml.push('  title: "' + (this.submissionData.title || 'Untitled') + '"');
      yaml.push('  authors:');
      
      if (this.submissionData.authors && this.submissionData.authors.length > 0) {
        this.submissionData.authors.forEach(author => {
          yaml.push('    - name: ' + author.name);
          if (author.orcid) {
            yaml.push('      ORCID: ' + author.orcid);
          }
        });
      }
      
      if (this.submissionData.doi) {
        yaml.push('  reference: https://doi.org/' + this.submissionData.doi);
      }
      
      yaml.push('manifest:');
      if (this.metadata.manifest && this.metadata.manifest.length > 0) {
        this.metadata.manifest.forEach(file => {
          yaml.push('  - file: ' + file.file);
          if (file.comment) {
            yaml.push('    comment: "' + file.comment + '"');
          }
        });
      }
      
      yaml.push('codechecker:');
      if (this.metadata.codecheckers && this.metadata.codecheckers.length > 0) {
        this.metadata.codecheckers.forEach(checker => {
          yaml.push('  - name: ' + checker.name);
          if (checker.orcid) {
            yaml.push('    ORCID: ' + checker.orcid);
          }
        });
      }
      
      if (this.metadata.summary) {
        yaml.push('summary: >');
        const summaryLines = this.metadata.summary.split('\n');
        summaryLines.forEach(line => {
          yaml.push('  ' + line);
        });
      }
      
      if (this.repositories.length > 0) {
        yaml.push('repository: ' + this.repositories[0]);
      }
      
      if (this.metadata.checkTime) {
        yaml.push('check_time: "' + this.metadata.checkTime + '"');
      }
      
      if (this.metadata.certificate) {
        yaml.push('certificate: ' + this.metadata.certificate);
      }
      
      if (this.metadata.reportUrl) {
        yaml.push('report: ' + this.metadata.reportUrl);
      }
      
      return yaml.join('\n');
    },

    showYamlModal(yamlContent) {
      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      const downloadFunc = 'downloadCodecheckYaml_' + Date.now();
      window[downloadFunc] = function() {
        const blob = new Blob([yamlContent], { type: 'text/yaml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'codecheck.yml';
        a.click();
        URL.revokeObjectURL(url);
      };

      const modalHtml = '<div class="yaml-modal-container">' +
        '<pre class="yaml-preview-content">' + this.escapeHtml(yamlContent) + '</pre>' +
        '</div>';

      openDialog({
        title: this.t('plugins.generic.codecheck.yaml.previewTitle'),
        message: modalHtml,
        actions: [
          {
            label: this.t('plugins.generic.codecheck.yaml.download'),
            isPrimary: true,
            callback: (close) => {
              window[downloadFunc]();
              delete window[downloadFunc];
              close();
            }
          },
          {
            label: this.t('plugins.generic.codecheck.yaml.close'),
            callback: (close) => {
              delete window[downloadFunc];
              close();
            }
          }
        ]
      });
    },

    showYamlFallback(yamlContent) {
      const win = window.open('', '_blank');
      const escapedYaml = this.escapeHtml(yamlContent);
      const yamlJson = JSON.stringify(yamlContent);
      
      const html = '<!DOCTYPE html>' +
        '<html>' +
        '<head>' +
        '<title>CODECHECK Metadata Preview</title>' +
        '<style>' +
        'body { font-family: "Courier New", monospace; padding: 20px; background: #f5f5f5; }' +
        '.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }' +
        'h2 { color: #007ab2; margin-top: 0; }' +
        'pre { background: #2d2d2d; color: #f8f8f2; padding: 20px; border-radius: 4px; overflow-x: auto; line-height: 1.6; }' +
        '.actions { margin-top: 20px; display: flex; gap: 10px; }' +
        'button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; }' +
        '.download-btn { background: #007ab2; color: white; }' +
        '.download-btn:hover { background: #005a87; }' +
        '.close-btn { background: #dc3545; color: white; }' +
        '.close-btn:hover { background: #c82333; }' +
        '</style>' +
        '</head>' +
        '<body>' +
        '<div class="container">' +
        '<h2>📄 CODECHECK Metadata Preview</h2>' +
        '<pre>' + escapedYaml + '</pre>' +
        '</div>' +
        '<script>' +
        'function downloadYaml() {' +
        '  const blob = new Blob([' + yamlJson + '], { type: "text/yaml" });' +
        '  const url = URL.createObjectURL(blob);' +
        '  const a = document.createElement("a");' +
        '  a.href = url;' +
        '  a.download = "codecheck.yml";' +
        '  a.click();' +
        '  URL.revokeObjectURL(url);' +
        '}' +
  '<\\/script>' +
  '</body>' +
  '</html>';
      
      win.document.write(html);
    },

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },

    validateForm() {
      if (this.metadata.manifest.length === 0) {
        this.showMessage(this.t('plugins.generic.codecheck.validation.manifestRequired'), 'error');
        return false;
      }
      if (this.metadata.codecheckers.length === 0) {
        this.showMessage(this.t('plugins.generic.codecheck.validation.codecheckersRequired'), 'error');
        return false;
      }
      if (!this.metadata.certificate) {
        this.showMessage(this.t('plugins.generic.codecheck.validation.certificateRequired'), 'error');
        return false;
      }
      if (!this.metadata.summary) {
        this.showMessage(this.t('plugins.generic.codecheck.validation.summaryRequired'), 'error');
        return false;
      }
      return true;
    },

    showMessage(message, type) {
      this.saveMessage = message;
      this.saveMessageType = type;
      setTimeout(() => {
        this.saveMessage = '';
      }, 5000);
    },

    formatDateTimeLocal(timestamp) {
      const date = new Date(timestamp);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
    }
  }
}
</script>

<style>
.codecheck-metadata-form {
  background: #fff;
}

.codecheck-metadata-form .loading-state {
  text-align: center;
  padding: 3rem;
}

.codecheck-metadata-form .header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.codecheck-metadata-form .version-selector {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.codecheck-metadata-form .version-label {
  font-size: 13px;
  color: #666;
}

.codecheck-metadata-form .version-select {
  padding: 0.25rem 0.5rem;
  border: 1px solid #ccc;
  border-radius: 3px;
  font-size: 13px;
}

.codecheck-metadata-form .publication-section {
  padding: 1rem 1.5rem;
}

.codecheck-metadata-form .form-section.read-only-section {
    border: 2px solid #ccc;
}

.codecheck-metadata-form .radio-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.codecheck-metadata-form .radio-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 14px;
}

.codecheck-metadata-form .radio-label {
  color: #333;
}

.codecheck-metadata-form .identifier-section {
  background: #fff;
  padding: 1rem 1.5rem 1rem 0;
}

.codecheck-metadata-form .identifier-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 1rem;
}

.codecheck-metadata-form .codecheck-header {
  padding: 0.5rem 1.5rem 1.5rem 0rem;
  border-bottom: 2px solid #ccc;
}

.codecheck-metadata-form .workflow-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #007ab2;
  text-transform: uppercase;
}

.codecheck-metadata-form .form-section {
  padding: 2rem 1.5rem;
  border-bottom: 1px solid #e5e5e5;
}

.codecheck-metadata-form .form-section.form-details {
    border: 2px solid #ccc;
    margin: 1.5rem 0;
}

.codecheck-metadata-form .section-title {
  margin: 0 0 0.5rem 0;
  font-size: 18px;
  font-weight: 600;
  color: #333;
}

.codecheck-metadata-form .section-description {
  margin: 0 0 1.5rem 0;
  font-size: 14px;
  color: #666;
  font-style: italic;
}

.codecheck-metadata-form .info-grid {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.codecheck-metadata-form .info-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.codecheck-metadata-form .info-label {
  font-weight: 600;
  font-size: 14px;
  color: #555;
}

.codecheck-metadata-form .info-value {
  font-size: 14px;
  color: #333;
  padding: 0.5rem 0.75rem;
  background: white;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.codecheck-metadata-form .info-value a {
  color: #007ab2;
  text-decoration: none;
}

.codecheck-metadata-form .info-value a:hover {
  text-decoration: underline;
}

.codecheck-metadata-form .author-item {
  border-bottom: 1px solid #eee;
}

.codecheck-metadata-form .author-item:last-child {
  border-bottom: none;
}

.codecheck-metadata-form .orcid-badge {
  margin-left: 0.5rem;
  padding: 0.25rem 0.5rem;
  background: #a6ce39;
  color: white;
  font-size: 11px;
  border-radius: 3px;
  font-weight: 600;
}

.codecheck-metadata-form .manifest-preview {
  margin: 0;
  white-space: pre-wrap;
  font-family: monospace;
  font-size: 13px;
  line-height: 1.6;
}

.codecheck-metadata-form .field-group {
  margin-bottom: 2rem;
}

.codecheck-metadata-form .form-details .field-group {
    border: 2px solid #ccc;
    padding: 1.5rem 1.5rem;
}

.codecheck-metadata-form .field-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.codecheck-metadata-form .field-label {
  font-size: 15px;
  font-weight: 600;
  color: #333;
  display: block;
  margin-bottom: 0.5rem;
}

.codecheck-metadata-form .field-description {
  margin: 0 0 0.75rem 0;
  font-size: 13px;
  color: #666;
}

.codecheck-metadata-form .required {
  color: #d9534f;
}

.codecheck-metadata-form .full-width {
  width: 100%;
  box-sizing: border-box;
}

.codecheck-metadata-form .pkpFormField__input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #ccc;
  border-radius: 3px;
  font-size: 14px;
  box-sizing: border-box;
  width: 100%;
}

.codecheck-metadata-form .pkpFormField__input:focus {
  outline: none;
  border-color: #007ab2;
  box-shadow: 0 0 0 2px rgba(0, 122, 178, 0.2);
}

.codecheck-metadata-form .pkpFormField__input--textarea {
  min-height: 100px;
  resize: vertical;
  font-family: inherit;
  line-height: 1.6;
}

.codecheck-metadata-form .pkpTable {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

.codecheck-metadata-form .pkpTable th,
.codecheck-metadata-form .pkpTable td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #dee2e6;
}

.codecheck-metadata-form .pkpTable th {
  background: #f8f9fa;
  font-weight: 600;
  font-size: 14px;
}

.codecheck-metadata-form .manifest-row {
  border-bottom: 1px solid #dee2e6;
}

.codecheck-metadata-form .file-checkbox {
  margin: 0;
}

.codecheck-metadata-form .file-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.codecheck-metadata-form .file-name {
  font-weight: 600;
  font-size: 14px;
}

.codecheck-metadata-form .file-size {
  font-size: 12px;
  color: #6c757d;
}

.codecheck-metadata-form .items-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.codecheck-metadata-form .codecheckers-list {
  margin-top: 1rem;
}

.codecheck-metadata-form .list-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.2rem 1rem;
  border: 1px solid #dee2e6;
  border-radius: 4px;
}

.codecheck-metadata-form .item-content {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
}

.codecheck-metadata-form .item-name {
  font-size: 13px;
  font-weight: 400;
  margin-bottom: 0.25rem;
}

.codecheck-metadata-form .item-orcid {
  font-size: 13px;
  color: #6c757d;
}

.codecheck-metadata-form .empty-state {
  padding: 2rem;
  text-align: center;
  color: #6c757d;
  font-style: italic;
  background: #f8f9fa;
  border: 2px dashed #dee2e6;
  border-radius: 4px;
}

.codecheck-metadata-form .repository-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.codecheck-metadata-form .repository-item {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.codecheck-metadata-form .repository-item input {
  flex: 1;
}

.codecheck-metadata-form .form-footer {
  padding: 1.5rem;
  border-top: 2px solid #ddd;
}

.codecheck-metadata-form .footer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.codecheck-metadata-form .save-message {
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 4px;
  text-align: center;
  font-weight: 600;
}

.codecheck-metadata-form .save-message.success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.codecheck-metadata-form .save-message.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.codecheck-metadata-form .codecheck-btn {
  display: inline-block;
  padding: .4375rem .75rem;
  border: 1px solid #007ab2;
  border-radius: 3px;
  line-height: 1.25rem;
  background: #007ab2;
  color: white;
  text-decoration: none;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.codecheck-metadata-form .codecheck-btn:hover:not(:disabled) {
  background: #005a87;
  border-color: #005a87;
}

.codecheck-metadata-form .codecheck-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.codecheck-metadata-form .pkpButton--isPrimary {
  background: #007ab2;
  border-color: #007ab2;
}

.codecheck-metadata-form .pkpButton--isWarnable {
  background: #dc3545;
  border-color: #dc3545;
}

.codecheck-metadata-form .pkpButton--isWarnable:hover:not(:disabled) {
  background: #c82333;
  border-color: #c82333;
}
.codecheck-metadata-form .pkpButton--close {
  background: #c8233300;
  border-color: #c8233300;
  font-size: 1.3rem;
  color: #676767;
}
.codecheck-metadata-form .pkpButton--close:hover:not(:disabled) {
  background: #c8233300;
  border-color: #c8233300;
  color: #c82333;
}
.codecheck-metadata-form .error-state {
  padding: 2rem;
  text-align: center;
  color: #721c24;
  background: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
  margin: 2rem;
}

.codecheck-metadata-form .modal-form {
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

.yaml-modal-container {
  max-width: 100%;
  padding: 1rem;
}

.yaml-preview-content {
  background: #f5f7f9;
  color: #2c3e50;
  padding: 20px;
  border-radius: 4px;
  overflow-x: auto;
  max-height: 500px;
  line-height: 1.6;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
  font-size: 13px;
  margin: 0;
  white-space: pre;
  border: 1px solid #e1e4e8;
}

.yaml-modal-actions {
  margin-top: 15px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.yaml-download-btn {
  background: #007ab2;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
}

.yaml-download-btn:hover {
  background: #005a87;
}

.codecheck-metadata-form .btn-remove {
  background: #dc3545;
  color: white;
  border: none;
  font-size: 1.2rem;
  font-weight: 600;
  padding: .3rem .75rem;
  border-radius: 4px;
  line-height: 1.60rem;
  cursor: pointer;
  min-width: 40px;
}

.codecheck-metadata-form .btn-add {
  background: #006798;
  color: white;
  border: none;
  font-size: .875rem;
  font-weight: 600;
  padding: .4375rem .75rem;
  border-radius: 4px;
  line-height: 1.25rem;
  cursor: pointer;
}

.btn-remove:hover {
  background: #c82333;
}

.btn-add:hover {
  background: #005580;
}

.codecheck-metadata-form .file-link {
  color: #007ab2;
  text-decoration: none;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.codecheck-metadata-form .file-link:hover {
  text-decoration: underline;
  color: #005a87;
}
</style>