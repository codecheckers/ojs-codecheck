<template>
  <div class="codecheck-review-display">
    <h3>{{ t("plugins.generic.codecheck.reviewTitle") }}</h3>

    <div v-if="loading" class="loading-state">
      <span class="pkpSpinner"></span>
      <p>{{ t('common.loading') }}</p>
    </div>
    <div v-else-if="dataLoaded">
      <div v-if="submission?.codecheckOptIn" class="codecheck-info">
        <div class="border border-light p-4">
          <h3 class="mb-2 text-lg-bold text-heading">{{ t("plugins.generic.codecheck.status") }}</h3>
          <p class="text-sm-normal" :class="statusClass">
            {{ getStatusText() }}
          </p>
        </div>

        <div class="info-section" v-if="hasMetadata && metadata.configVersion">
          <h4>{{ t("plugins.generic.codecheck.review.configVersion") }}</h4>
          <p>{{ metadata.configVersion }}</p>
        </div>

        <div class="info-section" v-if="hasMetadata && metadata.publicationType">
          <h4>{{ t("plugins.generic.codecheck.review.publicationType") }}</h4>
          <p>{{ metadata.publicationType === 'doi' 
                ? t("plugins.generic.codecheck.review.publicationType.doi") 
                : t("plugins.generic.codecheck.review.publicationType.separate") }}</p>
        </div>
        
        <div class="info-section" v-if="hasMetadata && metadata.certificate">
          <h4>{{ t("plugins.generic.codecheck.identifier.label") }}</h4>
          <p>{{ metadata.certificate }}</p>
        </div>

        <div class="info-section" v-if="hasMetadata && metadata.manifest && metadata.manifest.length > 0">
          <h4>{{ t("plugins.generic.codecheck.review.manifestFiles") }}</h4>
          <ul>
            <li v-for="(file, index) in metadata.manifest" :key="index">
              <strong>{{ file.file }}</strong>
              <span v-if="file.comment"> - {{ file.comment }}</span>
            </li>
          </ul>
        </div>

        <div class="info-section" v-if="hasMetadata && metadata.codecheckers && metadata.codecheckers.length > 0">
          <h4>{{ t("plugins.generic.codecheck.review.codecheckers") }}</h4>
          <ul>
            <li v-for="(checker, index) in metadata.codecheckers" :key="index">
              {{ checker.name }}
              <span v-if="checker.orcid" class="orcid-badge">{{ checker.orcid }}</span>
            </li>
          </ul>
        </div>
        
        <div class="info-section" v-if="hasMetadata && metadata.repository">
          <h4>{{ t("plugins.generic.codecheck.repositories.title") }}</h4>
          <a :href="metadata.repository" target="_blank">{{ metadata.repository }}</a>
        </div>
        
        <div class="info-section" v-if="hasMetadata && metadata.checkTime">
          <h4>{{ t("plugins.generic.codecheck.completionTime.label") }}</h4>
          <p>{{ formatDate(metadata.checkTime) }}</p>
        </div>
        
        <div class="info-section" v-if="hasMetadata && metadata.summary">
          <h4>{{ t("plugins.generic.codecheck.certificate.summary") }}</h4>
          <p>{{ metadata.summary }}</p>
        </div>

        <div class="info-section" v-if="hasMetadata && metadata.reportUrl">
          <h4>{{ t("plugins.generic.codecheck.review.reportUrl") }}</h4>
          <a :href="metadata.reportUrl" target="_blank">{{ metadata.reportUrl }}</a>
        </div>
        
        <div class="actions">
          <pkp-button @click="viewFullMetadata">
            {{ t("plugins.generic.codecheck.viewFullMetadata") }}
          </pkp-button>
        </div>
      </div>
      
      <div v-else class="codecheck-not-opted">
        <p>{{ t("plugins.generic.codecheck.notOptedIn") }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted} from 'vue';

const { t } = pkp.modules.useLocalize.useLocalize();

const props = defineProps({
  submission: { type: Object, required: true }
});

const status = ref('');
const loading = ref(true);
const dataLoaded = ref(false);

onMounted(async () => {
  try {
    if (!props.submission?.id) {dataLoaded.value = true; return};

    const submissionId = props.submission.id;
    const apiUrl = `${pkp.context.apiBaseUrl}codecheck/status?submissionId=${submissionId}`;
    
    const response = await fetch(apiUrl, {
      method: 'GET',
      headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
    });

    const data = await response.json();
    status.value = data.statusRecord.status;
    
    dataLoaded.value = true;

  } catch (error) {
    console.error('getStatus error:', error);
  } finally {
    loading.value = false;
  }
});

const statusClass = computed(() => 'status-' + status.value);

function getStatusText() {
  return t(status.value);
}

const metadata = computed(() => {
  if (props.submission.codecheckMetadata) {
    if (typeof props.submission.codecheckMetadata === 'string') {
      try {
        return JSON.parse(props.submission.codecheckMetadata);
      } catch (e) {
        console.error('Failed to parse codecheck metadata:', e);
        return {};
      }
    }
    return props.submission.codecheckMetadata;
  }
  return {};
});

const hasMetadata = computed(() => {
  return Object.keys(metadata.value).length > 0;
});

function formatDate(dateString) {
  if (!dateString) return '';
  let date = new Date(dateString);
  return date.toLocaleString();
}

function viewFullMetadata() {
  // Sadly only works by bypassing the API, searching for the 'CODECHECK' Button and then pressing it by script
  const allLinks = document.querySelectorAll('a, button, [role="button"]');
  const codecheckLink = Array.from(allLinks).find(el => 
    el.textContent.trim().includes(t("plugins.generic.codecheck.workflow.label"))
  );
  console.log('codecheck link:', codecheckLink);
  if (codecheckLink) codecheckLink.click();
}
</script>

<style scoped>
.codecheck-review-display {
  padding: 0;
  background: white;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  margin-bottom: var(--spacing-4);
}

.codecheck-info {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.info-section h4 {
  margin: 0 0 var(--spacing-2) 0;
  font: var(--font-base-bold);
  color: var(--text-color-heading);
}

.info-section p {
  margin: 0;
  color: var(--text-color-primary);
}

.info-section ul {
  margin: 0;
  padding-left: var(--spacing-4);
}

.info-section a {
  color: var(--color-primary);
  text-decoration: none;
}

.info-section a:hover {
  text-decoration: underline;
}

.status-badge {
  display: inline-block;
  padding: var(--spacing-1) var(--spacing-3);
  border-radius: 12px;
  font-size: var(--font-sm);
  font-weight: 600;
}

.status-complete {
  background: var(--color-success-light);
  color: var(--color-success);
}

.status-in-progress {
  background: var(--color-warning-light);
  color: var(--color-warning);
}

.status-pending {
  background: var(--color-background-light);
  color: var(--text-color-secondary);
}

.orcid-badge {
  margin-left: 0.5rem;
  padding: 0.25rem 0.5rem;
  background: #a6ce39;
  color: white;
  font-size: 11px;
  border-radius: 3px;
  font-weight: 600;
}

.actions {
  margin-top: var(--spacing-4);
  padding-top: var(--spacing-4);
  border-top: 1px solid var(--color-border);
}

.codecheck-not-opted {
  padding: var(--spacing-3);
  background: var(--color-background-light);
  border-radius: 4px;
  color: var(--text-color-secondary);
}
</style>