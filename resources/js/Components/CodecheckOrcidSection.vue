<template>
  <div class="codecheck-orcid-section">
    <h4 class="codecheck-orcid-section__title">
      <img
        :src="orcidIconUrl"
        alt="ORCID"
        width="16"
        height="16"
      />
      {{ t('plugins.generic.codecheck.orcid.sectionTitle') }}
    </h4>

    <p class="codecheck-orcid-section__description">
      {{ t('plugins.generic.codecheck.orcid.sectionDescription') }}
    </p>

    <!-- Journal config error warning -->
    <div v-if="journalConfigError" class="codecheck-orcid-section__warning-banner">
      ⚠ {{ journalConfigError }}
    </div>

    <!-- Auth error banner -->
    <div v-if="authError" class="codecheck-orcid-section__error-banner">
      <span>⚠ {{ authError }}</span>
      <button class="codecheck-orcid-section__error-close" @click="authError = null">✕</button>
    </div>

    <div v-if="depositError" class="codecheck-orcid-section__error-banner">
      <span>⚠ {{ depositError }}</span>
      <button class="codecheck-orcid-section__error-close" @click="depositError = null">✕</button>
    </div>

    <div v-if="isLoading">{{ t('common.loading') }}</div>

    <div v-else-if="codecheckers.length === 0">
      {{ t('plugins.generic.codecheck.orcid.noCodecheckers') }}
    </div>

    <div v-else class="codecheck-orcid-section__list">
      <div
        v-for="cc in codecheckers"
        :key="cc.orcidId || cc.name"
        class="codecheck-orcid-row"
      >
        <!-- Identity -->
        <div class="codecheck-orcid-row__identity">
          <strong>{{ cc.name }}</strong>
          <span v-if="cc.orcidId" class="codecheck-orcid-row__orcid-id">
            <img
              :src="orcidIconUrl"
              alt="ORCID iD"
              width="14"
              height="14"
            />
            <a :href="orcidProfileUrl(cc.orcidId)" target="_blank" rel="noopener noreferrer">
              {{ cc.orcidId }}
            </a>
          </span>
        </div>

        <!-- Status badge -->
        <div class="codecheck-orcid-row__status">
          <span class="codecheck-orcid-row__badge" :class="statusClass(cc)">
            {{ statusLabel(cc) }}
          </span>
          <a
            v-if="cc.depositStatus === 'success' && cc.putCode"
            :href="orcidActivityUrl(cc)"
            target="_blank"
            rel="noopener noreferrer"
            class="codecheck-orcid-row__view-link"
          >
            {{ t('plugins.generic.codecheck.orcid.viewOnOrcid') }}
          </a>
        </div>

        <!-- Actions -->
        <div class="codecheck-orcid-row__actions">
          <!-- Auth buttons: only shown to the codechecker, not to editors -->
          <template v-if="canAuthorise">
            <pkp-button v-if="!cc.orcidId" :is-link="true" class="codecheck-orcid-btn" @click="startAuth(cc)">
              {{ t('plugins.generic.codecheck.orcid.authorise') }}
            </pkp-button>
            <pkp-button v-else-if="cc.depositStatus !== 'success'" :is-link="true" class="codecheck-orcid-btn" @click="startAuth(cc)">
              {{ t('plugins.generic.codecheck.orcid.reAuthorise') }}
            </pkp-button>
          </template>
          <span v-else-if="!cc.orcidId" class="codecheck-orcid-row__auth-note">
            {{ t('plugins.generic.codecheck.orcid.authoriseByCodechecker') }}
          </span>

          <!-- Deposit button: only shown to editors who can trigger deposition -->
          <pkp-button
            v-if="cc.orcidId"
            :is-primary="cc.depositStatus !== 'success'"
            :disabled="isDepositing || !!journalConfigError"
            class="codecheck-orcid-btn"
            @click="deposit(cc)"
          >
            {{
              cc.depositStatus === 'success'
                ? t('plugins.generic.codecheck.orcid.reDeposit')
                : t('plugins.generic.codecheck.orcid.deposit')
            }}
          </pkp-button>
        </div>

        <!-- Deposit error message -->
        <div v-if="cc.depositStatus === 'failed' && cc.errorMessage" class="codecheck-orcid-row__error">
          {{ cc.errorMessage }}
        </div>
      </div>
    </div>

    <!-- Deposit all button: only for editors -->
    <div v-if="hasAuthorised && !canAuthorise" class="codecheck-orcid-section__footer">
      <pkp-button
        :disabled="isDepositing || !!journalConfigError"
        class="codecheck-orcid-btn"
        @click="depositAll"
      >
        {{ t('plugins.generic.codecheck.orcid.depositAll') }}
      </pkp-button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CodecheckOrcidSection',

  props: {
    submission:   { type: Object,  required: true },
    orcidEnabled: { type: Boolean, default: false },
    orcidAuthUrl: { type: String,  required: true },
    orcidApiType: { type: String,  default: 'memberSandbox' },
    canAuthorise: { type: Boolean, default: false },
  },

  data() {
    return {
      codecheckers: [],
      isLoading: true,
      isDepositing: false,
      authError: null,
      journalConfigError: null,
      depositError: null,
    };
  },

  computed: {
    hasAuthorised() {
      return this.codecheckers.some((cc) => !!cc.orcidId);
    },
    orcidBaseUrl() {
      return this.orcidApiType === 'memberSandbox'
        ? 'https://sandbox.orcid.org'
        : 'https://orcid.org';
    },
    ojsApiBaseUrl() {
      return window.codecheckOrcidConfig?.apiBaseUrl ?? '';
    },
    orcidIconUrl() {
      const base = this.ojsApiBaseUrl.replace(/\/index\.php.*/, '');
      return base + '/plugins/generic/codecheck/assets/img/orcid.svg';
    },
  },

  mounted() {
    this.loadTokenStatus();
    window.addEventListener('message', this.onOAuthMessage);
  },

  beforeUnmount() {
    window.removeEventListener('message', this.onOAuthMessage);
  },

  methods: {
    t(key) {
      const { useLocalize } = pkp.modules.useLocalize;
      const { t } = useLocalize();
      return t(key);
    },

    async loadTokenStatus() {
      this.isLoading = true;
      try {
        const response = await fetch(
          this.ojsApiBaseUrl + '/api/v1/codecheck/orcid-status?submissionId=' + this.submission.id,
          { headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken } }
        );
        if (response.ok) {
          const data = await response.json();
          this.codecheckers       = data.codecheckers ?? [];
          this.journalConfigError = data.journalConfigError ?? null;
        }
      } catch (err) {
        console.error('[CODECHECK ORCID] Load error', err);
      } finally {
        this.isLoading = false;
      }
    },

    startAuth(cc) {
      this.authError = null;
      const url = this.orcidAuthUrl + '?submissionId=' + this.submission.id;
      const popup = window.open(url, 'orcid_auth', 'width=700,height=600,scrollbars=yes');
      const timer = setInterval(() => {
        if (popup && popup.closed) {
          clearInterval(timer);
          this.loadTokenStatus();
        }
      }, 1000);
    },

    async deposit(cc) {
      this.depositError = null;
      this.isDepositing = true;
      try {
        const response = await fetch(
          this.ojsApiBaseUrl + '/api/v1/codecheck/orcid-deposit',
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Csrf-Token': pkp.currentUser.csrfToken,
            },
            body: JSON.stringify({ submissionId: this.submission.id, orcidId: cc.orcidId }),
          }
        );
        const data = await response.json();
        await this.loadTokenStatus();
        if (data.results && Array.isArray(data.results)) {
          const failed = data.results.filter(r => r.status === 'failed');
          if (failed.length > 0) {
            this.depositError = failed.map(r => r.error).join(', ');
          }
        }
      } catch (err) {
        console.error('[CODECHECK ORCID] Deposit error', err);
      } finally {
        this.isDepositing = false;
      }
    },

    async depositAll() {
      this.isDepositing = true;
      try {
        const response = await fetch(
          this.ojsApiBaseUrl + '/api/v1/codecheck/orcid-deposit',
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Csrf-Token': pkp.currentUser.csrfToken,
            },
            body: JSON.stringify({ submissionId: this.submission.id }),
          }
        );
        await response.json();
        await this.loadTokenStatus();
      } catch (err) {
        console.error('[CODECHECK ORCID] Deposit-all error', err);
      } finally {
        this.isDepositing = false;
      }
    },

    onOAuthMessage(event) {
      if (event.data?.type === 'orcidAuthSuccess') {
        this.authError = null;
        this.loadTokenStatus();
      } else if (event.data?.type === 'orcidAuthError') {
        this.authError = event.data.message;
        this.loadTokenStatus();
      }
    },

    statusClass(cc) {
      if (!cc.orcidId) return 'badge--none';
      return {
        success: 'badge--success',
        failed:  'badge--failed',
        pending: 'badge--pending',
      }[cc.depositStatus] ?? 'badge--pending';
    },

    statusLabel(cc) {
      if (!cc.orcidId) return this.t('plugins.generic.codecheck.orcid.status.notAuthorised');
      return {
        success: this.t('plugins.generic.codecheck.orcid.status.deposited'),
        failed:  this.t('plugins.generic.codecheck.orcid.status.failed'),
        pending: this.t('plugins.generic.codecheck.orcid.status.pending'),
      }[cc.depositStatus] ?? this.t('plugins.generic.codecheck.orcid.status.pending');
    },

    orcidProfileUrl(orcidId) {
      return this.orcidBaseUrl + '/' + orcidId;
    },

    orcidActivityUrl(cc) {
      if (!cc.orcidId || !cc.putCode) return '#';
      return this.orcidBaseUrl + '/' + cc.orcidId + '#peer-review-' + cc.putCode;
    },
  },
};
</script>

