{{-- Markers: each case below is held by DesignCheckMarkersTest, which scans this host with success-button and own-overflow. --}}
<atom:page>
    {{-- atom:design-check ignore success-button: the export is the next step here --}}
    <atom:button variant="success">Next line</atom:button>

    <atom:button variant="success">Same line</atom:button> {{-- atom:design-check ignore success-button: a trailing marker covers its own line --}}

    {{-- atom:design-check ignore success-button, own-overflow: two ids on one marker --}}

    <atom:button variant="info">After a blank line</atom:button>

    <atom:button variant="success">Not covered: the marker covers one line</atom:button> {{-- expect: success-button --}}

    {{-- atom:design-check off success-button: the whole group below is deliberate --}}
    <atom:button variant="success">In the region, one</atom:button>
    <atom:button variant="info">In the region, two</atom:button>
    {{-- atom:design-check on --}}
    <atom:button variant="success">After the region</atom:button> {{-- expect: success-button --}}

    {{-- atom:design-check ignore success-button --}}
    <atom:button variant="success">Marker without a reason: still honoured, and reported</atom:button>

    {{-- atom:design-check ignore success-button: nothing to suppress on the next line --}}
    <atom:button variant="primary">Unused</atom:button>

    {{-- atom:design-check ignore no-such-check: a typo --}}
    <atom:button variant="success">Unknown id on its marker</atom:button> {{-- expect: success-button --}}

    {{-- atom:design-check ignore all: too broad --}}

    {{-- atom:design-check on --}}

    <!-- atom:design-check ignore success-button: an HTML comment is not a marker -->
    <atom:button variant="success">HTML comment above</atom:button> {{-- expect: success-button --}}
</atom:page>
