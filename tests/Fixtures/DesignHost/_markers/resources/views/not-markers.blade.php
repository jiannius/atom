{{-- Text that looks like a marker but is not one: a marker is a Blade comment outside an HTML comment and outside @verbatim. None of it suppresses anything, and none of it is reported. --}}
<atom:page>
    <!-- {{-- atom:design-check ignore success-button: inside an HTML comment --}} -->
    <atom:button variant="success">Below an HTML comment</atom:button> {{-- expect: success-button --}}

    @verbatim
        {{-- atom:design-check ignore success-button: inside a verbatim block, shown as text --}}
    @endverbatim
    <atom:button variant="success">Below a verbatim block</atom:button> {{-- expect: success-button --}}
</atom:page>
