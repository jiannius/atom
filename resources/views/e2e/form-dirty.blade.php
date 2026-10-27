@php
// ?mode=plain is a form with no Livewire component; ?dirty=0 is the bar without the `dirty` prop.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$plain = request()->query('mode') === 'plain';
$dirty = request()->query('dirty') !== '0';
@endphp

<atom:html title="E2E: unsaved changes" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="request()->query('dark') === '1'">
@if ($plain)
    <atom:page data-testid="page">
        <atom:form x-on:submit.prevent data-form="plain">
            <atom:input label="Name" name="name" value="Acme" data-probe="name" />
            <atom:select label="Region" variant="listbox" x-data="{ region: null }" x-model="region" data-select="region" :options="[
                ['value' => 'north', 'label' => 'North'],
                ['value' => 'south', 'label' => 'South'],
            ]" />

            <div class="h-[800px]">Long content</div>

            <atom:form.actions bar :dirty="$dirty">
                <atom:button type="submit" data-submit="main">Save</atom:button>
            </atom:form.actions>
        </atom:form>
    </atom:page>
@else
    <livewire:atom-e2e-form-dirty :dirty="$dirty" />
@endif

@livewireScripts
</atom:html>
