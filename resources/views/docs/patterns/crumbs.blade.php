{{-- The crumbs slot both app shells share. In the sidebar it sits in the top bar; in the top nav, under the bar. A fixed trail, so
     `<atom:breadcrumbs :items>`: the shells here have no Livewire component to supply one. --}}
@php
$page = request()->query('page', 'invoices');
$page = is_string($page) ? $page : 'invoices';
@endphp

<atom:breadcrumbs :items="[['label' => 'Sales', 'href' => '?page=sales'], ['label' => str($page)->headline()->toString()]]"/>
