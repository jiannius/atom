<div class="max-w-3xl">
    <atom:form x-on:submit.prevent>
        <atom:form.section heading="Webhooks" subheading="Where we send events.">
            <div class="@md:col-span-2"><atom:input label="Endpoint URL" value="https://example.test/hooks"/></div>

            <x-slot:footer>
                <atom:button type="submit">Save webhook</atom:button>
            </x-slot:footer>
        </atom:form.section>
    </atom:form>
</div>
