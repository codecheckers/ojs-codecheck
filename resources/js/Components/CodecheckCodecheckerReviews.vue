<template>
    <div v-if="reviews.length" class="codecheck-codechecker-reviews border-light border-t p-4">
        <p class="text-base-normal">{{ t('plugins.generic.codecheck.closeReview.description') }}</p>
        <ul class="codecheck-codechecker-reviews__list">
            <li v-for="review in reviews" :key="review.reviewAssignmentId" class="codecheck-codechecker-reviews__item">
                <span>{{ t('plugins.generic.codecheck.closeReview.review', { name: review.name, round: review.round }) }}</span>
                <button
                    type="button"
                    class="pkpButton"
                    :disabled="closing !== null"
                    @click="askToClose(review)"
                >
                    {{ t('plugins.generic.codecheck.closeReview.button') }}
                </button>
            </li>
        </ul>
    </div>
</template>

<script>
import { askForConfirmation, showInformation } from '../dialogs.js';
import { closeCodecheckerReview, fetchOpenCodecheckerReviews } from '../codecheckerReviewers.js';

const { useLocalize } = pkp.modules.useLocalize;

/**
 * The codecheckers' reviews that are not submitted yet, each with a button
 * that closes it (#13): from "completed" on, for a codechecker whose only task
 * was the codecheck. The server decides which reviews these are and whether
 * the user may close them; for anyone else the list is empty and nothing is
 * shown.
 */
export default {
  name: 'CodecheckCodecheckerReviews',
  props: {
    submission: { type: Object, required: true },
    /** The recorded status: the list is read again when it changes. */
    status: { type: String, default: null },
    /** Changes when the record is saved, which may link a codechecker. */
    savedAt: { type: [Number, String], default: null }
  },
  setup() {
    const { t } = useLocalize();
    return { t };
  },
  data() {
    return {
      reviews: [],
      closing: null
    };
  },
  watch: {
    status() {
      this.loadReviews();
    },
    savedAt() {
      this.loadReviews();
    }
  },
  mounted() {
    this.loadReviews();
  },
  methods: {
    async loadReviews() {
      try {
        this.reviews = await fetchOpenCodecheckerReviews(this.submission.id);
      } catch (error) {
        // An editor without standing on the submission is refused, which
        // means nothing to show; anything else is logged.
        if (error.status !== 401) {
          console.error('CODECHECK: could not read the codecheckers\' reviews', error);
        }
        this.reviews = [];
      }
    },

    askToClose(review) {
      askForConfirmation({
        title: this.t('plugins.generic.codecheck.closeReview.button'),
        question: this.t('plugins.generic.codecheck.closeReview.confirm', { name: review.name }),
        // OJS cannot reopen a submitted review.
        destructive: true,
        onConfirm: () => this.close(review)
      });
    },

    async close(review) {
      this.closing = review.reviewAssignmentId;
      const answer = await closeCodecheckerReview(this.submission.id, review.reviewAssignmentId);
      this.closing = null;
      if (answer.error) {
        showInformation({ title: this.t('plugins.generic.codecheck.closeReview.button'), text: answer.error });
        await this.loadReviews();
        return;
      }
      this.reviews = Array.isArray(answer.reviews) ? answer.reviews : [];
    }
  }
};
</script>

<style>
.codecheck-codechecker-reviews__list {
  list-style: none;
  margin: 0.5rem 0 0;
  padding: 0;
}
.codecheck-codechecker-reviews__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.25rem 0;
}
</style>
