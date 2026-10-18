{{-- The crumbs slot both app shells share. In the sidebar it sits in the top bar; in the top nav, under the bar. --}}
@php
$page = request()->query('page', 'invoices');
$page = is_string($page) ? $page : 'invoices';
@endphp

<div class="flex items-center gap-1.5 text-fg-subtle">
    <span>Sales</span>
    <atom:icon.right class="size-3.5"/>
    <span class="font-medium text-fg">{{ str($page)->headline() }}</span>
</div>
