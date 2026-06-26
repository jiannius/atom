<atom:docs.example
title="Basic"
description="Wraps a form and auto-wires the submit button's loading state. In Livewire, use wire:submit on the form."
view="atom::docs.demos.form.basic"/>

<atom:docs.example
title="Disabled (read-only)"
description="Pass disabled to make the whole form inert (no focus, pointer or keyboard) and dimmed, and to drop wire:submit so it can't be submitted. Use for records that are locked / read-only."
view="atom::docs.demos.form.disabled"/>

<atom:docs.example
title="Grid columns"
description="atom:form.grid lays out a field group. cols=auto (default) is a container query: it shows 1 column in a narrow parent and 2 columns once the parent is wide enough (~max-w-2xl), regardless of viewport. Use cols=2 or cols=3 to force a viewport-responsive grid. For a single-group form, put cols on atom:form directly."
view="atom::docs.demos.form.grid"/>

<atom:docs.example
title="Actions footer"
description="atom:form.actions standardizes the footer: justify-between puts Save on the left and Delete on the right. An empty footer renders a default Save submit button. The standard delete is type=delete variant=ghost color=danger (de-emphasized red, inverts on hover). No Cancel button — modal dismiss handles it. Pass sticky to pin it to a modal's bottom."
view="atom::docs.demos.form.actions"/>

<atom:docs.example
title="Modal form"
description="atom:form.modal composes modal + form + footer. Fields go in the default slot (laid out by cols=auto, default). Width derives from cols (1-col→xl, auto/2→2xl, 3→4xl) and is overridable via class. The footer auto-includes a Save submit; add a right-aligned delete via the delete slot."
view="atom::docs.demos.form.modal"/>
