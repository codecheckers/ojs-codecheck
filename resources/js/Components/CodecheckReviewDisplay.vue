<template>
  <div class="codecheck-review-display">
    <h3>{{ t("plugins.generic.codecheck.reviewTitle") }}</h3>
    
    <div v-if="submission?.codecheckOptIn" class="codecheck-info">
      <div class="info-section">
        <h4>{{ t("plugins.generic.codecheck.status") }}</h4>
        <span class="status-badge" :class="statusClass">
          {{ getStatus() }}
        </span>
      </div>
      
      <div class="info-section" v-if="hasMetadata">
        <h4>{{ t("plugins.generic.codecheck.identifier.label") }}</h4>
        <p>{{ metadata.identifier || t("plugins.generic.codecheck.notYetAssigned") }}</p>
      </div>
      
      <div class="info-section" v-if="repositories.length > 0">
        <h4>{{ t("plugins.generic.codecheck.repositories.title") }}</h4>
        <ul>
          <li v-for="(repo, index) in repositories" :key="index">
            <a :href="repo" target="_blank">{{ repo }}</a>
          </li>
        </ul>
      </div>
      
      <div class="info-section" v-if="hasMetadata && metadata.completionTime">
        <h4>{{ t("plugins.generic.codecheck.completionTime.label") }}</h4>
        <p>{{ formatDate(metadata.completionTime) }}</p>
      </div>
      
      <div class="info-section" v-if="hasMetadata && metadata.summary">
        <h4>{{ t("plugins.generic.codecheck.certificate.summary") }}</h4>
        <p>{{ metadata.summary }}</p>
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
</template>

<script setup>
import { computed } from 'vue';

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  submission: { type: Object, required: true }
});

const metadata = computed(() => {
  if (props.submission.codecheckMetadata) {
    if (typeof props.submission.codecheckMetadata === 'string') {
      try {
        return JSON.parse(props.submission.codecheckMetadata);
      } catch (e) {
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

const repositories = computed(() => {
  if (metadata.value.repositories) {
    return metadata.value.repositories;
  }
  
  const repos = [];
  if (props.submission.codeRepository) {
    repos.push(...props.submission.codeRepository.split('\n').filter(r => r.trim()));
  }
  if (props.submission.dataRepository) {
    repos.push(...props.submission.dataRepository.split('\n').filter(r => r.trim()));
  }
  return repos;
});

const statusClass = computed(() => {
  if (metadata.value.identifier && metadata.value.completionTime) {
    return 'status-complete';
  } else if (hasMetadata.value) {
    return 'status-in-progress';
  }
  return 'status-pending';
});

function getStatus() {
  if (metadata.value.identifier && metadata.value.completionTime) {
    return t("plugins.generic.codecheck.status.complete");
  } else if (hasMetadata.value) {
    return t("plugins.generic.codecheck.status.inProgress");
  }
  return t("plugins.generic.codecheck.status.pending");
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString();
}

function viewFullMetadata() {
  const workflowStore = pkp.registry.getPiniaStore("workflow");
  workflowStore.selectedMenuState = {
    primaryMenuItem: 'codecheck'
  };
}
</script>

<style scoped>
.codecheck-review-display {
  padding: var(--spacing-4);
  background: white;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  margin-bottom: var(--spacing-4);
}

.codecheck-review-display h3 {
  margin: 0 0 var(--spacing-4) 0;
  font: var(--font-xl-bold);
  color: var(--text-color-heading);
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