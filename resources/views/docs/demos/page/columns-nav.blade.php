<atom:page.columns>
    <x-slot:nav>
        <atom:navlist aria-label="Settings sections">
            <atom:navlist.group heading="Account">
                <atom:navlist.item href="#profile" :current="true">Profile</atom:navlist.item>
                <atom:navlist.item href="#security">Security</atom:navlist.item>
                <atom:navlist.item href="#notifications">Notifications</atom:navlist.item>
            </atom:navlist.group>

            <atom:navlist.group heading="Workspace">
                <atom:navlist.item href="#general">General</atom:navlist.item>
                <atom:navlist.item href="#members">Members</atom:navlist.item>
                <atom:navlist.item href="#billing">Billing</atom:navlist.item>
                <atom:navlist.item href="#integrations">Integrations</atom:navlist.item>
            </atom:navlist.group>
        </atom:navlist>
    </x-slot:nav>

    <atom:form x-on:submit.prevent>
        <atom:card heading="Profile">
            <div class="grid gap-4">
                <atom:input label="Full name" value="Alex Doe"/>
                <atom:input label="Email" type="email" value="alex@example.com"/>
            </div>
        </atom:card>

        <atom:form.actions bar dirty/>
    </atom:form>
</atom:page.columns>
