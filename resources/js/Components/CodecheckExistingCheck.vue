<template>
  <div class="codecheck-existing-check-field">
    <div class="existing-check-row">
      <input
        id="existingCodecheck"
        v-model="address"
        type="text"
        class="form-control"
        placeholder="https://doi.org/10.5281/zenodo.1234567"
        @input="onInput(address.trim())"
      />
      <button
        type="button"
        class="btn-add existing-check-load"
        :disabled="loading || address.trim() === ''"
        @click="load"
      >
        {{ t('plugins.generic.codecheck.repositories.loadMetadata') }}
      </button>
    </div>
    <p v-if="message" class="existing-check-message" role="status">{{ message }}</p>
    <p v-if="warning" class="pkpFormField__error existing-check-warning" role="alert">{{ warning }}</p>
  </div>
</template>

<script setup>
/**
 * The wizard's pointer to a check of the paper done elsewhere (#190).
 *
 * The address is the author's to keep: `onInput` hands it to the wizard, which
 * saves it on the submission for the editor's full import. Loading reads the
 * check's `codecheck.yml` through the server, which writes nothing, and hands
 * its repositories and expected outputs to `onLoaded`, which adds them to the
 * fields below. A different paper title is a warning, not a refusal: the
 * title often changes between a checked preprint and the submission.
 */
import { ref } from "vue";
import { serverMessage } from "../serverMessage.js";

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  submissionId: { type: [String, Number], required: true },
  value: { type: String, default: "" },
  /** Called with the trimmed address on every change. */
  onInput: { type: Function, required: true },
  /** Called with `{repositories, manifest}`; answers how many entries it added. */
  onLoaded: { type: Function, required: true },
});

const address = ref(props.value);
const loading = ref(false);
const message = ref(null);
const warning = ref(null);

async function load() {
  loading.value = true;
  message.value = null;
  warning.value = null;

  try {
    const response = await fetch(
      `${pkp.context.apiBaseUrl}codecheck/repository/preview?submissionId=${props.submissionId}`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Csrf-Token': pkp.currentUser.csrfToken,
        },
        body: JSON.stringify({ address: address.value.trim() }),
      }
    );
    // An error page need not be JSON: a PHP fatal answers HTML.
    const data = await response.json().catch(() => ({}));

    if (!response.ok || data.success === false) {
      warning.value = t('plugins.generic.codecheck.existingCheck.loadError', {
        error: serverMessage(data, `HTTP ${response.status}`),
      });
      return;
    }

    const entries = {
      repositories: Array.isArray(data.repositories) ? data.repositories : [],
      manifest: Array.isArray(data.manifest) ? data.manifest : [],
    };
    const added = props.onLoaded(entries);
    if (added > 0) {
      message.value = t('plugins.generic.codecheck.existingCheck.loaded');
    } else if (entries.repositories.length + entries.manifest.length > 0) {
      message.value = t('plugins.generic.codecheck.existingCheck.alreadyAdded');
    } else {
      message.value = t('plugins.generic.codecheck.existingCheck.nothingFound');
    }

    if (data.titleMatches === false) {
      warning.value = t('plugins.generic.codecheck.existingCheck.titleMismatch');
    }
  } catch (error) {
    console.error('CODECHECK: Failed to load the existing check', error);
    warning.value = t('plugins.generic.codecheck.existingCheck.loadError', { error: error.message });
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.existing-check-row {
  display: flex;
  gap: 10px;
  align-items: center;
}

.form-control {
  flex: 1;
  padding: .4375rem .75rem;
  line-height: 1.25rem;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #007ab2;
  box-shadow: 0 0 0 2px rgba(0, 122, 178, 0.2);
}

.btn-add {
  background: #006798;
  color: white;
  border: none;
  font-size: .875rem;
  font-weight: 600;
  padding: .4375rem .75rem;
  border-radius: 4px;
  cursor: pointer;
}

.btn-add:disabled {
  opacity: .6;
  cursor: default;
}

.existing-check-message {
  margin: .5rem 0 0;
  font-size: 14px;
}
</style>
