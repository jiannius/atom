<div x-data>
    <atom:form marker="optional" x-on:submit.prevent>
        <atom:input label="Name" required/>
        <atom:input type="email" label="Email" required/>
        <atom:input label="Phone"/>
        <atom:textarea label="Message" rows="3"/>

        <atom:button type="submit">Send</atom:button>
    </atom:form>
</div>
