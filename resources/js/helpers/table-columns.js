import onMorphed from './morphed'

// Which columns of a table the user sees. The server (components/table, src/Services/TableColumns.php) prints, per table, a hidden
// source holding the named columns, a stylesheet with one `display: none` rule per hideable column keyed on a token list, and that
// list (`data-atom-column-hide`) on the scroll box, set to the columns hidden by default. This file owns the rest:
//
//  - the user's choices (name => visible, only those that differ from the column's default) and where they live: the host's
//    `columns-state` when it gave one (read on first sight, never stored here), else localStorage under `persist-key`, else memory;
//  - writing the effective list on the scroll box. A morph rewrites the attribute to what the server rendered (the defaults), so the
//    list is written again after every morph, before the browser paints;
//  - the API a column picker calls, and the `atom-table-columns-changed` event it fires.
//
// A first paint with choices from localStorage is the inline script's job (TableColumns::SCRIPT): this file starts after Alpine.

const NAME = /^[A-Za-z0-9_-]{1,64}$/
const PREFIX = 'atom:table-columns:'

// choices shared by every table with the same persist-key; a table with none has a store of its own, kept by its scroll box
const stores = new Map()
const loose = new WeakMap()

const clean = (value) => {
    let out = {}

    if (value && typeof value === 'object' && !Array.isArray(value)) {
        for (let [name, visible] of Object.entries(value)) {
            if (NAME.test(name) && typeof visible === 'boolean') out[name] = visible
        }
    }

    return out
}

// null when storage is unavailable (a throwing localStorage), {} when nothing usable is stored. What is stored is untrusted.
const readStorage = (key) => {
    try {
        let stored = JSON.parse(window.localStorage.getItem(PREFIX + key) || 'null')

        return stored?.v === 1 ? clean(stored.columns) : {}
    }
    catch (e) {
        return null
    }
}

const writeStorage = (store) => {
    try {
        if (Object.keys(store.choices).length) window.localStorage.setItem(PREFIX + store.key, JSON.stringify({ v: 1, columns: store.choices }))
        else window.localStorage.removeItem(PREFIX + store.key)
    }
    catch (e) {
        // memory still holds the choices for this page; nothing else to do
        store.unwritable = true
    }
}

const rootOf = (target) => target?.closest?.('[data-atom-table]') ?? null

// A table's parts, or null when it names no columns. `columns` is the server's list, checked: a name outside the pattern is dropped.
const partsOf = (root) => {
    let source = root.querySelector(':scope > [data-atom-column-source]')
    let scope = root.querySelector('[data-atom-column-scope]')

    if (!source || !scope || scope.closest('[data-atom-table]') !== root) return null

    let columns = []

    try {
        columns = JSON.parse(source.dataset.columns).filter((column) => column && NAME.test(column.name))
    }
    catch (e) {
        return null
    }

    return { source, scope, columns }
}

const storeOf = (parts) => {
    let key = parts.source.dataset.key || null
    let store = key ? stores.get(key) : loose.get(parts.scope)

    if (!store) {
        store = { key, choices: {}, persist: false, unwritable: false, scopes: new Set() }
        if (key) stores.set(key, store)
        else loose.set(parts.scope, store)
    }

    return store
}

// Where the choices come from, decided when a table is first seen and again when it is a new page of a store nobody is showing any
// more (wire:navigate): the host's copy if it gave one, else localStorage, else what memory holds. A table that appears while
// another with the same key is on screen joins its choices instead of replacing them.
const adopt = (parts, store) => {
    let { scope, source } = parts

    store.scopes.forEach((other) => { if (!other.isConnected) store.scopes.delete(other) })

    let fresh = store.scopes.size === 0
    store.scopes.add(scope)

    if (!fresh) return

    if (source.dataset.state !== undefined) {
        try {
            store.choices = clean(JSON.parse(source.dataset.state))
        }
        catch (e) {
            store.choices = {}
        }

        store.persist = false
    }
    else if (store.key) {
        store.persist = true

        let stored = store.unwritable ? null : readStorage(store.key)

        if (stored) store.choices = stored
    }
}

