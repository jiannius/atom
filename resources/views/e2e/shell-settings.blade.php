@php
// The settings screen (page.columns with a `nav` slot) in the sidebar shell, as a Livewire component so a round trip morphs the nav.
// ?tw=floor|latest is the consumer build (default latest); ?section=<key> is the current item (default: the last of the list); ?nav=late prints the slot without its navlist until $wire.showNav();
// ?dir=rtl writes dir="rtl" on <html> before the page is drawn; ?links=1 makes the nav items links that wire:navigate to this page again (the query is carried).
Auth::setUser(new \Illuminate\Auth\GenericUser(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]));
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$section = is_string(request()->query('section')) ? request()->query('section') : 'domains';
$rtl = request()->query('dir') === 'rtl';
$links = request()->query('links') === '1';
$carry = array_filter(['tw' => $tw, 'links' => $links ? '1' : null, 'dir' => $rtl ? 'rtl' : null]);
@endphp

<atom:layouts.sidebar title="E2E: settings page" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
    <x-slot:nav>
        <atom:navlist><atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item></atom:navlist>
    </x-slot>

    @if ($rtl)
        <script>document.documentElement.dir = 'rtl'</script>
    @endif

    <livewire:atom-e2e-settings-page :section="$section" :nav-ready="request()->query('nav') !== 'late'" :links="$links" :carry="$carry" />

    @livewireScripts
</atom:layouts.sidebar>
