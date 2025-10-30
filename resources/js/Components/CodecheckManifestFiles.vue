<template>
  <div class="codecheck-manifest-files">
    <div class="manifest-files-list">
      <div v-for="(file, index) in files" :key="index" class="manifest-file-row">
        <input
          v-model="file.filename"
          type="text"
          placeholder="Filename (e.g., figures/figure1.png)"
          @input="updateValue"
          class="form-control"
        />
        <input
          v-model="file.comment"
          type="text"
          placeholder="Comment (e.g., Main result visualization)"
          @input="updateValue"
          class="form-control"
        />
        <button type="button" @click="removeFile(index)" class="btn-remove">×</button>
      </div>
    </div>
    <button type="button" @click="addFile" class="btn-add">
      + Add File
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  description: { type: String, default: "" },
  value: { type: String, default: "" },
  isRequired: { type: Boolean, default: false }
});

const files = ref([]);

onMounted(() => {
  if (props.value) {
    props.value.split('\n').forEach(line => {
      if (line.trim()) {
        const parts = line.split(' - ');
        files.value.push({ 
          filename: parts[0]?.trim() || '', 
          comment: parts[1]?.trim() || '' 
        });
      }
    });
  }
  if (files.value.length === 0) addFile();
});

function addFile() {
  files.value.push({ filename: '', comment: '' });
}

function removeFile(index) {
  files.value.splice(index, 1);
  if (files.value.length === 0) addFile();
  updateValue();
}

function updateValue() {
  const data = files.value
    .filter(f => f.filename.trim())
    .map(f => {
      const filename = f.filename.trim();
      const comment = f.comment.trim();
      return comment ? `${filename} - ${comment}` : filename;
    })
    .join('\n');
  
  const event = new CustomEvent('update', { detail: data, bubbles: true });
  const vueRoot = document.querySelector(`textarea[name="${props.name}"]`)?.previousElementSibling;
  if (vueRoot) {
    vueRoot.dispatchEvent(event);
  }
}
</script>

<style scoped>
.manifest-file-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
  align-items: center;
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

.btn-add {
  background: #006798;
  color: white;
  border: none;
  font-size: .875rem;
  font-weight: 600;
  padding: .4375rem .75rem;
  border-radius: 4px;
  line-height: 1.25rem;
  cursor: pointer;
  margin-top: 10px;
}

.btn-remove:hover {
  background: #c82333;
}

.btn-add:hover {
  background: #005580;
}
</style>