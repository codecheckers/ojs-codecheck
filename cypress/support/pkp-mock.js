// This file mocks the OJS pkp global object for component tests
if (typeof window !== 'undefined') {
  window.pkp = {
    currentUser: {
      csrfToken: 'test-csrf-token'
    },
    modules: {
      useLocalize: {
        useLocalize: () => ({
          t: (key) => key,
          localize: (obj) => obj
        })
      },
      useModal: () => ({
        openDialog: () => {}
      })
    },
    const: {
      WORKFLOW_STAGE_ID_EXTERNAL_REVIEW: 3
    },
    registry: {
      getPiniaStore: () => ({
        selectedMenuState: null
      })
    }
  };
}