<template>
    <div class="codecheck-status-form border border-light">
        <div class="flex items-center justify-between bg-default p-5">
            <h3 class="text-2xl-bold uppercase text-heading">{{ t('plugins.generic.codecheck.github.issue.display') }}</h3>
            <div class="flex gap-x-2">
                <button
                    v-if="isConnected"
                    class="
                        pkpButton
                        pkpButton--isPrimary
                        codecheck-btn
                    "
                    type="button"
                    href="false"
                    @click="viewIssue"
                >
                    {{ t('plugins.generic.codecheck.github.issue.display.button.viewIssue') }}
                </button>
            </div>
        </div>
        <div v-if="loading" class="flex flex-col justify-center items-center loading-state">
            <span class="pkpSpinner"></span>
            <p>{{ t('common.loading') }}</p>
        </div>

        <div v-else-if="error" class="flex flex-col justify-center items-center error-state">
            <p>{{ t('plugins.generic.codecheck.request.failed') }}</p>
            <p>{{ error }}</p>
            <button class="pkpButton codecheck-btn pkpButton--isWarnable" @click="loadStatusData">{{ t('plugins.generic.codecheck.common.reload') }}</button>
        </div>

        <div v-else-if="dataLoaded">
            <div v-if="submission?.codecheckOptIn" class="codecheck-info">
                <div class="border-light border-t p-4">
                    <p class="text-base-normal" :class="statusClass">
                        <ul>
                            <li>
                                <span v-if="isConnected" class="pkpBadge codecheckBadge--isConnected">
                                    <div class="flex items-center justify-center">{{ t('plugins.generic.codecheck.github.issue.display.connected') }}</div>
                                </span>
                                <span v-else class="pkpBadge codecheckBadge--isDisconnected">
                                    <div class="flex items-center justify-center">{{ t('plugins.generic.codecheck.github.issue.display.disconnected') }}</div>
                                </span>
                            </li>
                            <li>
                                <span v-if="certificateIdentifier">
                                    {{ t('plugins.generic.codecheck.github.issue.display.certificateIdentifier', {identifier: certificateIdentifier}) }}
                                </span>
                                <span v-else>
                                    {{ t('plugins.generic.codecheck.github.issue.display.certificateIdentifier', {identifier: t('plugins.generic.codecheck.github.issue.display.certificateIdentifier.notSet')}) }}
                                </span>
                            </li>
                            <li>{{ t('plugins.generic.codecheck.github.issue.display.registerRepository', {repository: repository}) }}</li>
                        </ul>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const { useLocalize } = pkp.modules.useLocalize;

export default {
  name: 'CodecheckStatusForm',
  props: {
    submission: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    name: {type: String},
    value: {type: String},
    certificateIdentifier: {type: String, required: true},
    issue: {type: Object, required: true},
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
      hasUnsavedChanges: false,
      repository: null,
    }
  },
  computed: {
    isConnected() {
        return !!(this.issue?.url);
    },
    codecheckMetadataLastSavedAt() {
        const pinia = pkp.registry._piniaInstance;
        const workflowStore = pinia?._s?.get('workflow');

        return workflowStore?.codecheck?.registerIssueDisplayUpdateEvent ?? null;
    }
  },
  mounted() {
    this.loadData();
  },
  watch: {
    async codecheckMetadataLastSavedAt(newMetadataSaved) {
        if (newMetadataSaved !== null) {
            await this.loadData();
            console.log("Identifier Save button hit: ", this.issue, this.certificateIdentifier);
        }
    }
  },
  methods: {
    async loadData() {
        this.loading = true;
        this.error = null;
        this.dataLoaded = false;

        try {
            if(this.repository === null) {
                if (!this.submission || !this.submission.id) {
                    throw new Error('Invalid submission object');
                }

                const submissionId = this.submission.id;
                let apiUrl = pkp.context.apiBaseUrl;
                apiUrl += 'codecheck';
                apiUrl = `${apiUrl}/register`;
                
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'X-Csrf-Token': pkp.currentUser.csrfToken
                    }
                });

                const data = await response.json();

                console.log("issue repository: ", data);

                if (!response.ok || !data.success) {
                    throw new Error(`[HTTP ${response.status}] ${data.error}`);
                }
                
                if (data && typeof data === 'object') {
                    this.repository = data.url;
                } else {
                    throw new Error(`[HTTP ${response.status}] ${response.message}`);
                }
            }

            this.dataLoaded = true;

            this.$nextTick(() => {
                this.hasUnsavedChanges = false;
            });
        } catch (error) {
            console.error('Load error:', error);
            this.error = this.t('plugins.generic.codecheck.loadError') + ': ' + error.message;
        } finally {
            this.loading = false;
        }

        console.log("Loaded updated github register issue data: ", this.issue, this.repository);
    },
    async viewIssue() {
        if(this.isConnected) {
            window.open(this.issue.url);
        } else {
            await this.showErrorModal(this.t('plugins.generic.codecheck.github.issue.display.errorModal.title'), this.t('plugins.generic.codecheck.github.issue.display.errorModal.message'));
        }
    },
    async showErrorModal(error, message) {
      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      const modalHtml = '<div class="modal-form">' +
        '<div class="modal-field">' +
        '<label for="checker-name" class="modal-label">' + message + '</label>'
        '</div>';

      openDialog({
        title: error,
        message: modalHtml,
        actions: [
          {
            label: this.t('plugins.generic.codecheck.modal.close'),
            callback: (close) => close()
          },
        ]
      });
    },
  }
}
</script>

<style>
.border-mostRecentStatus-vertical {
    border-top: 3px solid #006798 !important;
    border-bottom: 3px solid #006798 !important;
}

.border-mostRecentStatus-left {
    border-left: 3px solid #006798 !important;
}

.border-mostRecentStatus-right {
    border-right: 3px solid #006798 !important;
}

.padding-mostRecentStatus {
    padding-top: 10px;
    padding-bottom: 10px;
}

.codecheckBadge--isConnected {
    border-color: #008033 !important;
    color: #fff !important;
    background-color: #008033 !important;
    margin-bottom: .5rem;
}

.codecheckBadge--isDisconnected {
    border-color: #dc3545 !important;
    color: #fff !important;
    background-color: #dc3545 !important;
    margin-bottom: .5rem;
}

.status-table-wrapper {
    height: 200px;
    overflow: auto;
    margin-top: 1rem;
}

.status-table-wrapper table th {
    position: -webkit-sticky;
    position: sticky;
    top: 0;
}

.modal-field table th:last-of-type,
.modal-field table td:last-of-type {
    text-align: right !important;
}

.codecheck-status-select {
  font-size: 14px;
  padding: 6px;
  border: 1px solid #ccc;
  border-radius: 3px;
  height: 2.5rem;
  background: #fff;
  width: 100%;
}

.loading-state,
.error-state {
  padding: 6px;
}

.codecheck-btn {
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

.codecheck-btn:hover:not(:disabled) {
  background: #005a87;
  border-color: #005a87;
}

.codecheck-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.pkpButton--isPrimary {
  background: #007ab2;
  border-color: #007ab2;
}

.pkpButton--isWarnable {
  background: #dc3545;
  border-color: #dc3545;
}

.pkpButton--isWarnable:hover:not(:disabled) {
  background: #c82333;
  border-color: #c82333;
}

.pkpButton--close {
  background: #c8233300;
  border-color: #c8233300;
  font-size: 1.3rem;
  color: #67676773;
}

.pkpButton--close:hover:not(:disabled) {
  background: #c8233300;
  border-color: #c8233300;
  color: #c82333;
}
</style>