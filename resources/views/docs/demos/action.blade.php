<div class="max-w-2xl space-y-4 leading-relaxed">
    <p>
        <code>&lt;atom:action&gt;</code> is a button or a menu item the surface it sits in places for you. Write each action once, with what it
        is (<code>priority</code> and <code>tone</code>), and atom draws the first ones as buttons and the rest as rows of the surface's ⋯ menu:
        the caps, the order, the separator and the confirm in the action's tone come from atom, so a record's actions sit in the same places on
        every page. <code>page.header</code> places them today.
    </p>

    <p>
        It draws nothing where nothing places it: use it inside a slot a surface reads. This is not an <code>App\Actions</code> class: it never
        runs one.
    </p>
</div>

<atom:docs.example
title="In a page header"
description="Two buttons, the rest in the ⋯ menu (utilities first, then a separator, then warning, then danger), the primary last. Duplicate is low, so it never shows as a button; Void and Delete are toned, so they never do either, and each asks before it acts, in its tone. Try the ⋯ menu."
view="atom::docs.demos.action.header"/>
