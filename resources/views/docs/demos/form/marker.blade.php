<div x-data>
    <atom:form marker="optional" x-on:submit.prevent>
        <atom:input label="Full name" required/>
        <atom:input type="email" label="Work email" required/>
        <atom:input label="Phone number"/>
        <atom:textarea label="Notes" rows="3"/>

        <atom:button type="submit">Send</atom:button>
    </atom:form>
</div>
