<div x-data class="space-y-6">
    <div>
        <atom:caption>Narrow container (max-w-sm) → collapses to 1 column</atom:caption>
        <div class="max-w-sm border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <atom:form x-on:submit.prevent>
                <atom:form.grid cols="auto">
                    <atom:input label="First name"/>
                    <atom:input label="Last name"/>
                </atom:form.grid>
            </atom:form>
        </div>
    </div>

    <div>
        <atom:caption>Wide container (max-w-3xl) → 2 columns</atom:caption>
        <div class="max-w-3xl border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <atom:form x-on:submit.prevent>
                <atom:form.grid cols="auto">
                    <atom:input label="First name"/>
                    <atom:input label="Last name"/>
                </atom:form.grid>
            </atom:form>
        </div>
    </div>
</div>
