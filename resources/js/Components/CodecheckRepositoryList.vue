<template>
  <div class="codecheck-repository-list">
    <div class="repository-list">
      <div v-for="(repo, index) in repositories" :key="index" class="repository-row">
        <input
          v-model="repositories[index]"
          type="url"
          placeholder="https://github.com/username/repo"
          @input="updateValue"
          @blur="validateUrl(index)"
          :class="['form-control', { 'is-invalid': errors[index] }]"
        />
        <button type="button" @click="removeRepository(index)" class="btn-remove">×</button>
      </div>
      <div v-for="(error, index) in errors" :key="'error-' + index" class="pkpFormField__error" v-if="error">
        {{ error }}
      </div>
    </div>
    <button type="button" @click="addRepository" class="btn-add">
      + Add URL
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
  value: { type: String, default: "" }
});

const repositories = ref([]);
const errors = ref([]);

onMounted(() => {
  if (props.value) {
    props.value.split('\n').forEach(line => {
      if (line.trim()) repositories.value.push(line.trim());
    });
  }
  if (repositories.value.length === 0) addRepository();
});

function addRepository() {
  repositories.value.push('https://');
  errors.value.push('');
}

function removeRepository(index) {
  repositories.value.splice(index, 1);
  errors.value.splice(index, 1);
  updateValue();
}

function validateUrl(index) {
  const url = repositories.value[index];
  if (!url.trim() || url === 'https://') {
    errors.value[index] = '';
    return;
  }
  
  try {
    new URL(url);
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      errors.value[index] = 'URL must start with http:// or https://';
    } else {
      errors.value[index] = '';
    }
  } catch {
    errors.value[index] = 'Please enter a valid URL';
  }
}

function updateValue() {
  const data = repositories.value
    .filter(r => r.trim() && r !== 'https://')
    .join('\n');
    
  const event = new CustomEvent('update', { detail: data, bubbles: true });
  const vueRoot = document.querySelector(`textarea[name="${props.name}"]`)?.previousElementSibling;
  if (vueRoot) {
    vueRoot.dispatchEvent(event);
  }
}
</script>

<style scoped>
.repository-row {
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

.is-invalid {
  border-color: #d00a0a !important;
}

.pkpFormField__error {
  color: #d00a0a;
  font-size: 0.875rem;
  margin-top: 0.25rem;
  margin-bottom: 0.5rem;
}
</style>