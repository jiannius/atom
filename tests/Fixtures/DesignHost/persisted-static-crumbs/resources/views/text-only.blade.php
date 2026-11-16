{{-- the card is never closed, so this view is read as text only, and this check, which needs no nesting, still runs --}}
<atom:card heading="Jobs">
    @persist('breadcrumbs')
        <atom:breadcrumbs :items="$trail" /> {{-- expect: persisted-static-crumbs --}}
    @endpersist
