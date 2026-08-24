<div>
    {{-- The Livewire root must stay reachable from [data-atom-main] for
         breadcrumbs.js to find _breadcrumbs on it. --}}
    <atom:breadcrumbs />

    <div data-page-content>{{ t('Page content') }}</div>
</div>
