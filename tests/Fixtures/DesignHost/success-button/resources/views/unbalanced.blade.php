{{-- An unbalanced view is read as text only: the card below is never closed, and this check, which needs no nesting, still runs --}}
<atom:page>
    <atom:card heading="Jobs">
        <atom:button variant="success">Approve</atom:button> {{-- expect: success-button --}}
</atom:page>
