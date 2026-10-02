<template>
  <div class="codecheck-publication-info border border-light">
    <p v-if="notOptedInReason" class="codecheck-publication-info__not-opted-in p-4">
      {{ t(notOptedInReason) }}
    </p>

    <div v-else class="p-4">
      <div class="codecheck-publication-info__heading">
        <h3 class="text-lg-bold">{{ t('plugins.generic.codecheck.publicationInfo.heading') }}</h3>
        <img
          v-if="badge.url"
          :src="badge.url"
          :alt="badge.text"
          :style="badge.style"
          class="codecheck-publication-info__badge"
        >
        <span
          v-else-if="badge.text"
          class="codecheck-publication-info__badge-text"
          :style="{ color: badge.textColor }"
        >{{ badge.text }}</span>
      </div>

      <p v-if="loading" class="codecheck-publication-info__loading">
        <span class="pkpSpinner"></span> {{ t('common.loading') }}
      </p>
      <p v-else-if="error" class="codecheck-publication-info__error">{{ error }}</p>
      <dl v-else class="codecheck-publication-info__facts">
        <dt>{{ t('plugins.generic.codecheck.publicationInfo.certificate') }}</dt>
        <dd>{{ certificate || t('plugins.generic.codecheck.publicationInfo.certificate.none') }}</dd>
        <dt>{{ t('plugins.generic.codecheck.status') }}</dt>
        <dd>{{ status ? t(status) : '' }}</dd>
        <template v-if="issueUrl">
          <dt>{{ t('plugins.generic.codecheck.publicationInfo.registerIssue') }}</dt>
          <dd><a :href="issueUrl" target="_blank" rel="noopener">{{ issueUrl }}</a></dd>
        </template>
      </dl>

      <h4 class="text-base-bold">{{ t('plugins.generic.codecheck.publicationInfo.recorded.heading') }}</h4>
      <p>{{ t('plugins.generic.codecheck.publicationInfo.recorded') }}</p>

      <section v-if="destinations.length">
        <h4 class="text-base-bold">{{ t('plugins.generic.codecheck.publicationInfo.destinations.heading') }}</h4>
        <ul class="codecheck-publication-info__destinations">
          <li
            v-for="destination in destinations"
            :key="destination.id"
            :data-destination="destination.id"
            :class="destination.enabled ? 'is-enabled' : 'is-disabled'"
          >
            <span class="codecheck-publication-info__state">
              {{ destination.enabled
                ? t('plugins.generic.codecheck.publicationInfo.destination.on')
                : t('plugins.generic.codecheck.publicationInfo.destination.off') }}
            </span>
            <span>{{ t(destination.label) }}</span>
            <a
              v-if="destination.url"
              :href="destination.url"
              target="_blank"
              rel="noopener"
              class="codecheck-publication-info__link"
            >{{ destination.url }}</a>
            <span v-if="destination.sandbox" class="codecheck-publication-info__note">
              {{ t('plugins.generic.codecheck.publicationInfo.destination.orcid.sandbox') }}
            </span>
            <span v-if="destination.id === 'registerIssue' && destination.enabled" class="codecheck-publication-info__note">
              {{ destination.authorNames
                ? t('plugins.generic.codecheck.publicationInfo.destination.registerIssue.authorNames')
                : t('plugins.generic.codecheck.publicationInfo.destination.registerIssue.anonymous') }}
            </span>
          </li>
        </ul>
      </section>

      <details class="codecheck-publication-info__yaml" @toggle="onYamlToggle">
        <summary>{{ t('plugins.generic.codecheck.publicationInfo.yaml') }}</summary>
        <p v-if="yamlLoading"><span class="pkpSpinner"></span> {{ t('common.loading') }}</p>
        <p v-else-if="yamlError" class="codecheck-publication-info__error">{{ yamlError }}</p>
        <p v-else-if="yamlEmpty" class="codecheck-publication-info__yaml-empty">{{ t('plugins.generic.codecheck.publicationInfo.yaml.none') }}</p>
        <pre v-else-if="yaml" class="yaml-preview-content">{{ yaml }}</pre>
      </details>

      <p class="codecheck-publication-info__edit">
        <button type="button" class="pkpButton" @click="openCodecheckTab">
          {{ t('plugins.generic.codecheck.publicationInfo.edit') }}
        </button>
      </p>
    </div>
  </div>
</template>

<script>
import { isWebUrl } from '../isWebUrl.js';
import { notOptedInReason } from '../optIn.js';

const { useLocalize } = pkp.modules.useLocalize;

/** The key of the CODECHECK item that main.js adds to the workflow menu. */
const CODECHECK_MENU_KEY = 'codecheck';

/** Marks a locale key for the extractor without translating it here. */
const tk = (key) => key;

/**
 * Spelled out rather than built from the id: OJS sends the frontend only the
 * keys the build finds written out literally in a call to t or tk.
 */
