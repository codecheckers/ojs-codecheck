// This file mocks the OJS pkp global object for component tests
if (typeof window !== 'undefined') {
  window.pkp = {
    currentUser: {
      csrfToken: 'test-csrf-token'
    },
    context: {
      apiBaseUrl: 'http://localhost:3000/api/v1/'
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