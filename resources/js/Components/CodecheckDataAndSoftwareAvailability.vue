<template>
  <div class="codecheck-data-and-software-availability">
    <textarea
      :value="dataSoftwareAvail"
      @input="onInput"
      placeholder="Describe how your data and code are available..."
      class="form-control"
    >
    </textarea>
    <div class="vertical-spacer"></div>
    <button type="button" @click="clearText" class="btn-remove">×</button>
  </div>
</template>

<script>
export default {
  props: {
    value: { type: String, required: true }
  },
  data() {
    return {
      dataSoftwareAvail: "",
    }
  },
  mounted() {
    this.dataSoftwareAvail = this.value;
  },
  methods: {
    onInput(e) {
      const val = e.target.value;
      // update local bound value
      this.dataSoftwareAvail = val;
      this.$emit("input", val);
      this.$el.dispatchEvent(new CustomEvent('input', { detail: val }));
    },

    clearText() {
      this.dataSoftwareAvail = '';
      this.$emit("input", "");
      this.$el.dispatchEvent(new CustomEvent('input', { detail: "" }));
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
  height: 100px;
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