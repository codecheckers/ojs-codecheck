/**
 * The half of a dialog that asks for something which is the same in all of
 * them: the buttons, and what pressing the primary one means (#180).
 *
 * The buttons are the body's own rather than the dialog's, for the reason
 * `askForInput()` sets out — OJS's own are disabled for good by the first
 * click. A component using this mixin implements `validate()`, answering
 * `{valid, value}`, and renders the footer:
 *
 *     <div class="modal-actions">
 *       <button type="button" class="pkpButton" @click="onClose">…cancel…</button>
 *       <button type="button" class="pkpButton pkpButton--isPrimary" :disabled="busy" @click="submitDialog">
 *         {{ submitLabel }}
 *       </button>
 *     </div>
 */
export const dialogForm = {
  props: {
    submitLabel: { type: String, required: true },
    /** Answers a message when it refuses, and nothing when it is done. */
    onSubmit: { type: Function, required: true },
    onClose: { type: Function, required: true }
  },
  data() {
    return {
      busy: false,
      /** What the caller refused the value with; cleared by the next attempt. */
      error: ''
    };
  },
  methods: {
    async submitDialog() {
      this.error = '';

      const answer = this.validate();
      if (!answer.valid) {
        return;
      }

      this.busy = true;
      try {
        const failure = await this.onSubmit(answer.value);
        if (typeof failure === 'string' && failure !== '') {
          this.error = failure;
          return;
        }
        this.onClose();
      } catch (error) {
        // Nothing above catches this, and a dialog that neither closes nor
        // says anything is the worst of the outcomes available here.
        console.error('CODECHECK: the dialog could not finish', error);
        this.error = this.t('plugins.generic.codecheck.dialog.submitFailed');
      } finally {
        this.busy = false;
      }
    }
  }
};
