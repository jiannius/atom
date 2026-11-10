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
}

// The look rows of disabled-controls.spec.js, keyed by the pair's test id (the same on both builds, in light and dark).
export const LOOK_LEDGER = {
  "form-input": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "form-select-listbox": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "form-select-native": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "form-textarea": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
}

// The pairs of tokens-disabled-looks.spec.js that can be in LOOK_LEDGER: controls whose disabled copy must look unlike the enabled one, and fields that,
// in a disabled <atom:form>, must look as the same field with its own `disabled` (their key is `form-<name>`). The ledger check holds every key to this list.
export const LOOK_DIFFERS = ['time', 'range', 'filter', 'color', 'rating', 'uploader', 'dropzone', 'tiptap', 'chat']
export const LOOK_FORM_MATCHES = ['input', 'textarea', 'select-native', 'select-listbox']
