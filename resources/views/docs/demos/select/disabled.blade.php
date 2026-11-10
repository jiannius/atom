<div class="space-y-4">
    <atom:select label="Native" disabled>
        <atom:select.option value="my" label="Malaysia"/>
    </atom:select>

    <atom:select variant="listbox" label="Listbox" :options="[['value' => 1, 'label' => 'Jane Cooper']]" disabled/>

    <atom:select variant="filter" label="Filter" :options="[['value' => 1, 'label' => 'Acme Sdn Bhd']]" disabled/>
</div>
