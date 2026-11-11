@php
// ?tw=floor|latest picks the consumer's Tailwind build (default latest): the toolbar's width rule is a utility.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table Search Width" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
{{-- The toolbar's 240px search (#208): a default search, one a page widened with its own class, one with a base width. --}}
<div class="space-y-4 p-4">
    <atom:table.toolbar data-testid="default">
        <atom:table.search placeholder="Search" data-testid="search-default" />
    </atom:table.toolbar>

    <atom:table.toolbar data-testid="wide">
        <atom:table.search placeholder="Search order number or member name" class="sm:w-[28rem]" data-testid="search-wide" />
    </atom:table.toolbar>

    <atom:table.toolbar data-testid="base">
        <atom:table.search placeholder="Search" class="w-72" data-testid="search-base" />
    </atom:table.toolbar>
</div>

@livewireScripts
</atom:html>
