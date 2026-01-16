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
    <div class="vertical-spacer"></div>
    <button type="button" @click="clearText" class="btn-remove">×</button>
  </div>
</template>

<script>
const { useLocalize } = pkp.modules.useLocalize;

export default {
  props: {
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
      // update local bound value
      this.dataSoftwareAvail = val;
      this.$emit("input", val);
      this.$el.dispatchEvent(new CustomEvent('input', { detail: val }));

      // resize the textarea if the textarea value is empty and the placeholder appears again
      this.resizeTextarea();
    },

    clearText() {
      this.dataSoftwareAvail = '';
      this.$emit("input", "");
      this.$el.dispatchEvent(new CustomEvent('input', { detail: "" }));
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
.vertical-spacer {
  height: 100%;
  width: 0.75rem;
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

.codecheck-data-and-software-availability {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  justify-content: space-between;
}

.codecheck-data-and-software-availability textarea {
  width: 90%;
  resize: none;
  overflow: scroll;
}

.btn-remove {
  background: #dc3545;
  color: white;
  border: none;
  font-size: 1.2rem;
  font-weight: 600;
  padding: .3rem .75rem;
  border-radius: 4px;
  line-height: 1.60rem;
  cursor: pointer;
  min-width: 40px;
}

.btn-remove:hover {
  background: #c82333;
}
</style>