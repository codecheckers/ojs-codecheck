<template>
    <div class="codecheck-status-form border border-light">
        <div class="flex items-center justify-between bg-default p-5">
            <h3 class="text-2xl-bold uppercase text-heading">Status</h3>
            <div class="flex gap-x-2">
                <button
                    class="
                        pkpButton
                        inline-flex
                        relative
                        items-center
                        gap-x-1 
                        text-lg-semibold
                        text-primary
                        border-light 
                        hover:text-hover
                        disabled:text-disabled 
                        bg-secondary
                        py-[0.4375rem]
                        px-3
                        border
                        rounded
                    "
                    type="button"
                    href="false"
                    @click="showHistoryModal"
                >
                    {{ t('plugins.generic.codecheck.status.buttons.history') }}
                </button>
                <button
                    v-if="userAllowedToAccess"
                    class="
                        pkpButton
                        pkpButton--isPrimary
                        codecheck-btn
                    "
                    type="button"
                    href="false"
                    @click="showStatusModal"
                >
                    {{ t('plugins.generic.codecheck.status.buttons.change') }}
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
                        {{ getStatusText() }}
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
      statusData: [],
      allStatuses: [],
      userAllowedToAccess: false,
    }
  },
  computed: {
    codecheckMetadataLastSavedAt() {
        const pinia = pkp.registry._piniaInstance;
        const workflowStore = pinia?._s?.get('workflow');

        return workflowStore?.codecheck?.statusUpdateEvent ?? null;
    }
  },
  mounted() {
    this.validateUserAccessRights();
    this.loadStatusData();
  },
  watch: {
    async codecheckMetadataLastSavedAt(newMetadataSaved) {
        if (newMetadataSaved !== null) {
            await this.automaticStatusUpdate();
        }
    }
  },
  methods: {
    async validateUserAccessRights() {
        try {
            if (!this.submission?.id) return;

            const submissionId = this.submission.id;
            const apiUrl = `${pkp.context.apiBaseUrl}codecheck/users/roles/validation`;
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Csrf-Token': pkp.currentUser.csrfToken,
                },
                body: JSON.stringify({ user: pkp.currentUser }),
            });

            const data = await response.json();

            this.userAllowedToAccess = data.userAllowedToAccess;
        } catch (error) {
            console.error('User Role Validation error:', error);
        }
    },
    async loadStatusData() {
        console.log('Loading the status Data', this.submission.id);
        try {
            if (!this.submission?.id) return;

            const submissionId = this.submission.id;
            const apiUrl = `${pkp.context.apiBaseUrl}codecheck/status?submissionId=${submissionId}`;
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
            });

            const data = await response.json();
            console.log("Status data", data);
            this.statusData = data.statusRecord;
            this.allStatuses = data.allStatuses;

            this.dataLoaded = true;
        } catch (error) {
            console.error('getStatus error:', error);
        } finally {
            this.loading = false;
        }
    },
    getStatusText() {
        return this.t(this.statusData.status);
    },
    getStatusSelect() {
        let statusSelectString = '<select id="codecheck-status-select">';
        this.allStatuses.forEach(element => {
            if(element === this.statusData.status) {
                statusSelectString += '<option value="' + element + '" selected>' + this.t(element) + '</option>';
            } else {
                statusSelectString += '<option value="' + element + '">' + this.t(element) + '</option>';
            }
        });
        statusSelectString += '</select>';
        return statusSelectString;
    },
    async showStatusModal() {
      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      const modalHtml = '<div class="modal-form">' +
        '<div class="modal-field">' +
        '<label for="checker-name" class="modal-label">' + this.t('plugins.generic.codecheck.status.modal.label') + '</label>' +
        this.getStatusSelect() +
        '</div>';

      openDialog({
        title: this.t('plugins.generic.codecheck.status.modal.title'),
        message: modalHtml,
        actions: [
          {
            label: this.t('plugins.generic.codecheck.modal.cancel'),
            callback: (close) => close()
          },
          {
            label: this.t('plugins.generic.codecheck.modal.change'),
            isPrimary: true,
            callback: async (close) => {
              const statusSelect = document.getElementById('codecheck-status-select');
              await this.updateStatus(statusSelect.value, pkp.currentUser);
              close();
            }
          }
        ]
      });
    },
    async getStatusHistory() {
        try {
            if (!this.submission?.id) return;

            const submissionId = this.submission.id;
            const apiUrl = `${pkp.context.apiBaseUrl}codecheck/status/history?submissionId=${submissionId}`;
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
            });

            const data = await response.json();
            const statusHistory = data.statusHistory;

            return statusHistory;
        } catch (error) {
            console.error('getStatus error:', error);
        }
    },
    async getStatusHistoryTableRows(statusHistory, mostRecentStatus) {
        let statusHistoryRows = "";
        for (const element of statusHistory) {
            const user = await this.getUser(element.user_id);
            statusHistoryRows += `
                <tr class="border-separate border ${mostRecentStatus ? "padding-mostRecentStatus" : "border-light"} even:bg-tertiary">
                    <td scope="false" class="border-b ${mostRecentStatus ? "border-mostRecentStatus-vertical border-mostRecentStatus-left" : "border-light first:border-s last:border-e"} px-2 py-2 text-start text-base-normal first:ps-3 last:pe-3">
                        <div class="flex items-center">
                            <span class="text-base-normal text-default">${mostRecentStatus ? "<span style='font-weight:bold'>Current Status</span><br>" : ""}${element.timestamp}</span>
                        </div>
                    </td>
                    <td scope="false" class="border-b ${mostRecentStatus ? "border-mostRecentStatus-vertical" : "border-light first:border-s last:border-e"} px-2 py-2 text-start text-base-normal first:ps-3 last:pe-3 whitespace-nowrap">
                        <span class="pkpBadge ${mostRecentStatus ? "pkpBadge--isPrimary" : "codecheckBadge--isInvisible"}">
                            <div class="flex items-center justify-center">${this.t(element.status)}</div>
                        </span>
                    </td>
                    <td scope="false" class="border-b ${mostRecentStatus ? "border-mostRecentStatus-vertical border-mostRecentStatus-right" : "border-light first:border-s last:border-e"} px-2 py-2 text-start text-base-normal first:ps-3 last:pe-3 whitespace-nowrap">
                        <span class="text-base-normal text-default">${user.email === null ? user.fullName : "<a href='mailto:" + user.email + "'>" + user.fullName + "</a>"}</span>
                    </td>
                </tr>
            `;
        };
        return statusHistoryRows;
    },
    async statusTableSegment(statusHistory, tableTop) {
        let table = `<div class="modal-field ${tableTop ? "" : "status-table-wrapper"}">` +
        '<table class="w-full max-w-full border-collapse border-spacing-0" aria-labelledby="v-25" aria-describedby="v-26">';
        
        if(tableTop) {
            table += `
                <thead>
                    <tr class="bg bg-default">
                        <th scope="col" class="whitespace-nowrap border-b border-t border-light px-2 py-4 text-start text-base-normal uppercase text-heading first:border-s first:ps-3 last:border-e last:pe-3">
                            <span>${this.t('plugins.generic.codecheck.status.history.timestamp')}</span>
                        </th>
                        <th scope="col" class="whitespace-nowrap border-b border-t border-light px-2 py-4 text-start text-base-normal uppercase text-heading first:border-s first:ps-3 last:border-e last:pe-3">
                            <span>${this.t('plugins.generic.codecheck.status')}</span>
                        </th>
                        <th scope="col" class="whitespace-nowrap border-b border-t border-light px-2 py-4 text-start text-base-normal uppercase text-heading first:border-s first:ps-3 last:border-e last:pe-3">
                            <span>${this.t('plugins.generic.codecheck.status.history.user')}</span>
                        </th>
                    </tr>
                </thead>
            `;
        }
        
        table += `
            <tbody>
                ${await this.getStatusHistoryTableRows(statusHistory, tableTop)}
            </tbody>
        </table>`
        
        return table;
    },
    async buildStatusHistoryTable() {
        const statusHistory = await this.getStatusHistory();
        const [currentStatus, ...statusRest] = statusHistory;
        return await this.statusTableSegment([currentStatus], true) + await this.statusTableSegment(statusRest, false);
    },
    async showHistoryModal() {
      const { useModal } = pkp.modules.useModal;
      const { openDialog } = useModal();

      const modalHtml = '<div class="modal-form">' +
        await this.buildStatusHistoryTable() +
        '</div>';

      openDialog({
        title: this.t('plugins.generic.codecheck.status.history'),
        message: modalHtml,
        actions: [
          {
            label: this.t('plugins.generic.codecheck.modal.cancel'),
            callback: (close) => close()
          },
        ]
      });
    },
    async automaticStatusUpdate() {
        const status = "";
        const user = {id: -1};

        await this.updateStatus(status, user);
    },
    async updateStatus(status, user) {
        if(this.userAllowedToAccess || user.id === -1) {
            try {
                if (!this.submission?.id) return;
                const submissionId = this.submission.id;
                let apiUrl = pkp.context.apiBaseUrl + 'codecheck';
                const response = await fetch(`${apiUrl}/status/update?submissionId=${submissionId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Csrf-Token': pkp.currentUser.csrfToken,
                    },
                    body: JSON.stringify({ status: status, userId: user.id }),
                });
                const data = await response.json();

                if (data.success) {
                    console.log('Success:', data.statusRecord);
                    this.statusData = data.statusRecord;
                    this.allStatuses = data.allStatuses;
                } else {
                    console.error('Error:', data.error);
                }
            } catch (error) {
                console.error('Failed to update Status: ', error);
            }
        }
    },
    async getUser(userId) {
        // if the user is the Plugin itsself
        if(userId === -1) {
            return {
                fullName: "CODECHECK Plugin",
                email: null
            }
        }
        try {
            const response = await fetch(`${pkp.context.apiBaseUrl}users/${userId}`, {
                method: 'GET',
                headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
            });
            const user = await response.json();
            return user;
        } catch (error) {
            console.error(`User with ID: ${userId} not found. `, error);
        }
    }
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

.codecheckBadge--isInvisible {
    border-color: transparent;
    color: inherit;
    background-color: transparent;
    padding: 0 !important;
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

#codecheck-status-select {
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