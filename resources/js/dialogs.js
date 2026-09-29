import { html, toHtml } from './markup.js';

/**
 * Every dialog the plugin opens goes through here, and nothing else names
 * `pkp.modules.useModal`.
 *
 * Each dialog used to build its own: seven copies of the same two destructuring
 * lines, three different single-button dialogs, and two confirmations with
 * opposite button orders and different labels, so the confirmation in the file
 * manager behaved unlike the one in the editorial form (#179). The messages are
 * built with `html` from `markup.js`, which escapes what goes into them.
 *
 * **The buttons are labelled from OJS's own `common.*` keys**, not the plugin's.
 * PKP ships those translated in every locale it has and the plugin ships only
 * `locale/en`, so a plugin key would read "Yes" beside an OJS dialog reading
 * "Ja". Only a label OJS has no word for stays the plugin's — the status
 * dialog's "Change", where `common.change` does not exist.
 */

/** The two lines every call site used to repeat. */
function openDialog({ message, ...options }) {
  const { useModal } = pkp.modules.useModal;
  return useModal().openDialog({
    ...options,
    ...(message === undefined ? {} : { message: toHtml(message) })
  });
}

function translate() {
  const { useLocalize } = pkp.modules.useLocalize;
  return useLocalize().t;
}

/**
 * Asks a yes/no question, so that every confirmation in the plugin looks and
 * behaves alike — No first, Yes primary.
 *
 * @param {object} options title, question, onConfirm and optional onCancel
 */
export function askForConfirmation({ title, question, onConfirm, onCancel = () => {} }) {
  const t = translate();

  openDialog({
    title,
    message: html`<div class="modal-form"><div class="modal-field"><label class="modal-label">${question}</label></div></div>`,
    actions: [
      {
        label: t('common.no'),
        callback: (close) => {
          close();
          onCancel();
        }
      },
      {
        label: t('common.yes'),
        isPrimary: true,
        callback: (close) => {
          close();
          onConfirm();
        }
      }
    ]
  });
}

/**
 * Shows a message that is only read, with a close button — and, where the
 * caller asks for one, a single further action, as the YAML preview does to
 * download what is on screen.
 *
 * @param {object} options `title`, and either `text` (escaped here) or `body`
 *                         built with `html`; optionally `actionLabel`/`onAction`
 */
export function showInformation({ title, text, body, actionLabel, onAction }) {
  const t = translate();
  const content = body !== undefined
    ? body
    : html`<div class="modal-field"><label class="modal-label">${text}</label></div>`;

  const action = actionLabel === undefined ? [] : [{
    label: actionLabel,
    isPrimary: true,
    callback: (close) => {
      onAction();
      close();
    }
  }];

  openDialog({
    title,
    message: html`<div class="modal-form">${content}</div>`,
    actions: [
      ...action,
      {
        label: t('common.close'),
        callback: (close) => close()
      }
    ]
  });
}

/**
 * Closes the dialog that is open.
 *
 * `useModal()` exposes no closer — OJS closes a dialog through the `close` it
 * hands an action callback, and the dialogs that ask for something have no
 * actions — so this goes to the modal store directly, the way the rest of the
 * plugin reaches the workflow store. `pkp.registry.getPiniaStore()` is *not*
 * the way: it looks the name up in a registry only OJS's own component stores
 * are entered in, and throws for `modal`.
 *
 * The event bus is the fallback, since that is how OJS's legacy code closes a
 * dialog from outside the Vue app.
 */
function closeDialog() {
  try {
    const store = pkp.registry._piniaInstance?._s?.get('modal');
    if (store?.closeDialog) {
      store.closeDialog();
      return;
    }
  } catch (error) {
    console.error('CODECHECK: the modal store could not be reached', error);
  }

  pkp.eventBus?.$emit('close-dialog-vue', {});
}

/**
 * Opens a dialog whose body is a Vue component that asks for something (#180).
 *
 * **It is opened with no `actions`, and the body draws its own buttons.** OJS's
 * dialog is built for a question that is over once a button is pressed: the
 * first click sets an internal flag that disables *every* action for good and
 * puts a spinner beside them, and there is no close X while the dialog has
 * actions. So a dialog that stays open to show why it refused — which is the
 * whole of #180 — would stay open with nothing left to press, and only Escape
 * would get the editor out, discarding what they had typed. Every
 * `bodyComponent` dialog OJS opens itself is one that only displays something,
 * which is why OJS has never met this.
 *
 * The body gets `submitLabel`, `onSubmit` and `onClose` and is expected to use
 * the `dialogForm` mixin, which owns the refuse-and-stay-open rule.
 *
 * @param {object} options bodyComponent, bodyProps, title, submitLabel, onSubmit
 */
export function askForInput({ title, bodyComponent, bodyProps = {}, submitLabel, onSubmit }) {
  openDialog({
    title,
    bodyComponent,
    bodyProps: { ...bodyProps, submitLabel, onSubmit, onClose: closeDialog },
    actions: []
  });
}
