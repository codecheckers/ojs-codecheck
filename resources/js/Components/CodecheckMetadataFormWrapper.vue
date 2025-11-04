<template>
  <div id="codecheck-metadata-container"></div>
</template>

<script>
import { createApp } from 'vue'
import CodecheckMetadataForm from './CodecheckMetadataForm.vue'

export default {
  name: 'CodecheckMetadataFormWrapper',
  props: {
    submission: { type: Object, required: true },
    canEdit: { type: Boolean, default: true }
  },
  mounted() {
    const container = this.$el
    const app = createApp(CodecheckMetadataForm, {
      submission: this.submission,
      canEdit: this.canEdit
    })
    app.mount(container)
  }
}
</script>