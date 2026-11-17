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

<atom:docs.example
title="In a card and a panel"
description="A card or a panel draws one action as a link (an href) or a ghost button, 12px in a card and 14px in a panel; the rest, and every toned one, are rows of its ⋯, which is named for the box (More actions for Parts used). Write them in the actions slot; an action in the body draws nothing."
view="atom::docs.demos.action.card"/>

<atom:docs.example
title="In a table's toolbar"
description="Two ghost buttons in the end group, the rest in the ⋯ (Archive old, then Copy list, a separator and Void all). A button with an icon shows only the icon below sm and keeps its name for a screen reader. The bulk bar reads its checked and checked-menu slots the same way: three buttons, the first delete or danger action pinned after them, the rest in the ⋯."
view="atom::docs.demos.action.toolbar"/>
