@php
// The settings screen (page.columns with a `nav` slot) in the sidebar shell, as a Livewire component so a round trip morphs the nav.
// ?tw=floor|latest is the consumer build (default latest); ?section=<key> is the current item (default: the last of the list); ?nav=late prints the slot without its navlist until $wire.showNav().
Auth::setUser(new \Illuminate\Auth\GenericUser(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]));
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$section = is_string(request()->query('section')) ? request()->query('section') : 'domains';
@endphp

<atom:layouts.sidebar title="E2E: settings page" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
    <x-slot:nav>
        <atom:navlist><atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item></atom:navlist>
    </x-slot>

    <livewire:atom-e2e-settings-page :section="$section" :nav-ready="request()->query('nav') !== 'late'" />

    @livewireScripts
</atom:layouts.sidebar>
