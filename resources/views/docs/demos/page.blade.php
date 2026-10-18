<div class="max-w-2xl space-y-4 leading-relaxed">
    <p>
        <code>page</code> is the padded body of a screen: the shell's <code>&lt;main&gt;</code> is not padded, so
        everything on a page sits inside one <code>&lt;atom:page&gt;</code>. Inside it, <code>page.header</code> comes
        first, then stats, <code>page.tabs</code> and the content, as siblings: the page keeps a 22px gap between them.
    </p>

    <p>
        See the whole thing built on a record (header, status, stats, record tabs, empty states):
        <a class="underline" href="{{ route('atom.docs.pattern', 'record-page') }}">Record page</a>.
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
title="In a modal or a slide-over"
description="For a form, never a whole record: size=sm and level=2. The header is pure Blade and CSS, so a Livewire morph leaves it alone."
view="atom::docs.demos.page.compact"/>
