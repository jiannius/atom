<atom:docs.example
title="Basic"
description="Label and caption render through the shared input field wrapper. Bind with wire:model in Livewire components."
view="atom::docs.demos.input.basic"/>

<atom:docs.example
title="Types"
description="text (default), email, password, number, tel, color. tel and email get dedicated Alpine behaviors."
view="atom::docs.demos.input.types"/>

<atom:docs.example
title="Prefix & suffix"
view="atom::docs.demos.input.affixes"/>

<atom:docs.example
title="States"
description="required adds the asterisk. error accepts a message string; in Livewire it defaults to the validation error for the bound field. invalid alone draws the danger border. disabled is sunken with the faint border, not the strong one (it reads as unavailable in dark too), and a field in a disabled atom:form is drawn the same but read-only, so its value stays selectable. A host's own readonly is not a disabled state and looks like an enabled field. Every text control is the control token high (28px compact, 32 comfortable, 40 touch)."
view="atom::docs.demos.input.states"/>

<atom:docs.example
title="OTP / one-time code"
description="input.otp — numeric boxes with auto-advance, paste-to-fill and backspace nav. length, masked and groups props; dispatches otp-completed (and calls the submit method) when full."
view="atom::docs.demos.input.otp"/>
