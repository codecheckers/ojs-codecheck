<template>
  <div class="codecheck-repository-list">
    <div class="repository-list">
      <!--
        The message sits with the row it belongs to. It used to be a second
        loop below every input, with `v-if` and `v-for` on one element — which
        Vue 3 evaluates in the other order, so `error` was out of scope, read
        `undefined`, and no message was ever shown (issue #170).
      -->
      <div v-for="(repo, index) in repositories" :key="index" class="repository-row-group">
        <div class="repository-row">
          <input
            v-model="repositories[index]"
            type="url"
            :placeholder="t('plugins.generic.codecheck.repository.placeholder')"
            @input="updateValue"
            @blur="validateUrl(index)"
            :class="['form-control', { 'is-invalid': errors[index] }]"
          />
          <button type="button" @click="removeRepository(index)" class="btn-remove">×</button>
        </div>
        <div v-if="errors[index]" class="pkpFormField__error">{{ errors[index] }}</div>
      </div>
    </div>
    <button type="button" @click="addRepository" class="btn-add">
      {{ t('plugins.generic.codecheck.repository.addButton') }}
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { isWebUrl } from "../isWebUrl.js";

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  description: { type: String, default: "" },
  value: { type: String, default: "" }
});

/** What a freshly added row holds: a scheme to type after, not an address. */
const NEW_ROW_VALUE = 'https://';

const repositories = ref([]);
const errors = ref([]);

onMounted(() => {
  if (props.value) {
    props.value.split('\n').forEach(line => {
      if (line.trim()) {
        repositories.value.push(line.trim());
        errors.value.push('');
      }
    });
  }
  if (repositories.value.length === 0) addRepository();

  // Judge what arrived, so a stored address the rule refuses is flagged rather
  // than sitting there looking accepted. Deliberately without `updateValue()`:
  // that would rewrite the textarea from a list the author has not touched.
  repositories.value.forEach((_, index) => validateUrl(index));
});

function addRepository() {
  repositories.value.push(NEW_ROW_VALUE);
  errors.value.push('');
}

function removeRepository(index) {
  repositories.value.splice(index, 1);
  errors.value.splice(index, 1);
  updateValue();
}

/**
 * A row the author has not filled in yet: neither valid nor an error.
 *
 * The shape rather than the seed literal — `isWebUrl()` is true for a bare
 * `http://` too, so testing only against `NEW_ROW_VALUE` let one keystroke
 * turn the placeholder into a stored address whose link label is empty.
 */
function isEmptyRow(url) {
  return /^(https?:\/\/)?$/i.test(String(url ?? '').trim());
}

/**
 * Whether the server will accept a row: the same rule `Constants::isWebUrl()`
 * applies, so the message here says in advance what the save would answer.
 */
function isSubmittable(url) {
  return !isEmptyRow(url) && isWebUrl(url);
}

/**
 * The rule is `isWebUrl()`, the same one the API applies, so the wizard and the
 * server cannot disagree about a given address (issue #170). `new URL()` still
 * runs, but only to tell "that is not a URL at all" from "that is a URL with
 * the wrong scheme" — it decides nothing.
 */
function validateUrl(index) {
  const url = repositories.value[index];

  if (isSubmittable(url) || isEmptyRow(url)) {
    errors.value[index] = '';
    return;
  }

  try {
    new URL(url);
    errors.value[index] = t('plugins.generic.codecheck.repository.validation.protocol');
  } catch {
    errors.value[index] = t('plugins.generic.codecheck.repository.validation.invalid');
  }
}

/**
 * Only addresses that pass the rule are written into the hidden textarea the
 * wizard submits.
 *
 * Everything the author typed is submitted, invalid rows included, and the
 * server refuses the save with the error keyed to this field — which is how
 * PKP's own form fields work: no field filters its own payload, because
 * `Repository::edit()` treats a submitted list as complete and an absent entry
 * as a deletion. Withholding a row here was indistinguishable from the author
 * removing it, and deleted the address already on file (issue #170).
 *
 * The per-row message is still shown, so the author sees which row is at fault
 * before the server says so.
 */
function updateValue() {
  // Every row is judged on every change, so the message and the submitted value
  // are never out of step. That does mean an author typing `h`, `ht`, `htt` is
  // told the row is not a URL yet — accepted deliberately: the alternative is a
  // row that has quietly stopped being submitted and still looks accepted.
  repositories.value.forEach((_, index) => validateUrl(index));

  const data = repositories.value
    .filter(url => !isEmptyRow(url))
    .map(url => url.trim())
    .join('\n');
    
  const event = new CustomEvent('update', { detail: data, bubbles: true });
  const vueRoot = document.querySelector(`textarea[name="${props.name}"]`)?.previousElementSibling;
  if (vueRoot) {
    vueRoot.dispatchEvent(event);
  }
}
</script>

<style scoped>
.repository-row-group {
  margin-bottom: 10px;
}

.repository-row {
  display: flex;
  gap: 10px;
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