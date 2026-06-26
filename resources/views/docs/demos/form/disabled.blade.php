<div x-data>
    <atom:form disabled x-on:submit.prevent>
        <atom:input label="Name" value="Ada Lovelace"/>
        <atom:input type="email" label="Email" value="ada@example.com"/>
        <atom:textarea label="Message" rows="3">Read-only form.</atom:textarea>

        <atom:button.group>
            <atom:button type="submit">Save</atom:button>
        </atom:button.group>
    </atom:form>
</div>