<style>
.codecheck-orcid-section {
  margin-top: 1.5rem;
  padding: 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  background: #fafafa;
}
.codecheck-orcid-section__title {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}
.codecheck-orcid-section__title img,
.codecheck-orcid-row__orcid-id img {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}
.codecheck-orcid-section__description {
  color: #555;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}
.codecheck-orcid-section__warning-banner {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffc107;
  border-radius: 4px;
  padding: 0.6rem 0.8rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}
.codecheck-orcid-section__error-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
  padding: 0.6rem 0.8rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}
.codecheck-orcid-section__error-close {
  background: none;
  border: none;
  color: #721c24;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  padding: 0;
  flex-shrink: 0;
}
.codecheck-orcid-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 0.5rem 1rem;
  align-items: center;
  padding: 0.75rem 0;
  border-top: 1px solid #eee;
}
.codecheck-orcid-row__identity { display: flex; flex-direction: column; gap: 0.2rem; }
.codecheck-orcid-row__orcid-id { font-size: 0.8rem; color: #555; display: flex; align-items: center; gap: 0.25rem; }
.codecheck-orcid-row__status { display: flex; align-items: center; gap: 0.5rem; }
.codecheck-orcid-row__badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 3px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}
.badge--success { background: #d4edda; color: #155724; }
.badge--failed  { background: #f8d7da; color: #721c24; }
.badge--pending { background: #fff3cd; color: #856404; }
.badge--none    { background: #e2e3e5; color: #383d41; }
.codecheck-orcid-row__actions { display: flex; gap: 0.5rem; align-items: center; }
.codecheck-orcid-row__auth-note {
  font-size: 0.8rem;
  color: #777;
  font-style: italic;
}
.codecheck-orcid-row__error {
  grid-column: 1 / -1;
  font-size: 0.8rem;
  color: #721c24;
  background: #f8d7da;
  padding: 0.4rem 0.6rem;
  border-radius: 3px;
}
.codecheck-orcid-section__footer { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
.codecheck-orcid-btn,
.codecheck-orcid-btn button,
.codecheck-orcid-row__actions button,
.codecheck-orcid-row__actions .pkpButton {
  cursor: pointer !important;
}
</style>