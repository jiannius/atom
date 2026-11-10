<div>
    <atom:form disabled>
        <atom:input label="Name"/>
        <atom:toggle label="Compact view" x-model="compact"/>
        <atom:slider label="Zoom" x-model="zoom"/>
        <atom:select variant="filter" x-model="status" label="Status"/>
        <atom:toggle label="Active" :disabled="false"/>
        <atom:slider label="Fixed" disabled/>
        <atom:select variant="listbox" label="Pick"/>
    </atom:form>

    <atom:toggle label="Outside the form"/>

    <atom:form :disabled="false">
        <atom:toggle label="In an enabled form"/>
    </atom:form>

    <atom:form :disabled="$record->locked">
        <atom:slider label="Bound form" x-model="zoom"/>
    </atom:form>
</div>
