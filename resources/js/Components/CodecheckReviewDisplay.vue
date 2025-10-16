<template>
  <div class="codecheck-review-display">
    <h3>CODECHECK Information</h3>
    <table class="review-table">
      <tbody>
        <tr>
          <td class="label-col"><strong>Status</strong></td>
          <td>This submission will be codechecked</td>
        </tr>
        <tr v-if="submission.codeRepository">
          <td class="label-col"><strong>Code Repository</strong></td>
          <td>
            <div v-html="formatRepositories(submission.codeRepository)"></div>
          </td>
        </tr>
        <tr v-if="submission.dataRepository">
          <td class="label-col"><strong>Data Repository</strong></td>
          <td>
            <div v-html="formatRepositories(submission.dataRepository)"></div>
          </td>
        </tr>
        <tr v-if="submission.manifestFiles">
          <td class="label-col"><strong>Expected Output Files</strong></td>
          <td>
            <div v-html="formatManifestFiles(submission.manifestFiles)"></div>
          </td>
        </tr>
        <tr v-if="submission.dataAvailabilityStatement">
          <td class="label-col"><strong>Data and Software Availability</strong></td>
          <td>{{ submission.dataAvailabilityStatement }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  submission: { type: Object, required: true }
});

function formatRepositories(repoData) {
  if (!repoData) return '';
  const repos = repoData.split('\n').filter(r => r.trim());
  if (repos.length === 0) return '';
  
  return '<ul style="margin: 0; padding-left: 20px;">' +
    repos.map(repo => `<li><a href="${repo}" target="_blank">${repo}</a></li>`).join('') +
    '</ul>';
}

function formatManifestFiles(manifestData) {
  if (!manifestData) return '';
  const lines = manifestData.split('\n').filter(l => l.trim());
  if (lines.length === 0) return '';
  
  return '<ul style="margin: 0; padding-left: 20px;">' +
    lines.map(line => {
      const parts = line.split(' - ');
      return `<li><strong>${parts[0]}</strong>${parts[1] ? ' - ' + parts[1] : ''}</li>`;
    }).join('') +
    '</ul>';
}
</script>

<style scoped>
.codecheck-review-display {
  padding: 1.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  margin-top: 1rem;
  background: #f9f9f9;
}

.review-table {
  width: 100%;
  border-collapse: collapse;
}

.review-table td {
  padding: 10px;
  border-bottom: 1px solid #eee;
}

.label-col {
  width: 30%;
  vertical-align: top;
}
</style>