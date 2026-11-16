{{-- danger-section: a danger section that is not the only one, or not last. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:form wire:submit="save">
    <atom:form.section heading="Archive" variant="danger"> {{-- expect: danger-section --}}
        <atom:button>Archive</atom:button>
    </atom:form.section>

    <atom:form.section heading="Notifications">
        <atom:checkbox label="Email me" />
    </atom:form.section>
</atom:form>

<atom:form wire:submit="remove">
    <atom:form.section heading="Details">
        <atom:input label="Name" />
    </atom:form.section>

    <atom:form.section heading="Danger zone" variant="danger">
        <atom:button type="delete">Delete</atom:button>
    </atom:form.section>
</atom:form>

<atom:form wire:submit="two">
    <atom:form.section heading="Archive" variant="danger"> {{-- expect: danger-section --}}
        <atom:button>Archive</atom:button>
    </atom:form.section>

    <atom:form.section heading="Delete" variant="danger"> {{-- expect: danger-section --}}
        <atom:button type="delete">Delete</atom:button>
    </atom:form.section>
</atom:form>

<atom:form wire:submit="exclusive">
    @if ($archived)
        <atom:form.section heading="Restore" variant="danger">
            <atom:button>Restore</atom:button>
        </atom:form.section>
    @else
        <atom:form.section heading="Archive" variant="danger">
            <atom:button>Archive</atom:button>
        </atom:form.section>
    @endif
</atom:form>

<atom:form wire:submit="other">
    <atom:form.section heading="Other form" variant="plain">
        <atom:input label="Name" />
    </atom:form.section>
</atom:form>
