<atom:docs.example
title="Basic"
description="The sidebar navigation primitive. atom:navlist.group adds an (optional) heading and separates sections; atom:navlist.item takes an icon, a count or badge, and highlights itself when its href matches the current URL (set current to force it). This is the same component the docs sidebar uses."
view="atom::docs.demos.navlist.basic"/>

<atom:docs.example
title="Remembering which groups are collapsed"
description="persist-key stores the open/closed state of an expandable group in localStorage, namespaced under atom:navlist-group:. Collapse Purchase and reload this page — it stays collapsed, while Sales (no key) springs back open. The key must be stable across pages, never derived from the current route. Note the precedence: expanded is only the starting state for a group the user has never touched, so a stored value always wins — expand Analytics and reload and it stays open despite :expanded=false. Two groups sharing a key will sync, which is usually what you want for a nav rendered in more than one layout."
view="atom::docs.demos.navlist.persist"/>
