{{-- persisted-static-crumbs. A line marked `expect:` is a finding; every other line must stay clean. --}}
@persist('breadcrumbs')
    <atom:breadcrumbs :items="$trail" /> {{-- expect: persisted-static-crumbs --}}
@endpersist

@persist('crumbs')
    <div>
        <atom:breadcrumbs items="Jobs" /> {{-- expect: persisted-static-crumbs --}}
    </div>
@endpersist

@persist('outer')
    @persist('inner')
        <atom:breadcrumbs :items="$trail" /> {{-- expect: persisted-static-crumbs --}}
    @endpersist
@endpersist

{{-- the look-alikes --}}
@persist('breadcrumbs')
    <atom:breadcrumbs />
@endpersist

<atom:breadcrumbs :items="$trail" />

@persist('sidebar')
    <atom:navlist>Items</atom:navlist>
@endpersist

<atom:breadcrumbs :items="$after" />

@@persist('escaped')
    <atom:breadcrumbs :items="$trail" />
@endpersist

@persist('open')
    <atom:breadcrumbs :items="$never_closed" />
