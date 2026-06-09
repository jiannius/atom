<div x-data>
    <atom:form x-on:submit.prevent>
        <atom:input label="Name"/>

        <atom:form.actions>
            <atom:button type="submit">Save</atom:button>
            <atom:button type="delete" variant="ghost" color="danger" x-on:click.prevent>Delete</atom:button>
        </atom:form.actions>
    </atom:form>
</div>
