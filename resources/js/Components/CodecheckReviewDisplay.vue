<template>
  <div class="codecheck-review-display">
    <h3>{{ t("plugins.generic.codecheck.reviewTitle") }}</h3>

    <div v-if="notOptedIn" class="codecheck-not-opted">
      <p>{{ t(notOptedIn) }}</p>
    </div>
    <div v-else-if="loading" class="loading-state">
      <span class="pkpSpinner"></span>
      <p>{{ t('common.loading') }}</p>
    </div>
    <p v-else-if="error" class="codecheck-not-opted">{{ error }}</p>
    <div v-else>
      <div class="codecheck-info">
        <div class="border border-light p-4">
          <h3 class="mb-2 text-lg-bold text-heading">{{ t("plugins.generic.codecheck.status") }}</h3>
          <p class="text-sm-normal">{{ status && t(status) }}</p>
        </div>

        <div class="info-section" v-if="metadata.version">
          <h4>{{ t("plugins.generic.codecheck.review.configVersion") }}</h4>
          <p>{{ metadata.version }}</p>
        </div>

        <div class="info-section" v-if="metadata.publicationType">
          <h4>{{ t("plugins.generic.codecheck.review.publicationType") }}</h4>
          <p>{{ metadata.publicationType === 'doi' 
                ? t("plugins.generic.codecheck.review.publicationType.doi") 
                : t("plugins.generic.codecheck.review.publicationType.separate") }}</p>
        </div>
        
        <div class="info-section" v-if="metadata.certificate">
          <h4>{{ t("plugins.generic.codecheck.identifier.title") }}</h4>
          <p>{{ metadata.certificate }}</p>
        </div>

        <div class="info-section" v-if="metadata.manifest?.length">
          <h4>{{ t("plugins.generic.codecheck.review.manifestFiles") }}</h4>
          <ul>
            <li v-for="(file, index) in metadata.manifest" :key="index">
              <strong>{{ file.file }}</strong>
              <span v-if="file.comment"> - {{ file.comment }}</span>
            </li>
          </ul>
        </div>

        <div class="info-section" v-if="metadata.codecheckers?.length">
          <h4>{{ t("plugins.generic.codecheck.review.codecheckers") }}</h4>
          <ul>
            <li v-for="(checker, index) in metadata.codecheckers" :key="index">
              {{ checker.name }}
              <span v-if="checker.orcid" class="orcid-badge">{{ checker.orcid }}</span>
            </li>
          </ul>
        </div>
        
        <div class="info-section" v-if="repositoryUrls.length > 0">
          <h4>{{ t("plugins.generic.codecheck.repositories.title") }}</h4>
          <ul>
            <li v-for="(url, index) in repositoryUrls" :key="index">
              <a :href="url" target="_blank" rel="noopener">{{ url }}</a>
            </li>
          </ul>
        </div>
        
        <div class="info-section" v-if="metadata.check_time">
          <h4>{{ t("plugins.generic.codecheck.completionTime.label") }}</h4>
          <p>{{ formatDate(metadata.check_time) }}</p>
        </div>
        
        <div class="info-section" v-if="metadata.summary">
          <h4>{{ t("plugins.generic.codecheck.certificate.summary") }}</h4>
          <p>{{ metadata.summary }}</p>
        </div>

        <div class="info-section" v-if="isWebUrl(metadata.report)">
          <h4>{{ t("plugins.generic.codecheck.review.reportUrl") }}</h4>
          <a :href="metadata.report" target="_blank" rel="noopener">{{ metadata.report }}</a>
        </div>
        
        <div class="actions">
          <pkp-button @click="viewFullMetadata">
            {{ t("plugins.generic.codecheck.viewFullMetadata") }}
          </pkp-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted} from 'vue';
import { notOptedInReason } from '../optIn.js';
import { isWebUrl } from '../isWebUrl.js';
import { getCodecheckJson, openCodecheckTab } from '../codecheckApi.js';

const { t } = pkp.modules.useLocalize.useLocalize();

const props = defineProps({
  submission: { type: Object, required: true },
  codecheckMode: { type: String, default: 'opt-in' },
});

// Why the submission takes no part, worded as on every other tab (#34).
const notOptedIn = computed(() => notOptedInReason(props.submission, props.codecheckMode));

const status = ref('');
const metadata = ref({});
const loading = ref(true);
const error = ref(null);

/**
 * The recorded status and the CODECHECK record, from the endpoints the
 * CODECHECK tab reads, so the review stage cannot say something else (#65).
 */
onMounted(async () => {
  if (!props.submission?.id || notOptedIn.value) {
    loading.value = false;
    return;
  }
  try {
    const [statusData, metadataData] = await Promise.all([
      getCodecheckJson('status', props.submission.id),
      getCodecheckJson('metadata', props.submission.id),
    ]);
    status.value = statusData.statusRecord?.status ?? '';
    metadata.value = metadataData.codecheck ?? {};
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
});

const repositoryUrls = computed(() =>
  (metadata.value.repository?.repositories ?? [])
    .map((repository) => repository?.url)
    .filter(isWebUrl)
);

function formatDate(dateString) {
  if (!dateString) return '';
  let date = new Date(dateString);
  return date.toLocaleString();
}

const viewFullMetadata = openCodecheckTab;
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