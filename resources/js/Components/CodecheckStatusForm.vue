<template>
    <div class="codecheck-status-form border border-light">
        <div class="flex items-center justify-between bg-default p-5">
            <h3 class="text-2xl-bold uppercase text-heading">{{ t('plugins.generic.codecheck.status') }}</h3>
            <div class="flex gap-x-2">
                <button
                    v-if="hasStatusHistory"
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
                    v-if="canUpdate && hasStatusHistory"
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
            <div v-if="optedIn" class="codecheck-info">
                <div class="border-light border-t p-4">
                    <p class="text-base-normal" :class="statusClass">
                        {{ getStatusText() }}
                    </p>
                    <p v-if="registerWarning" class="codecheck-register-warning" role="status">
                        {{ registerWarning }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { html } from '../markup.js';
import { isOptedIn } from '../optIn.js';
import { askForInput, showInformation } from '../dialogs.js';
import CodecheckStatusDialog from './CodecheckStatusDialog.vue';

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
      // What the register issue did not get from the last recorded status (#186).
      registerWarning: null,
      hasUnsavedChanges: false,
      statusData: [],
      allStatuses: [],
      canUpdate: false,
      hasStatusHistory: false,
    }
  },
  computed: {
    optedIn() {
      return isOptedIn(this.submission);
    },
    codecheckMetadataLastSavedAt() {
        const pinia = pkp.registry._piniaInstance;
        const workflowStore = pinia?._s?.get('workflow');

        return workflowStore?.codecheck?.statusUpdateEvent ?? null;
    }
  },
  mounted() {
    this.getStatusHistory();
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
    async loadStatusData() {
        try {
            if (!this.submission?.id) return;

            const submissionId = this.submission.id;
            const apiUrl = `${pkp.context.apiBaseUrl}codecheck/status?submissionId=${submissionId}`;
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
            });

            const data = await response.json();
            this.statusData = data.statusRecord;
            this.allStatuses = data.allStatuses;
            // The server says whether it would accept a change (#127).
            this.canUpdate = data.canUpdate === true;

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
    async showStatusModal() {
      askForInput({
        title: this.t('plugins.generic.codecheck.status.modal.title'),
        bodyComponent: CodecheckStatusDialog,
        bodyProps: { statuses: this.allStatuses, currentStatus: this.statusData.status },
        submitLabel: this.t('plugins.generic.codecheck.modal.change'),
        // A refused update answers with its reason, which keeps the dialog open
        // and shows it there rather than only in the console (#180).
        onSubmit: (status) => this.updateStatus(status, pkp.currentUser)
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

            if(statusHistory === null) {
                this.hasStatusHistory = false;
            } else {
                this.hasStatusHistory = true;
            }

            return statusHistory;
        } catch (error) {
            this.hasStatusHistory = false;
            console.error('getStatus error:', error);
        }
    },
    /**
     * One row per recorded status. Built with `html`, so a name or an email
     * address someone chose for themselves is text rather than markup — the
     * status history is where that went wrong once (#179).
     */
    async getStatusHistoryTableRows(statusHistory, mostRecentStatus) {
        // One request per distinct user, all at once. It was one request per
        // row, awaited in turn, so a long history opened its own dialog slowly
        // and asked for the same editor over and over.
        const users = await this.getUsers(statusHistory.map((element) => element.user_id));

        const rows = [];
        for (const element of statusHistory) {
            const user = users.get(element.user_id);
            // getUser() answers undefined, or an error body, for a user it cannot read
            const name = user?.fullName ?? element.user_id;
            const userCell = user?.email ? html`<a href="mailto:${user.email}">${name}</a>` : html`${name}`;
            const current = mostRecentStatus
                ? html`<span class="current-status-label">${this.t('plugins.generic.codecheck.status.history.current')}</span><br>`
                : '';

            rows.push(html`
                <tr class="border-separate border ${mostRecentStatus ? 'padding-mostRecentStatus' : 'border-light'} even:bg-tertiary">
                    <td scope="false" class="border-b ${mostRecentStatus ? 'border-mostRecentStatus-vertical border-mostRecentStatus-left' : 'border-light first:border-s last:border-e'} px-2 py-2 text-start text-base-normal first:ps-3 last:pe-3">
                        <div class="flex items-center">
                            <span class="text-base-normal text-default">${current}${element.timestamp}</span>
                        </div>
                    </td>
                    <td scope="false" class="border-b ${mostRecentStatus ? 'border-mostRecentStatus-vertical' : 'border-light first:border-s last:border-e'} px-2 py-2 text-start text-base-normal first:ps-3 last:pe-3 whitespace-nowrap">
                        <span class="pkpBadge ${mostRecentStatus ? 'pkpBadge--isPrimary' : 'codecheckBadge--isInvisible'}">
                            <div class="flex items-center justify-center">${this.t(element.status)}</div>
                        </span>
                    </td>
                    <td scope="false" class="border-b ${mostRecentStatus ? 'border-mostRecentStatus-vertical border-mostRecentStatus-right' : 'border-light first:border-s last:border-e'} px-2 py-2 text-start text-base-normal first:ps-3 last:pe-3 whitespace-nowrap">
                        <span class="text-base-normal text-default">${userCell}</span>
                    </td>
                </tr>
            `);
        }
        return rows;
    },
    async statusTableSegment(statusHistory, tableTop) {
        const head = tableTop ? html`
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
            ` : '';

        return html`
            <div class="modal-field ${tableTop ? '' : 'status-table-wrapper'}">
                <table class="w-full max-w-full border-collapse border-spacing-0">
                    ${head}
                    <tbody>
                        ${await this.getStatusHistoryTableRows(statusHistory, tableTop)}
                    </tbody>
                </table>
            </div>
        `;
    },
    async buildStatusHistoryTable() {
        if (!this.hasStatusHistory) {
            return '';
        }

        const [currentStatus, ...statusRest] = await this.getStatusHistory();
        return html`${await this.statusTableSegment([currentStatus], true)}${await this.statusTableSegment(statusRest, false)}`;
    },
    async showHistoryModal() {
      // The history is read-only, so its one button closes rather than cancels.
      showInformation({
        title: this.t('plugins.generic.codecheck.status.history'),
        body: await this.buildStatusHistoryTable()
      });
    },
    async automaticStatusUpdate() {
        const status = "";
        const user = {id: -1};

        await this.updateStatus(status, user);
    },
    /**
     * Records a status.
     *
     * Every way this can fail answers with a sentence the editor can read, so
     * the dialog that asked can show it — a path that answered nothing would be
     * indistinguishable from a recorded change (#180).
     *
     * @returns {Promise<string|null>} the reason it was refused, or null
     */
    async updateStatus(status, user) {
        // Whatever comes of this one, the last one's warning is not about it.
        this.registerWarning = null;

        if (!this.canUpdate && user.id !== -1) {
            return this.t('plugins.generic.codecheck.status.update.notPermitted');
        }

        if (!this.submission?.id) {
            console.error('CODECHECK: no submission to record a status against');
            return this.t('plugins.generic.codecheck.status.update.failed');
        }

        try {
            const apiUrl = `${pkp.context.apiBaseUrl}codecheck/status/update?submissionId=${this.submission.id}`;
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Csrf-Token': pkp.currentUser.csrfToken,
                },
                body: JSON.stringify({ status: status, userId: user.id }),
            });
            const data = await response.json();

            // The HTTP status is checked as well as the body's: an
            // authorization refusal is PKP's own answer and carries no
            // `success` at all.
            if (!response.ok || !data.success) {
                // `error` is the server's internal English; `errorMessage` is
                // the translated one PKP sends with a refusal. Only the latter
                // is fit to show.
                console.error('CODECHECK: the status was not recorded', data.error ?? data.errorMessage);
                return data.errorMessage || this.t('plugins.generic.codecheck.status.update.failed');
            }

            this.statusData = data.statusRecord;
            this.allStatuses = data.allStatuses;
            // Recorded either way; the register is best-effort, and says so here.
            this.registerWarning = data.registerWarning ?? null;
            return null;
        } catch (error) {
            console.error('CODECHECK: the status could not be recorded', error);
            return this.t('plugins.generic.codecheck.status.update.failed');
        }
    },
    /**
     * The users behind a list of ids, as a Map, asked for once each and in
     * parallel.
     *
     * @param {Array} userIds may repeat
     * @returns {Promise<Map>} id to user, or to undefined for one that could
     *                         not be read
     */
    async getUsers(userIds) {
        const distinct = [...new Set(userIds)];
        const users = await Promise.all(distinct.map((userId) => this.getUser(userId)));

        return new Map(distinct.map((userId, index) => [userId, users[index]]));
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

.current-status-label {
    font-weight: bold;
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

.codecheck-register-warning {
  margin-top: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeeba;
  border-radius: 4px;
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