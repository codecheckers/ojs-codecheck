/**
 * OJS's Pinia stores, as the plugin reaches them from outside OJS's own
 * components: the one place that knows they sit in a private field.
 *
 * `pkp.registry.getPiniaStore()` is *not* the way: it looks the name up in a
 * registry only OJS's own component stores are entered in, and throws for
 * `modal`. A store that is not there (another page, or OJS's markup changed)
 * answers `undefined`, so a caller skips what it would have done.
 *
 * @param {string} name e.g. `workflow`, `modal`
 */
export function piniaStore(name) {
  return pkp.registry._piniaInstance?._s?.get(name);
}

/** The submission workflow's store, which main.js extends with `codecheck`. */
export function workflowStore() {
  return piniaStore('workflow');
}
