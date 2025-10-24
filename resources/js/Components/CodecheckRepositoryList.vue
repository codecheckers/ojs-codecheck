<template>
  <div class="codecheck-repository-list">
    <div class="repository-list">
      <div v-for="(repo, index) in repositories" :key="index" class="repository-row">
        <input
          v-model="repositories[index]"
          type="text"
          placeholder="Repository URL (e.g., https://github.com/username/repo)"
          @input="updateValue"
          class="form-control"
        />
        <button type="button" @click="removeRepository(index)" class="btn-remove">Remove</button>
      </div>
    </div>
    <button type="button" @click="addRepository" class="btn-add">+ Add Repository</button>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  description: { type: String, default: "" },
  value: { type: String, default: "" }
});

const repositories = ref([]);

onMounted(() => {
  if (props.value) {
    props.value.split('\n').forEach(line => {
      if (line.trim()) repositories.value.push(line.trim());
    });
  }
  if (repositories.value.length === 0) addRepository();
});

function addRepository() {
  repositories.value.push('');
}

function removeRepository(index) {
  repositories.value.splice(index, 1);
  updateValue();
}

function updateValue() {
  const data = repositories.value.filter(r => r.trim()).join('\n');
  const event = new CustomEvent('update', { detail: data });
  document.querySelector(`[data-name="${props.name}"]`)?.dispatchEvent(event);
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

.btn-remove {
  background: #dc3545;
  color: white;
  border: none;
  font-size: .875rem;
  font-weight: 600;
  padding: .4375rem .75rem;
  border-radius: 4px;
  line-height: 1.25rem;
  cursor: pointer;
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