{{-- page-header (a): a page that opens with something else --}}
{{-- expect: page-header --}}
<atom:page>
    <atom:callout variant="warning">Read only</atom:callout>
    <atom:panel heading="Lines">Rows</atom:panel>
</atom:page>

{{-- the header is not in the first element, and the first element holds no echo before its first atom tag --}}
{{-- expect: page-header --}}
<atom:page>
    <div class="space-y-4"><atom:callout>{{ $message }}</atom:callout></div>
</atom:page>

{{-- expect: page-header --}}
<atom:page>
    <div class="flex"><atom:button>Edit</atom:button> {{ $suffix }}</div>
</atom:page>
