{{-- own-sidebar-toggle. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:button icon="panel-left" x-on:click="$dispatch('toggle-sidebar')" /> {{-- expect: own-sidebar-toggle --}}

<atom:button variant="ghost" icon="panel-left-open" aria-label="Open the sidebar" /> {{-- expect: own-sidebar-toggle --}}

<atom:button variant="ghost" icon="panel-left-close" /> {{-- expect: own-sidebar-toggle --}}

<button type="button"><atom:icon.panel-left-close /></button> {{-- expect: own-sidebar-toggle --}}

<atom:button variant="ghost"><atom:icon.panel-left /></atom:button> {{-- expect: own-sidebar-toggle --}}

<button type="button" @click="$dispatch('toggle-sidebar')">Menu</button> {{-- expect: own-sidebar-toggle --}}

<div x-on:toggle-sidebar.window="redraw()"> {{-- expect: own-sidebar-toggle --}}
    <canvas></canvas>
</div>

<div @toggle-sidebar.window="redraw()"> {{-- expect: own-sidebar-toggle --}}
    <canvas></canvas>
</div>

{{-- the look-alikes --}}
<atom:button icon="menu" aria-label="Open the menu" />

<atom:button variant="ghost"><atom:icon.menu /></atom:button>

<atom:button><atom:icon.panel-left /> Hide the panel</atom:button>

<atom:button icon="panel-top" />

<atom:button x-on:click="$dispatch('toggle-filters')">Filters</atom:button>

<div x-on:toggle-filters.window="redraw()"></div>

<atom:icon.panel-left />

<atom:menu.item icon="panel-left">Hide the panel</atom:menu.item>
