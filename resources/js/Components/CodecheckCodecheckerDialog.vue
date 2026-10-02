<template>
    <div class="modal-form">
        <div v-if="directory.length" class="modal-field">
            <label :for="directoryId" class="modal-label">{{ t('plugins.generic.codecheck.codecheckers.directory') }}</label>
            <select :id="directoryId" v-model="directoryIndex" class="modal-input" @change="pickFromDirectory">
                <option value="">{{ t('plugins.generic.codecheck.codecheckers.directory.none') }}</option>
                <option v-for="(entry, index) in directory" :key="index" :value="String(index)">
                    {{ describe(entry) }}
                </option>
            </select>
        </div>
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
                <template v-if="githubSuggestion.source === 'directory'">{{ t('plugins.generic.codecheck.codecheckers.githubSuggested.directory', { username: githubSuggestion.github }) }}</template>
                <template v-else>{{ t('plugins.generic.codecheck.codecheckers.githubSuggested.community', { username: githubSuggestion.github }) }}</template>
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
import { fetchCodecheckerDirectory, lookupGithubUsername } from '../codecheckerDirectory.js';

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
 * The GitHub username is what the register issue is assigned to (#186). The
 * journal's directory of codecheckers fills all three fields at once, and an
 * ORCID iD the journal or the CODECHECK community list already knows is
 * offered a username, which the editor takes with a button.
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
      directoryId: `codecheck-checker-directory-${id}`,
      name: '',
      orcid: '',
      github: '',
      nameError: '',
      orcidError: '',
      githubError: '',
      githubSuggestion: null,
      directory: [],
      directoryIndex: '',
      lookedUp: ''
    };
  },
  async mounted() {
    this.$refs.nameField?.focus();
    this.directory = await fetchCodecheckerDirectory();
  },
  methods: {
    describe(entry) {
      return [entry.name, entry.orcid, entry.github ? '@' + entry.github : '']
        .filter(Boolean)
        .join(' · ');
    },

    pickFromDirectory() {
      const entry = this.directoryIndex === '' ? null : this.directory[Number(this.directoryIndex)];
      // "Someone new" empties what an earlier pick filled, or that person's
      // iD and username would be added under the name typed next.
      this.name = entry?.name ?? '';
      this.orcid = entry?.orcid ?? '';
      this.github = entry?.github ?? '';
      this.nameError = this.orcidError = this.githubError = '';
      this.githubSuggestion = null;
    },

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

      // The directory is already here; only the community list needs asking.
      const known = this.directory.find((entry) => entry.orcid === orcid && entry.github);
      const suggestion = known
        ? { github: known.github, source: 'directory' }
        : await lookupGithubUsername(orcid);

      if (suggestion && normalizeOrcid(this.orcid) === orcid) {
        this.githubSuggestion = suggestion;
      }
    },

    useGithubSuggestion() {
      this.github = this.githubSuggestion.github;
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
