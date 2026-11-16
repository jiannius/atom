{{-- A host component of its own around a page header: its slot (the actions) is rendered before the header's view starts, so no scope is open yet. --}}
<atom:page.header title="Wrapped">
    <x-slot:actions>{{ $slot }}</x-slot:actions>
</atom:page.header>