const DESTINATION_LABELS = {
  registerIssue: tk('plugins.generic.codecheck.publicationInfo.destination.registerIssue'),
  registerCsv: tk('plugins.generic.codecheck.publicationInfo.destination.registerCsv'),
  orcid: tk('plugins.generic.codecheck.publicationInfo.destination.orcid'),
  articlePage: tk('plugins.generic.codecheck.publicationInfo.destination.articlePage'),
  availabilityStatement: tk('plugins.generic.codecheck.publicationInfo.destination.availabilityStatement'),
  issueToc: tk('plugins.generic.codecheck.publicationInfo.destination.issueToc'),
};

export default {
  name: 'CodecheckPublicationInfo',
  props: {
    submission: { type: Object, required: true },
    // codecheckDashboardConfig.publicationInfo: the badge and the destinations
    config: { type: Object, default: () => ({}) },
    codecheckMode: { type: String, default: 'opt-in' },
  },
  setup() {
    const { t } = useLocalize();
    return { t };
  },
  data() {
    return {
      loading: false,
      error: null,
      certificate: null,
      issueUrl: null,
      status: null,
      yaml: null,
      yamlLoading: false,
      yamlError: null,
      yamlEmpty: false,
    };
  },
  computed: {
    /** Only destinations this component can name; an unknown id is left out. */
    destinations() {
      return (this.config.destinations ?? [])
        .filter((destination) => DESTINATION_LABELS[destination.id])
        .map((destination) => ({ ...destination, label: DESTINATION_LABELS[destination.id] }));
    },
    /** Why there is no CODECHECK to describe, or null when there is one. */
    notOptedInReason() {
      return notOptedInReason(this.submission, this.codecheckMode);
    },
    badge() {
      return this.config.badge ?? {};
    },
  },
  mounted() {
    if (!this.notOptedInReason) {
      this.loadData();
    }
  },
  methods: {
    /** A plugin endpoint's answer, or an error already worded for the panel. */
    async getJson(endpoint) {
      const response = await fetch(
        `${pkp.context.apiBaseUrl}codecheck/${endpoint}?submissionId=${this.submission.id}`,
        { headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken } }
      );
      // An error page need not be JSON: a PHP fatal answers HTML.
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) {
        const error = new Error(`${this.t('plugins.generic.codecheck.loadError')}: [HTTP ${response.status}] ${data.error ?? ''}`);
        error.status = response.status;
        throw error;
      }
      return data;
    },
    async loadData() {
      this.loading = true;
      this.error = null;
      try {
        const [metadata, status] = await Promise.all([
          this.getJson('metadata'),
          this.getJson('status'),
        ]);
        this.certificate = metadata.codecheck?.certificate || null;
        // Stored as the metadata save received it, so the scheme is checked
        // here before it becomes an href — Vue does not refuse javascript:.
        const issueUrl = metadata.codecheck?.issue?.url;
        this.issueUrl = isWebUrl(issueUrl) ? issueUrl : null;
        this.status = status.statusRecord?.status ?? null;
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },
    /** Fetched on first opening only: most visits never look at it. */
    async onYamlToggle(event) {
      if (!event.target.open || this.yaml || this.yamlEmpty || this.yamlLoading) {
        return;
      }
      this.yamlLoading = true;
      this.yamlError = null;
      try {
        this.yaml = (await this.getJson('yaml')).yaml;
      } catch (error) {
        // 404 is an opted-in article nobody has recorded anything for yet,
        // which is the ordinary state here rather than a failure.
        if (error.status === 404) {
          this.yamlEmpty = true;
        } else {
          this.yamlError = error.message;
        }
      } finally {
        this.yamlLoading = false;
      }
    },
    openCodecheckTab() {
      pkp.registry._piniaInstance?._s?.get('workflow')?.navigateToMenu(CODECHECK_MENU_KEY);
    },
  },
};
</script>

<style>
.codecheck-publication-info {
  margin-bottom: 1rem;
}

.codecheck-publication-info__heading {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.codecheck-publication-info__facts {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.25rem 1rem;
  margin: 0.5rem 0 1rem;
}

.codecheck-publication-info__facts dt {
  font-weight: 600;
}

.codecheck-publication-info__destinations {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 1rem;
}

.codecheck-publication-info__destinations li {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.25rem 0;
}

.codecheck-publication-info__state {
  display: inline-block;
  min-width: 3rem;
  padding: 0 0.4rem;
  border-radius: 3px;
  font-size: 0.75rem;
  font-weight: 600;
  text-align: center;
}

.codecheck-publication-info__destinations .is-enabled .codecheck-publication-info__state {
  background: #e8f5e8;
  color: #006629;
}

.codecheck-publication-info__destinations .is-disabled {
  color: #666;
}

.codecheck-publication-info__destinations .is-disabled .codecheck-publication-info__state {
  background: #eee;
}

.codecheck-publication-info__note {
  font-size: 0.875rem;
  color: #666;
}

.codecheck-publication-info__yaml pre {
  max-height: 20rem;
  overflow: auto;
}

.codecheck-publication-info__error {
  color: #dc3545;
}
</style>
