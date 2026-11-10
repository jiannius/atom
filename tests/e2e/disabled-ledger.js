// The ledger of known failures of disabled-behaviour.spec.js, disabled-morph.spec.js and disabled-controls.spec.js: today's rows that do not keep the disabled-state
// contract (a disabled control writes nothing to its model on any gesture, blocks the keyboard as well as the mouse, and looks disabled; issues #184 to #187).
// Every entry is a row marked `test.fail()` by ledgerRow() in disabled-helpers.js, and is removed by the pull request it names (`pr` is the number in the series
// that fixes the disabled state control by control); until then the row must fail in EXACTLY the way the entry says, so a fix that lands, or half of one, turns
// the row red and the entry has to come out with the fix.
//
// Keys: `<case id>/<route>` (behaviour), `morph:<case id>/<route>`, `noWrite:<case id>/<route>`, `child/first` and `child/update`. `violations` are the names the
// probe reports. `engines.<name>.violations` is the list in an engine that differs from Chromium (empty: it keeps the contract there). A `pr` that is not a
// number is a row that needs a decision first.
export const LEDGER = {
  // ---- PR 3
  "morph:input_tel/form": { pr: 3, why: "WebKit keeps `select:open` true on a native dial code list that was open when a morph disabled the select (it stays so through blur, Escape and a click away; Chromium closes it): the engine's list, not the control's. The select is disabled, which is all the markup can do", violations: [], engines: { webkit: { violations: ["popup stays open"] } } },
  "morph:input_tel/prop": { pr: 3, why: "WebKit keeps `select:open` true on a native dial code list that was open when a morph disabled the select (it stays so through blur, Escape and a click away; Chromium closes it): the engine's list, not the control's. The select is disabled, which is all the markup can do", violations: [], engines: { webkit: { violations: ["popup stays open"] } } },
  // ---- PR 7
  "morph:tiptap/form": { pr: 7, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:tiptap/prop": { pr: 7, why: "an open popup stays open when a morph disables the control; its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render", "popup stays open"] },
  "morph:tiptap_chat/prop": { pr: 7, why: "its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render"] },
  "tiptap/form": { pr: 7, why: "no disabled state: typing, the toolbar and its image input work", violations: ["bold the text: value", "keyboard: chooser", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"], engines: { webkit: { violations: ["bold the text: value", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"] } } },
  "tiptap/prop": { pr: 7, why: "no disabled state: typing, the toolbar and its image input work", violations: ["bold the text: value", "keyboard: chooser", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"], engines: { webkit: { violations: ["bold the text: value", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"] } } },
  "tiptap_chat/form": { pr: 7, why: "no disabled state: typing, Enter and the buttons work", violations: ["keyboard: chooser", "keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"], engines: { webkit: { violations: ["keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"] } } },
  "tiptap_chat/prop": { pr: 7, why: "no disabled state: typing, Enter and the buttons work", violations: ["keyboard: chooser", "keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"], engines: { webkit: { violations: ["keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"] } } },
}

// The look rows of disabled-controls.spec.js, keyed by the pair's test id (the same on both builds, in light and dark).
export const LOOK_LEDGER = {
  "chat": { pr: 7, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "tiptap": { pr: 7, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
}

// The pairs of tokens-disabled-looks.spec.js that can be in LOOK_LEDGER: controls whose disabled copy must look unlike the enabled one, and fields that,
// in a disabled <atom:form>, must look as the same field with its own `disabled` (their key is `form-<name>`). The ledger check holds every key to this list.
export const LOOK_DIFFERS = ['time', 'range', 'filter', 'color', 'rating', 'uploader', 'dropzone', 'tiptap', 'chat']
export const LOOK_FORM_MATCHES = ['input', 'textarea', 'select-native', 'select-listbox']
