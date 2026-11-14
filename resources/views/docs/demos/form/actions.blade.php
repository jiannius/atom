<div x-data>
    <atom:form x-on:submit.prevent>
        <atom:input label="Name"/>

        <atom:form.actions>
            <atom:button type="submit">Save</atom:button>
            <atom:button type="delete" x-on:click.prevent>Delete</atom:button>
        </atom:form.actions>
    </atom:form>
</div>
