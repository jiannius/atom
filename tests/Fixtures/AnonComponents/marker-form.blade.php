{{-- A host wrapper that hands its attributes (here a `marker`) to the form it draws, with fields of its own inside it. --}}
<atom:form {{ $attributes }}>
    <atom:input name="a" label="Name" required />
    <atom:input name="b" label="Phone" />
</atom:form>
