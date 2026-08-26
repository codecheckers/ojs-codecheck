<template>
  <div class="codecheck-data-and-software-availability">
    <textarea
      ref="textarea"
      :value="dataSoftwareAvail"
      @input="onInput"
      :placeholder="t('plugins.generic.codecheck.dataSoftwareAvailability.description')"
      class="form-control"
    >
    </textarea>
  </div>
</template>

<script>
const { useLocalize } = pkp.modules.useLocalize;

export default {
  props: {
    name: { type: String, required: true },
    value: { type: String, required: true }
  },
  setup() {
    const { t } = useLocalize();
    return { t };
  },
  data() {
    return {
      dataSoftwareAvail: "",
    }
  },
  mounted() {
    this.dataSoftwareAvail = this.value;
    // resize the textarea so the the whole placeholder is visible
    this.resizeTextarea();
    // resize the textarea on window resize
    window.addEventListener('resize', this.resizeTextarea);
  },
  methods: {
    onInput(e) {
      const val = e.target.value;
      this.dataSoftwareAvail = val;
      this.$emit("update", val);
      
      const vueRoot = document.querySelector(`textarea[name="dataAvailabilityStatement"]`)?.previousElementSibling;
      if (vueRoot) {
        vueRoot.dispatchEvent(new CustomEvent('update', { detail: val, bubbles: true }));
      }
      
      this.resizeTextarea();
    },

    adjustHeight() {
      const textarea = this.$refs.textarea;
      if (!textarea) return;

      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';
    },

    resizeTextarea() {
      const textarea = this.$refs.textarea;
      if (!textarea) return;

      // Temporarily set value to placeholder to measure
      if (!this.dataSoftwareAvail) {
        this.dataSoftwareAvail = textarea.placeholder;
        this.$nextTick(() => {
          this.adjustHeight();
          this.dataSoftwareAvail = '';
        });
      }
    }
  }
};
</script>

<style>
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

/* Full width like any other optional field; the textarea grows with its
   content rather than scrolling. */
.codecheck-data-and-software-availability textarea {
  width: 100%;
  resize: vertical;
  overflow: hidden;
}
</style>