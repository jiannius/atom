{{-- Unbalanced: this is a token check, so it still reads the view (a structural check would skip it). --}}
<atom:page>
    <div class="bg-zinc-100">Read</div> {{-- expect: palette-colour --}}