// the visible state of a column: the user's choice, else its default
const visibleIn = (store, column) => !column.hideable || (store.choices[column.name] ?? !column.hidden)

// write the effective hidden list on a table's scroll box. Only when the server rendered one (a table with a hideable column).
const paint = (parts, store) => {
    if (!parts.scope.hasAttribute('data-atom-column-hide') && !parts.columns.some((column) => column.hideable)) return

    let hidden = parts.columns.filter((column) => !visibleIn(store, column)).map((column) => column.name).join(' ')

    if (parts.scope.getAttribute('data-atom-column-hide') !== hidden) parts.scope.setAttribute('data-atom-column-hide', hidden)
}

// bring a table up to date: set it up if it is new, then paint
const refresh = (root) => {
    let parts = root && partsOf(root)

    if (!parts) return null

    let store = storeOf(parts)

    if (!store.scopes.has(parts.scope)) adopt(parts, store)

    paint(parts, store)

    return { ...parts, store }
}

// every table that shows this store's choices
const tablesOf = (store, root) => store.key
    ? [...document.querySelectorAll('[data-atom-table]')].filter((table) => table.querySelector(':scope > [data-atom-column-source]')?.dataset.key === store.key)
    : [root]

// Save the choices after a change: drop what no longer names a hideable column (a renamed or removed one, a column that turned
// locked), store them, repaint every table that shares them and tell the page. `choices` is the new map.
const commit = (found, choices) => {
    let { store, columns } = found
    let known = new Set(columns.filter((column) => column.hideable).map((column) => column.name))
    let before = JSON.stringify(store.choices)

    store.choices = Object.fromEntries(Object.entries(choices).filter(([name]) => known.has(name)))

    if (JSON.stringify(store.choices) === before) return false

    if (store.persist) writeStorage(store)

    tablesOf(store, found.source.closest('[data-atom-table]')).forEach((table) => refresh(table))

    // from the table the change was made on; it bubbles to the window, where `x-on:atom-table-columns-changed.window` listens
    found.source.dispatchEvent(new CustomEvent('atom-table-columns-changed', {
        bubbles: true,
        detail: { key: store.key, columns: { ...store.choices } },
    }))

    return true
}

const change = (target, name, visible) => {
    let found = refresh(rootOf(target))
    let column = found?.columns.find((candidate) => candidate.name === name)

    if (!column?.hideable) return false

    let choices = { ...found.store.choices }

    // a choice that equals the default is not kept, so a default the host changes later reaches this user
    if (visible === !column.hidden) delete choices[name]
    else choices[name] = visible

    return commit(found, choices)
}

// The API for a column picker (and any host code). `target` is a table, or anything inside one.
const tableColumns = {
    // x-init on the source: a table appeared (first load, wire:navigate, a lazy component)
    sync(target) {
        refresh(rootOf(target))
    },

    // the named columns: { name, label, hideable, hidden (the default), visible (now) }
    list(target) {
        let found = refresh(rootOf(target))

        return found ? found.columns.map((column) => ({ ...column, visible: visibleIn(found.store, column) })) : []
    },

    // { key, columns }: the user's choices, the shape of `columns-state` and of the event's detail
    state(target) {
        let found = refresh(rootOf(target))

        return found ? { key: found.store.key, columns: { ...found.store.choices } } : { key: null, columns: {} }
    },

    show: (target, name) => change(target, name, true),

    hide: (target, name) => change(target, name, false),

    toggle(target, name) {
        let column = tableColumns.list(target).find((candidate) => candidate.name === name)

        return column?.hideable ? change(target, name, !column.visible) : false
    },

    // back to the defaults for every column
    reset(target) {
        let found = refresh(rootOf(target))

        return found ? commit(found, {}) : false
    },
}

// A morph strips what the server did not render and rewrites the rest to what it did: the hidden list goes back to the defaults.
// Painted again here, which runs before the browser does. `el` is what was morphed: a component root, or an island's container.
onMorphed((el) => {
    let roots = new Set()

    if (rootOf(el)) roots.add(rootOf(el))
    el?.querySelectorAll?.('[data-atom-table]').forEach((root) => roots.add(root))

    roots.forEach((root) => refresh(root))
})

export default tableColumns
