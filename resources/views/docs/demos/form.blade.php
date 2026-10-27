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
description="atom:form.modal composes modal + form + footer. Fields go in the default slot (laid out by cols=auto, default). Width derives from cols (1-col→xl, auto/2→2xl, 3→4xl) and a w-* or max-w-* class on the tag replaces it (min-w-* does not). The footer auto-includes a Save submit; the right side takes two optional slots: actions (anything that should stay visible while the form scrolls, such as a running total) and delete, which sits at the far edge after actions."
view="atom::docs.demos.form.modal"/>

<atom:docs.example
title="Sections"
description="atom:form.section is a heading, a line of help and the fields in a grid. The default aside layout puts the heading in a 220px column beside the fields once the section is 768px wide, and above them below that. The fields are two across once their own box is 448px wide; class=@md:col-span-2 on a field spans the row. Sections are separated by a hairline and the form's own gap."
view="atom::docs.demos.form.section"/>

<atom:docs.example
title="Stacked sections"
description="layout=stacked always puts the heading above its fields: the layout for a narrow place, such as a slide-over or a modal (use level=3 there, under the modal's own heading). It works in atom:form.modal with cols=1."
view="atom::docs.demos.form.section-stacked"/>

<atom:docs.example
title="Section footer"
description="A footer slot adds a right-aligned row under the fields, for a section that saves on its own. Give that section its own atom:form."
view="atom::docs.demos.form.section-footer"/>

<atom:docs.example
title="Save bar"
description="atom:form.actions with bar is the save bar of a page form: a card (solid surface, border, the panel radius and shadow) that floats 0.75rem above the bottom of the screen while the page is longer than it, as wide as the form's sections. It takes the page's bottom room, so at the end of the page it sits right below the last section. The start slot holds live totals (a b or strong in it is the figure); dirty adds a muted "Unsaved changes" after it while the form has changes that were not saved (type in a field below); the buttons are at the end, full width on a phone, and the submit is the screen's one primary action. In a modal or a slide-over use sticky instead (atom:form.modal draws it for you)."
view="atom::docs.demos.form.save-bar"/>
