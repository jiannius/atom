{{-- typed-optional: "(optional)" typed into a label. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:form wire:submit="save">
    <atom:input label="Notes (optional)" /> {{-- expect: typed-optional --}}
    <atom:textarea label="{{ t('Remarks (Optional)') }}" /> {{-- expect: typed-optional --}}
    <atom:select :label="t('Region ( optional )')" /> {{-- expect: typed-optional --}}
    <atom:date-picker label="Due (optional)" /> {{-- expect: typed-optional --}}
    <atom:input label="Notes" />
    <atom:input label="Optional notes" />
    <atom:input label="Notes (optional) for staff" />
    <atom:input :label="$label" />
    <atom:input label="Notes" placeholder="Optional" />
    <atom:button>Skip (optional)</atom:button>
    <atom:button label="Add note (optional)" />
    <atom:link href="#">Details (optional)</atom:link>
</atom:form>

<atom:form marker="optional" wire:submit="other">
    <atom:input label="Notes" />
    <atom:input label="Reference" required />
</atom:form>
