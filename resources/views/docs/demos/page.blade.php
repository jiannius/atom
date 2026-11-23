<div class="max-w-2xl space-y-4 leading-relaxed">
    <p>
        <code>page</code> is the padded body of a screen: the shell's <code>&lt;main&gt;</code> is not padded, so
        everything on a page sits inside one <code>&lt;atom:page&gt;</code>. Inside it, <code>page.header</code> comes
        first, then stats, <code>page.tabs</code> and the content, as siblings: the page keeps a 22px gap between them.
    </p>

    <p>
        See the whole thing built on a record (header, status, stats, record tabs, empty states):
        <a class="underline" href="{{ route('atom.docs.pattern', 'record-page') }}">Record page</a>. A settings screen, with a column of navigation beside the forms and one save bar across both:
        <a class="underline" href="{{ route('atom.docs.pattern', 'settings-page') }}">Settings page</a>.
    </p>
</div>

<atom:docs.example
title="Page header"
description="Crumbs, the title with its status beside it, key facts, secondary actions, an overflow menu and the one primary action for the record's current state, last. The title, crumb labels and facts print as text. Use the shell's own crumbs slot or the crumbs prop here, not both."
view="atom::docs.demos.page.header"/>

<atom:docs.example
title="Record tabs"
description="Link tabs with counts, straight under the header. The current tab carries aria-current=page. A tab whose href safe_url() refuses is plain text."
view="atom::docs.demos.page.tabs"/>

<atom:docs.example
title="Main column and rail"
description="No breakpoint: the rail (a 320px aside, 14px between its cards) sits beside the main column while that keeps 60% of the row, and under it, full width, when it no longer fits, so a collapsed sidebar or the top-nav shell brings it beside sooner. sticky pins it under the shell's bar while it is beside the main column; label names the aside."
view="atom::docs.demos.page.columns"/>

<atom:docs.example
title="A settings screen: a nav beside the forms"
description="The nav slot is a 200px column of navigation before the main column, from a row 768px wide; narrower, it is a row above the main column and the navlist a strip that scrolls sideways, with the current item kept in view. Put an atom:navlist in it (a plain div holds it: the navlist is the landmark, so give it an aria-label). A save bar (atom:form.actions bar) in the main column spans nav and forms while its buttons stay where the content starts."
view="atom::docs.demos.page.columns-nav"/>

<atom:docs.example
title="In a modal or a slide-over"
description="For a form, never a whole record: size=sm and level=2. The header is pure Blade and CSS, so a Livewire morph leaves it alone."
view="atom::docs.demos.page.compact"/>
