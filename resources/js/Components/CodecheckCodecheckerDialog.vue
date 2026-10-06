<template>
    <div class="modal-form">
        <p v-if="!reviewers.length" class="modal-field-hint codecheck-no-reviewers">
            {{ t('plugins.generic.codecheck.codecheckers.noReviewers') }}
        </p>
        <div v-else class="modal-field">
            <label :for="reviewerId" class="modal-label">{{ t('plugins.generic.codecheck.codecheckers.chooseReviewer') }}</label>
            <select
                :id="reviewerId"
                ref="reviewerField"
                v-model="pickedUserId"
                class="modal-input"
                :class="{ 'modal-input--invalid': pickError }"
                :aria-invalid="Boolean(pickError)"
                :aria-describedby="pickError ? `${reviewerId}-error` : null"
                @change="pickError = ''; githubError = ''"
            >
                <option value="">{{ t('plugins.generic.codecheck.codecheckers.chooseReviewer.none') }}</option>
                <option v-for="reviewer in reviewers" :key="reviewer.userId" :value="String(reviewer.userId)">
                    {{ reviewer.name }}
                </option>
            </select>
            <p v-if="pickError" :id="`${reviewerId}-error`" class="modal-field-error" role="alert">{{ pickError }}</p>
        </div>

        <dl v-if="picked" class="codecheck-reviewer-details">
            <dt>{{ t('plugins.generic.codecheck.codecheckers.orcid') }}</dt>
            <dd>{{ picked.orcid || t('plugins.generic.codecheck.codecheckers.orcid.none') }}</dd>
            <dt>{{ t('plugins.generic.codecheck.githubUsername.label') }}</dt>
            <dd>
                <template v-if="picked.github">@{{ picked.github }}</template>
                <template v-else>{{ t('plugins.generic.codecheck.codecheckers.github.none') }}</template>
            </dd>
        </dl>
        <p v-if="picked && !picked.github && picked.githubSuggestion" class="modal-field-hint codecheck-github-suggestion">
            {{ t('plugins.generic.codecheck.codecheckers.githubSuggested.community', { username: picked.githubSuggestion }) }}
            <button type="button" class="pkpButton" :disabled="savingGithub" @click="useGithubSuggestion">{{ t('plugins.generic.codecheck.codecheckers.githubSuggestion.use') }}</button>
        </p>
        <p v-if="githubError" class="modal-field-error" role="alert">{{ githubError }}</p>
        <p v-if="picked && picked.doubleAnonymous" class="modal-field-hint codecheck-double-anonymous">
            {{ t('plugins.generic.codecheck.codecheckers.doubleAnonymous') }}
        </p>

        <p v-if="error" class="modal-field-error" role="alert">{{ error }}</p>
        <div class="modal-actions">
            <button type="button" class="pkpButton" @click="onClose">
                {{ t('common.cancel') }}
            </button>
            <button v-if="reviewers.length" type="button" class="pkpButton pkpButton--isPrimary" :disabled="busy" @click="submitDialog">
                {{ submitLabel }}
            </button>
        </div>
    </div>
</template>

<script>
import { dialogForm } from '../dialogForm.js';
import { saveReviewerGithubUsername } from '../codecheckerReviewers.js';

const { useLocalize } = pkp.modules.useLocalize;

let instances = 0;

/**
 * The body of the "add codechecker" dialog (#13).
 *
 * Every codechecker is a reviewer assigned to the submission through "Add
 * Reviewer", so the dialog offers those reviewers who are not on the list yet,
 * and the entry is copied from the chosen account: name, ORCID iD and GitHub
 * username. An account without a username is offered the one the CODECHECK
 * community list has for its ORCID iD; "Use it" saves it to the account, and
 * it is never filled in unasked.
 *
 * The element ids are per instance: a fixed id is a `<label for>` pointing at
 * whichever copy rendered first (#180).
 */
export default {
  name: 'CodecheckCodecheckerDialog',
  mixins: [dialogForm],
  props: {
    submissionId: { type: [Number, String], required: true },
    /** The reviewers assigned to the submission who are not on the list yet. */
    reviewers: { type: Array, required: true }
  },
  setup() {
    const { t } = useLocalize();
    return { t };
  },

  data() {
    return {
      reviewerId: `codecheck-checker-reviewer-${++instances}`,
      pickedUserId: '',
      pickError: '',
      githubError: '',
      savingGithub: false,
      /** Usernames saved to an account while the dialog is open, by user id. */
      savedGithub: {}
    };
  },
  computed: {
    picked() {
      const reviewer = this.reviewers.find((candidate) => String(candidate.userId) === this.pickedUserId);
      return reviewer ? { ...reviewer, github: this.savedGithub[reviewer.userId] ?? reviewer.github } : null;
    }
  },
  mounted() {
    this.$refs.reviewerField?.focus();
  },
  methods: {
    async useGithubSuggestion() {
      const { userId, githubSuggestion } = this.picked;
      this.savingGithub = true;
      this.githubError = '';
      const answer = await saveReviewerGithubUsername(this.submissionId, userId, githubSuggestion);
      this.savingGithub = false;
      if (answer.error) {
        this.githubError = answer.error;
        return;
      }
      this.savedGithub = { ...this.savedGithub, [userId]: answer.github };
    },

    /**
     * @returns {object} `{valid, value}` — the dialog stays open when invalid,
     *                   the message beside the field saying why
     */
    validate() {
      if (!this.picked) {
        this.pickError = this.t('plugins.generic.codecheck.codecheckers.validation.reviewerRequired');
        return { valid: false };
      }

      const { userId, name, orcid, github } = this.picked;
      return { valid: true, value: { userId, name, orcid, github } };
    }
  }
};
</script>

<style>
.codecheck-reviewer-details {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.25rem 1rem;
  margin: 0 0 1rem;
}
.codecheck-reviewer-details dt {
  font-weight: 600;
}
.codecheck-reviewer-details dd {
  margin: 0;
}
</style>
