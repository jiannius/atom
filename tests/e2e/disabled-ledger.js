// The ledger of known failures of disabled-behaviour.spec.js, disabled-morph.spec.js and disabled-controls.spec.js: today's rows that do not keep the disabled-state
// contract (a disabled control writes nothing to its model on any gesture, blocks the keyboard as well as the mouse, and looks disabled; issues #184 to #187).
// Every entry is a row marked `test.fail()` by ledgerRow() in disabled-helpers.js, and is removed by the pull request it names (`pr` is the number in the series
// that fixes the disabled state control by control); until then the row must fail in EXACTLY the way the entry says, so a fix that lands, or half of one, turns
// the row red and the entry has to come out with the fix.
//
// Keys: `<case id>/<route>` (behaviour), `morph:<case id>/<route>`, `noWrite:<case id>/<route>`, `child/first` and `child/update`. `violations` are the names the
// probe reports. `engines.<name>.violations` is the list in an engine that differs from Chromium (empty: it keeps the contract there). A `pr` that is not a
// number is a row that needs a decision first. The firefox entries of input_tel follow from a native <select> never being seen open there (see
// ENGINE_GESTURE_SKIPS in disabled-helpers.js); like all of Firefox's, they are inferred from CI's one run and not yet checked against a green one.
export const LEDGER = {
  // ---- PR 2
  "morph:select_filter/form": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_filter/prop": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox/form": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox/prop": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_list/form": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_list/prop": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_multiple/form": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_multiple/prop": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_remote/form": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_remote/prop": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_searchable/form": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:select_listbox_searchable/prop": { pr: 2, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "select_filter/form": { pr: 2, why: "the filter hands off before it reads a disabled form: no disabled state", violations: ["keyboard: dom", "keyboard: popup", "keyboard: value", "open: popup", "pick: dom", "pick: value", "server: value", "tab stops"] },
  "select_filter/prop": { pr: 2, why: "the filter hands off before it reads a disabled form: no disabled state", violations: ["keyboard: dom", "keyboard: popup", "keyboard: value", "open: popup", "pick: dom", "pick: value", "server: value", "tab stops"] },
  "select_listbox/form": { pr: 2, why: "the trigger is a live tab stop and keys change the value (#186)", violations: ["keyboard: dom", "keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox/prop": { pr: 2, why: "the trigger is a live tab stop and keys change the value (#186)", violations: ["keyboard: dom", "keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_add/form": { pr: 2, why: "the trigger is a live tab stop and the add button works (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_add/prop": { pr: 2, why: "the trigger is a live tab stop and the add button works (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_clear/form": { pr: 2, why: "the trigger is a live tab stop; clear and keys change the value (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_clear/prop": { pr: 2, why: "the trigger is a live tab stop; clear and keys change the value (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_list/form": { pr: 2, why: "the trigger is a live tab stop; row remove and keys change the value (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_list/prop": { pr: 2, why: "the trigger is a live tab stop; row remove and keys change the value (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_multiple/form": { pr: 2, why: "the trigger is a live tab stop; chips and keys change the value (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_multiple/prop": { pr: 2, why: "the trigger is a live tab stop; chips and keys change the value (#186)", violations: ["keyboard: popup", "keyboard: value", "server: value", "tab stops"] },
  "select_listbox_remote/form": { pr: 2, why: "the trigger is a live tab stop and opens (#186)", violations: ["keyboard: popup", "tab stops"] },
  "select_listbox_remote/prop": { pr: 2, why: "the trigger is a live tab stop and opens (#186)", violations: ["keyboard: popup", "tab stops"] },
  "select_listbox_searchable/form": { pr: 2, why: "the trigger is a live tab stop and opens (#186)", violations: ["keyboard: popup", "tab stops"] },
  "select_listbox_searchable/prop": { pr: 2, why: "the trigger is a live tab stop and opens (#186)", violations: ["keyboard: popup", "tab stops"] },
  "select_native_multiple/form": { pr: 2, why: "the chip remove is not guarded", violations: ["chip remove: value", "server: value"] },
  "select_native_multiple/prop": { pr: 2, why: "the chip remove is not guarded", violations: ["chip remove: value", "server: value"] },
  // ---- PR 3
  "input_email_multiple/form": { pr: 3, why: "chip remove and clear all are live (#186)", violations: ["add an address: popup", "chip remove: popup", "chip remove: value", "clear all: value", "server: value", "tab stops"] },
  "input_email_multiple/prop": { pr: 3, why: "chip remove and clear all are live (#186)", violations: ["chip remove: popup", "chip remove: value", "clear all: value", "server: value", "tab stops"] },
  "input_email_options/form": { pr: 3, why: "chip remove is live, and Enter adds the first option in a disabled form (#186)", violations: ["add by Enter: popup", "add by Enter: value", "chip remove: popup", "chip remove: value", "keyboard: value", "open: popup", "server: value", "tab stops"] },
  "input_email_options/prop": { pr: 3, why: "chip remove is live, and Enter adds the first option in a disabled form (#186)", violations: ["chip remove: value", "keyboard: value", "server: value", "tab stops"] },
  "input_tel/form": { pr: 3, why: "the dial code select is never disabled (#186)", violations: ["keyboard: popup", "keyboard: value", "open the dial codes: popup", "server: value", "tab stops"], engines: { firefox: { violations: ["keyboard: value", "server: value", "tab stops"] } } },
  "input_tel/prop": { pr: 3, why: "the dial code select is never disabled (#186)", violations: ["keyboard: popup", "keyboard: value", "open the dial codes: popup", "server: value", "tab stops"], engines: { firefox: { violations: ["keyboard: value", "server: value", "tab stops"] } } },
  "morph:input_email_options/form": { pr: 3, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:input_email_options/prop": { pr: 3, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:input_tel/form": { pr: 3, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"], engines: { firefox: { violations: [] } } },
  "morph:input_tel/prop": { pr: 3, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"], engines: { firefox: { violations: [] } } },
  "noWrite:input_tel/form": { pr: 3, why: "a number without a dial code is given one and sent on load", violations: ["server value rewritten", "writes on load"] },
  "noWrite:input_tel/prop": { pr: 3, why: "a number without a dial code is given one and sent on load", violations: ["server value rewritten", "writes on load"] },
  // ---- PR 4
  "input_otp/form": { pr: 4, why: "a form-disabled OTP writes its digits past readonly", violations: ["keyboard: dom", "keyboard: value", "server: value", "type a digit: dom", "type a digit: value"] },
  "noWrite:slider/form": { pr: 4, why: "a value outside the range is clamped and sent on load", violations: ["server value rewritten", "writes on load"] },
  "noWrite:slider/prop": { pr: 4, why: "a value outside the range is clamped and sent on load", violations: ["server value rewritten", "writes on load"] },
  "noWrite:time_picker/form": { pr: 4, why: "a value with seconds is rewritten and sent on load", violations: ["server value rewritten", "writes on load"] },
  "noWrite:time_picker/prop": { pr: 4, why: "a value with seconds is rewritten and sent on load", violations: ["server value rewritten", "writes on load"] },
  "slider/form": { pr: 4, why: "it ignores a disabled form (#184)", violations: ["click the track: dom", "click the track: value", "keyboard: dom", "keyboard: value", "server: value", "tab stops"] },
  "time_picker/form": { pr: 4, why: "no disabled state: typing, arrows and AM/PM change the value (#184)", violations: ["click AM/PM: value", "keyboard: dom", "keyboard: value", "server: value", "type the hour: dom", "type the hour: value"] },
  "time_picker/prop": { pr: 4, why: "no disabled state: typing, arrows and AM/PM change the value (#184)", violations: ["click AM/PM: value", "keyboard: dom", "keyboard: value", "server: value", "tab stops", "type the hour: dom", "type the hour: value"] },
  "toggle/form": { pr: 4, why: "it ignores a disabled form (#184)", violations: ["click the label: dom", "click the label: value", "click the switch: dom", "click the switch: value", "keyboard: dom", "keyboard: value", "server: value", "tab stops"] },
  // ---- PR 5
  "input_color/form": { pr: 5, why: "no disabled state: the palette opens and picks a colour", violations: ["open: popup", "pick a swatch: dom", "pick a swatch: value", "server: value"] },
  "input_color/prop": { pr: 5, why: "no disabled state: the palette opens and picks a colour", violations: ["open: popup", "pick a swatch: dom", "pick a swatch: value", "server: value", "tab stops"] },
  "input_file/form": { pr: 5, why: "a disabled form opens the chooser", violations: ["keyboard: chooser", "open the chooser: chooser", "tab stops"] },
  "input_file/prop": { pr: 5, why: "a disabled form opens the chooser", violations: ["tab stops"] },
  "input_file_slot/form": { pr: 5, why: "a host trigger opens the chooser in a disabled form", violations: ["keyboard: chooser", "open the chooser from the slot: chooser", "tab stops"] },
  "input_file_slot/prop": { pr: 5, why: "a host trigger opens the chooser in a disabled form", violations: ["tab stops"] },
  "morph:input_color/form": { pr: 5, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:input_color/prop": { pr: 5, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "rating/form": { pr: 5, why: "no disabled state", violations: ["click the fifth star: dom", "click the fifth star: value", "keyboard: dom", "keyboard: value", "server: value", "tab stops"] },
  "rating/prop": { pr: 5, why: "no disabled state", violations: ["click the fifth star: dom", "click the fifth star: value", "keyboard: dom", "keyboard: value", "server: value", "tab stops"] },
  "uploader/form": { pr: 5, why: "the button stays a tab stop, and a disabled form opens the chooser", violations: ["keyboard: chooser", "open the chooser: chooser", "tab stops"] },
  "uploader/prop": { pr: 5, why: "the button stays a tab stop, and a disabled form opens the chooser", violations: ["tab stops"] },
  "uploader_dropzone/form": { pr: 5, why: "a drop uploads, and a disabled form opens the chooser", violations: ["drop a file: value", "keyboard: chooser", "open the chooser: chooser", "server: file", "tab stops"] },
  "uploader_dropzone/prop": { pr: 5, why: "a drop uploads, and a disabled form opens the chooser", violations: ["drop a file: value", "server: file", "tab stops"] },
  // ---- PR 6
  "date_picker/prop": { pr: 6, why: "a prop-disabled date picker is a readonly tab stop; it should be a real disabled (#185)", violations: ["tab stops"] },
  "date_picker_time/prop": { pr: 6, why: "a prop-disabled date picker is a readonly tab stop; the date-time value is also rewritten on load", violations: ["tab stops"] },
  "date_range/form": { pr: 6, why: "no disabled state: it opens, presets and keys change the value (#185)", violations: ["keyboard: dom", "keyboard: popup", "open: popup", "pick a preset: dom", "pick a preset: popup", "pick a preset: value", "server: value"] },
  "date_range/prop": { pr: 6, why: "no disabled state: it opens, presets and keys change the value (#185)", violations: ["keyboard: dom", "keyboard: popup", "open: popup", "pick a preset: dom", "pick a preset: popup", "pick a preset: value", "server: value", "tab stops"] },
  "date_range_add/form": { pr: 6, why: "no disabled state: the add pill opens and changes the value (#185)", violations: ["keyboard: popup", "open: popup", "pick a preset: popup", "pick a preset: value", "server: value", "tab stops"] },
  "date_range_add/prop": { pr: 6, why: "no disabled state: the add pill opens and changes the value (#185)", violations: ["keyboard: popup", "open: popup", "pick a preset: popup", "pick a preset: value", "server: value", "tab stops"] },
  "morph:date_picker/form": { pr: 6, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:date_picker/prop": { pr: 6, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:date_picker_time/form": { pr: 6, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:date_picker_time/prop": { pr: 6, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:date_range/form": { pr: 6, why: "an open popup stays open when a morph disables the control; its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render", "popup stays open", "re-enabled DOM differs from a static render"] },
  "morph:date_range/prop": { pr: 6, why: "an open popup stays open when a morph disables the control; its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render", "popup stays open", "re-enabled DOM differs from a static render"] },
  "morph:date_range_add/form": { pr: 6, why: "an open popup stays open when a morph disables the control; its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render", "popup stays open", "re-enabled DOM differs from a static render"] },
  "morph:date_range_add/prop": { pr: 6, why: "an open popup stays open when a morph disables the control; its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render", "popup stays open", "re-enabled DOM differs from a static render"] },
  "morph:textarea_autoresize/form": { pr: 6, why: "its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd); it stays editable after the morph", violations: ["after the morph: type: dom", "after the morph: type: value", "disabled DOM differs from a static render"] },
  "morph:textarea_autoresize/prop": { pr: 6, why: "its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd); it stays editable after the morph", violations: ["after the morph: type: dom", "after the morph: type: value", "disabled DOM differs from a static render"] },
  "noWrite:date_picker_time/form": { pr: 6, why: "a date-time is turned into an ISO instant and sent on load", violations: ["server value rewritten", "writes on load"] },
  "noWrite:date_picker_time/prop": { pr: 6, why: "a date-time is turned into an ISO instant and sent on load", violations: ["server value rewritten", "writes on load"] },
  // ---- PR 7
  "morph:tiptap/form": { pr: 7, why: "an open popup stays open when a morph disables the control", violations: ["popup stays open"] },
  "morph:tiptap/prop": { pr: 7, why: "an open popup stays open when a morph disables the control; its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render", "popup stays open"] },
  "morph:tiptap_chat/prop": { pr: 7, why: "its DOM after the morph is not the DOM of a static render (the control is wire:ignore'd)", violations: ["disabled DOM differs from a static render"] },
  "tiptap/form": { pr: 7, why: "no disabled state: typing, the toolbar and its image input work", violations: ["bold the text: value", "keyboard: chooser", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"], engines: { webkit: { violations: ["bold the text: value", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"] } } },
  "tiptap/prop": { pr: 7, why: "no disabled state: typing, the toolbar and its image input work", violations: ["bold the text: value", "keyboard: chooser", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"], engines: { webkit: { violations: ["bold the text: value", "keyboard: dom", "keyboard: value", "open the toolbar menu: popup", "server: value", "tab stops", "type: dom", "type: value"] } } },
  "tiptap_chat/form": { pr: 7, why: "no disabled state: typing, Enter and the buttons work", violations: ["keyboard: chooser", "keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"], engines: { webkit: { violations: ["keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"] } } },
  "tiptap_chat/prop": { pr: 7, why: "no disabled state: typing, Enter and the buttons work", violations: ["keyboard: chooser", "keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"], engines: { webkit: { violations: ["keyboard: dom", "keyboard: sent", "send with Enter: dom", "send with Enter: sent", "send with the button: popup", "send with the button: sent", "tab stops", "type: dom"] } } },
  // ---- none yet
  "child/update": { pr: "none yet", why: "a Livewire child renders on its own after an update, with no form around it, and comes back editable", violations: ["child is editable", "child value changed"] },
}

// The look rows of disabled-controls.spec.js, keyed by the pair's test id (the same on both builds, in light and dark).
export const LOOK_LEDGER = {
  "chat": { pr: 7, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "color": { pr: 5, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "dropzone": { pr: 5, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "filter": { pr: 2, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "form-input": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "form-select-listbox": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "form-select-native": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "form-textarea": { pr: 8, why: "a field in a disabled form does not look as one with its own disabled", violations: ["looks different"] },
  "range": { pr: 6, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "rating": { pr: 5, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "time": { pr: 4, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "tiptap": { pr: 7, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
  "uploader": { pr: 5, why: "the disabled control looks like the enabled one", violations: ["looks enabled"] },
}

// The pairs of tokens-disabled-looks.spec.js that can be in LOOK_LEDGER: controls whose disabled copy must look unlike the enabled one, and fields that,
// in a disabled <atom:form>, must look as the same field with its own `disabled` (their key is `form-<name>`). The ledger check holds every key to this list.
export const LOOK_DIFFERS = ['time', 'range', 'filter', 'color', 'rating', 'uploader', 'dropzone', 'tiptap', 'chat']
export const LOOK_FORM_MATCHES = ['input', 'textarea', 'select-native', 'select-listbox']
