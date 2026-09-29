<template>
    <div class="modal-form">
        <div class="modal-field">
            <label :for="selectId" class="modal-label">{{ t('plugins.generic.codecheck.status.modal.label') }}</label>
            <select
                :id="selectId"
                ref="statusField"
                v-model="selected"
                class="modal-input"
                :aria-invalid="Boolean(error)"
                :aria-describedby="error ? `${selectId}-error` : null"
            >
                <option v-for="status in statuses" :key="status" :value="status">{{ t(status) }}</option>
            </select>
        </div>
        <p v-if="error" :id="`${selectId}-error`" class="modal-field-error" role="alert">{{ error }}</p>
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

const { useLocalize } = pkp.modules.useLocalize;

let instances = 0;

/**
 * The body of the "change CODECHECK status" dialog.
 *
 * The `<select>` was assembled option by option as a string and read back
 * through `document.getElementById`, so a refused update had nowhere to be
 * reported (#180) — `error` is that place.
 */
export default {
  name: 'CodecheckStatusDialog',
  mixins: [dialogForm],
  setup() {
    const { t } = useLocalize();
    return { t };
  },

  props: {
    statuses: { type: Array, required: true },
    currentStatus: { type: String, default: '' }
  },
  data() {
    return {
      selectId: `codecheck-status-select-${++instances}`,
      // The list is the editor's choices; a record with no status yet starts on
      // the first of them rather than on nothing selectable.
      selected: this.statuses.includes(this.currentStatus) ? this.currentStatus : (this.statuses[0] ?? '')
    };
  },
  mounted() {
    this.$refs.statusField?.focus();
  },
  methods: {
    validate() {
      if (!this.selected) {
        // Reachable only if the status list never arrived; saying so beats a
        // button that does nothing.
        this.error = this.t('plugins.generic.codecheck.status.update.noStatuses');
        return { valid: false };
      }
      return { valid: true, value: this.selected };
    }
  }
};
</script>
