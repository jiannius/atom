{{-- The header shell's worst case, for the widths spec: long labels, a count on each item, groups, a context switcher
     (a dropdown, so Escape can be tested against it), a search field and an action. At 1024px none of it fits. --}}
@php
$styles = $styles ?? [];
$page = request()->query('page', 'purchase-orders');
$page = is_string($page) ? $page : 'purchase-orders';

// Switches for the specs: ?hide=<page>,<page> hides those units, ?lw=1 puts a Livewire counter in the actions slot,
// ?refs=1 gives one item aria-describedby / aria-controls / a label that point at ids.
$hide = explode(',', (string) request()->query('hide', ''));
$hidden = fn (string $key) => in_array($key, $hide, true) ? 'display: none' : null;
@endphp

<atom:layouts.header title="Wide nav" :vite="[]" :styles="$styles" :dark="$dark ?? false">
    <x-slot:brand>
        <a href="?page=purchase-orders" class="flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight">
            <div class="flex size-[22px] items-center justify-center rounded-nav bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-3.5"/>
            </div>
            <span class="max-sm:sr-only">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:context>
        <atom:dropdown position="bottom" align="start">
            <atom:button size="sm" variant="ghost" icon-suffix="dropdown">Acme Manufacturing Sdn Bhd</atom:button>

            <atom:menu popover>
                <atom:menu.item>Acme Manufacturing</atom:menu.item>
                <atom:menu.item>Acme Trading</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </x-slot>

    <x-slot:search>
        <atom:input icon="search" placeholder="Search" aria-label="Search" class="w-44"/>
    </x-slot>

    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=purchase-orders" :style="$hidden('purchase-orders')" count="12" :current="$page === 'purchase-orders'">Purchase orders</atom:navlist.item>
            <atom:navlist.item href="?page=work-orders" :style="$hidden('work-orders')" count="8" :current="$page === 'work-orders'">Work orders</atom:navlist.item>
            <atom:navlist.item href="?page=sales-invoices" :style="$hidden('sales-invoices')" count="31" :current="$page === 'sales-invoices'">Sales invoices</atom:navlist.item>
            <atom:navlist.item href="?page=delivery-orders" :style="$hidden('delivery-orders')" count="4" :current="$page === 'delivery-orders'">Delivery orders</atom:navlist.item>
            <atom:navlist.item href="?page=stock-transfers" count="2" :style="$hidden('stock-transfers')" :current="$page === 'stock-transfers'" :aria-describedby="request()->query('refs') ? 'shell-hint' : null" :aria-controls="request()->query('refs') ? 'shell-hint' : null">Stock transfers @if (request()->query('refs'))<label for="shell-hint" aria-labelledby="shell-hint" data-testid="shell-ref-label">hint</label>@endif</atom:navlist.item>

            <atom:navlist.group heading="Financial reports" :style="$hidden('financial-reports')">
                <atom:navlist.item href="?page=trial-balance" count="1" :current="$page === 'trial-balance'">Trial balance</atom:navlist.item>
                <atom:navlist.item href="?page=aged-debtors" count="5" :current="$page === 'aged-debtors'">Aged debtors</atom:navlist.item>
            </atom:navlist.group>

            <atom:navlist.group heading="Administration" expandable persist-key="wide.admin" :style="$hidden('administration')">
                <atom:navlist.item href="?page=users-and-roles" count="9" :current="$page === 'users-and-roles'">Users and roles</atom:navlist.item>
                <atom:navlist.item href="?page=company-settings" :current="$page === 'company-settings'">Company settings</atom:navlist.item>
            </atom:navlist.group>
        </atom:navlist>
    </x-slot>

    <x-slot:actions>
        @if (request()->query('lw'))
            <livewire:atom-e2e-shell-actions />
        @endif

        <atom:button size="sm" variant="primary" icon="add">New purchase order</atom:button>
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <div class="px-7 pb-24 pt-6 max-lg:px-4" data-testid="pattern-page">
        <div class="text-2xl font-semibold" data-testid="pattern-title">{{ $page }}</div>
    </div>

    <span id="shell-hint" class="sr-only">hint</span>

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</atom:layouts.header>
