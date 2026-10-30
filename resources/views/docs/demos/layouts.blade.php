<div class="max-w-2xl space-y-4 leading-relaxed">
    <p>
        <code>layouts.sidebar</code> and <code>layouts.header</code> are the two app shells. They take the same props
        and the same slots, so a host can swap one for the other without touching anything else. The nav slot is the
        same <code>&lt;atom:navlist&gt;</code> markup in both: the sidebar draws it as a column, the header as bar
        items, and a <code>navlist.group</code> as a dropdown. Atom never chooses for you: pick the shell in your own
        app layout, for example per role.
    </p>

    <pre class="font-id overflow-x-auto rounded-panel border bg-surface-sunken p-4 text-fg"><code>&lt;x-dynamic-component
    :component="$user->modules()->count() &lt;= 2 ? 'atom::layouts.header' : 'atom::layouts.sidebar'"
    title="Invoices"&gt;
    &lt;x-slot:nav&gt;
        @@include('partials.nav')
    &lt;/x-slot&gt;

    @{{ $slot }}
&lt;/x-dynamic-component&gt;</code></pre>

    <p>
        Slots: <code>brand</code>, <code>context</code>, <code>search</code>, <code>nav</code>,
        <code>navfoot</code>, <code>crumbs</code>, <code>actions</code>, <code>dropdown</code>,
        <code>profile</code>, <code>footer</code>. <code>&lt;main&gt;</code> is unpadded: padding comes from the page.
    </p>

    <p>
        The account menu (the <code>dropdown</code> slot) opens from the brand row: the logo goes home, the app name and
        the caret open the menu. Pass <code>account="end"</code> to put it at the foot of the sidebar, or behind an
        avatar at the end of the top nav, instead.
    </p>

    <p>
        Below 1024px the top nav becomes a menu (the <code>context</code> and <code>search</code> slots are the first
        two rows of its panel). From 1024px the <code>search</code> slot is an icon button that opens a popover, and from
        1280px it is inline; nav items that do not fit the bar move into a trailing "More" dropdown.
        <code>crumbs</code> sit in the sidebar's top bar but under the top nav's bar. A <code>profile</code> with no
        <code>dropdown</code> is plain content, not a button.
    </p>

    <p>See both shells in use, with one shared nav partial:</p>

    <ul class="list-disc ps-5">
        @foreach (\Jiannius\Atom\Services\Docs::PATTERNS as $name => $label)
            <li><atom:link :href="route('atom.docs.pattern', $name)">{{ $label }}</atom:link></li>
        @endforeach
    </ul>
</div>
