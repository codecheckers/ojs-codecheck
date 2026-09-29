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
                @input="orcidError = ''"
            />
            <p v-if="orcidError" :id="`${orcidId}-error`" class="modal-field-error" role="alert">{{ orcidError }}</p>
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
      name: '',
      orcid: '',
      nameError: '',
      orcidError: ''
    };
  },
  mounted() {
    this.$refs.nameField?.focus();
  },
  methods: {
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

      if (this.nameError || this.orcidError) {
        return { valid: false };
      }

      return { valid: true, value: { name: this.name.trim(), orcid } };
    }
  }
};
</script>
