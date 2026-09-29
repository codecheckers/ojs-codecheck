import { mount } from '@cypress/vue';
import '../../css/codecheck.css';

Cypress.Commands.add('mount', mount);

// The mocked PKP modal appends to document.body, which mount() does not clear
// between tests — a dialog a test leaves open would otherwise still be found by
// the next one.
beforeEach(() => {
  // Closed rather than detached, so the body component is unmounted the way a
  // real close unmounts it.
  document.querySelectorAll('.pkp-mock-modal').forEach((dialog) => {
    if (dialog.__close) {
      dialog.__close();
    } else {
      dialog.remove();
    }
  });
});