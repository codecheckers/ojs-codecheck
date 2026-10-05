<template>
    <div class="modal-form">
        <div class="modal-field">
            <label :for="nameId" class="modal-label">{{ t('plugins.generic.codecheck.codecheckers.enterName') }}</label>
            <input
                :id="nameId"
                ref="nameField"
                v-model="name"
                type="text"
                class="modal-input"
                :class="{ 'modal-input--invalid': nameError }"
                :placeholder="t('plugins.generic.codecheck.codecheckers.enterName')"
                :aria-invalid="Boolean(nameError)"
                :aria-describedby="nameError ? `${nameId}-error` : null"
                @input="nameError = ''"
            />
            <p v-if="nameError" :id="`${nameId}-error`" class="modal-field-error" role="alert">{{ nameError }}</p>
        </div>
        <div class="modal-field">
            <label :for="orcidId" class="modal-label">{{ t('plugins.generic.codecheck.codecheckers.enterOrcid') }}</label>
            <input
                :id="orcidId"
                v-model="orcid"
                type="text"
                class="modal-input"
                :class="{ 'modal-input--invalid': orcidError }"
                placeholder="0000-0000-0000-0000"
                :aria-invalid="Boolean(orcidError)"
                :aria-describedby="orcidError ? `${orcidId}-error` : null"
                @input="orcidError = ''; githubSuggestion = null"
                @blur="suggestGithubUsername"
            />
            <p v-if="orcidError" :id="`${orcidId}-error`" class="modal-field-error" role="alert">{{ orcidError }}</p>
        </div>
        <div class="modal-field">
            <label :for="githubId" class="modal-label">{{ t('plugins.generic.codecheck.codecheckers.enterGithubUsername') }}</label>
            <input
                :id="githubId"
                v-model="github"
                type="text"
                class="modal-input"
                :class="{ 'modal-input--invalid': githubError }"
                :aria-invalid="Boolean(githubError)"
                :aria-describedby="githubError ? `${githubId}-error` : `${githubId}-hint`"
                @input="githubError = ''"
            />
            <p v-if="githubError" :id="`${githubId}-error`" class="modal-field-error" role="alert">{{ githubError }}</p>
            <p v-else :id="`${githubId}-hint`" class="modal-field-hint">{{ t('plugins.generic.codecheck.codecheckers.githubHint') }}</p>
            <p v-if="githubSuggestion" class="modal-field-hint codecheck-github-suggestion">
                {{ t('plugins.generic.codecheck.codecheckers.githubSuggested.community', { username: githubSuggestion }) }}
                <button type="button" class="pkpButton" @click="useGithubSuggestion">{{ t('plugins.generic.codecheck.codecheckers.githubSuggestion.use') }}</button>
            </p>
        </div>
        <p v-if="error" class="modal-field-error" role="alert">{{ error }}</p>
        <div class="modal-actions">
            <button type="button" class="pkpButton" @click="onClose">
                {{ t('common.cancel') }}
            </button>
            <button type="button" class="pkpButton pkpButton--isPrimary" :disabled="busy" @click="submitDialog">
                {{ submitLabel }}
            </button>
        </div>
    </div>
</template>

<script>
import { dialogForm } from '../dialogForm.js';
import { isValidOrcid, normalizeOrcid } from '../orcid.js';
import { isValidGithubUsername, normalizeGithubUsername } from '../githubUsername.js';
import { lookupGithubUsername } from '../codecheckerLookup.js';

const { useLocalize } = pkp.modules.useLocalize;

let instances = 0;

/**
 * The body of the "add codechecker" dialog.
 *
 * It was markup built as a string and read back through
 * `document.getElementById('checker-name')`, which meant a second copy of the
 * form on the same page read the first copy's fields, an empty name closed the
 * dialog without adding anything, and the ORCID was stored unchecked (#180).
 * The element ids are per instance for the same reason — a fixed id is also a
 * `<label for>` pointing at whichever copy rendered first.
 *
 * The GitHub username is what the register issue is assigned to (#186). An
 * ORCID iD the CODECHECK community list knows is offered a username, which
 * the editor takes with a button.
 */
export default {
  name: 'CodecheckCodecheckerDialog',
  mixins: [dialogForm],
  setup() {
    const { t } = useLocalize();
    return { t };
  },

  data() {
    const id = ++instances;
    return {
      nameId: `codecheck-checker-name-${id}`,
      orcidId: `codecheck-checker-orcid-${id}`,
      githubId: `codecheck-checker-github-${id}`,
      name: '',
      orcid: '',
      github: '',
      nameError: '',
      orcidError: '',
      githubError: '',
      githubSuggestion: null,
      lookedUp: ''
    };
  },
  mounted() {
    this.$refs.nameField?.focus();
  },
  methods: {
    /**
     * Ask who the ORCID iD belongs to on GitHub, and offer the answer beside
     * the field. It is never filled in by itself: the blur that asks is also
     * the one pressing Add causes, so a value filled in then would be added
     * without the editor having seen it.
     */
    async suggestGithubUsername() {
      const orcid = normalizeOrcid(this.orcid);
      if (this.github.trim() !== '' || !isValidOrcid(orcid) || orcid === this.lookedUp) {
        return;
      }
      this.lookedUp = orcid;

      const suggestion = await lookupGithubUsername(orcid);

      if (suggestion && normalizeOrcid(this.orcid) === orcid) {
        this.githubSuggestion = suggestion;
      }
    },

    useGithubSuggestion() {
      this.github = this.githubSuggestion;
      this.githubError = '';
      this.githubSuggestion = null;
    },

    /**
     * @returns {object} `{valid, value}` — the dialog stays open when invalid,
     *                   the message beside the field saying why
     */
    validate() {
      this.nameError = this.name.trim() ? '' : this.t('plugins.generic.codecheck.codecheckers.validation.nameRequired');

      const orcid = normalizeOrcid(this.orcid);
      this.orcidError = orcid === '' || isValidOrcid(orcid)
        ? ''
        : this.t('plugins.generic.codecheck.codecheckers.validation.orcidInvalid');

      const github = normalizeGithubUsername(this.github);
      this.githubError = github === '' || isValidGithubUsername(github)
        ? ''
        : this.t('plugins.generic.codecheck.codecheckers.validation.githubInvalid');

      if (this.nameError || this.orcidError || this.githubError) {
        return { valid: false };
      }

      return { valid: true, value: { name: this.name.trim(), orcid, github } };
    }
  }
};
</script>
