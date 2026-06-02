<div x-data>
    <atom:form x-on:submit.prevent>
        <atom:input label="Name" required/>
        <atom:input type="email" label="Email" required/>
        <atom:textarea label="Message" rows="3"/>

        <atom:button.group>
            <atom:button>Cancel</atom:button>
            <atom:button type="submit">Send</atom:button>
        </atom:button.group>
    </atom:form>
</div>
