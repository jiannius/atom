@php
// A slide-over with SHORT content (?long=1: long), its pinned bar, opened on load: ?tw=floor|latest, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$long = request()->query('long') === '1';
@endphp

<atom:html title="E2E: slide-over short" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-4">
    <atom:button x-on:click="atom.modal('short').slide()" data-testid="open">Open</atom:button>

    <atom:modal name="short" class="w-[28rem]" data-testid="slide-over">
        <atom:form x-data x-on:submit.prevent :error-toast="false">
            <atom:page.header title="Request a change" level="2" size="sm"/>

            <div class="grid gap-6">
                <atom:form.section layout="stacked" level="3" heading="Details" subheading="What should change.">
                    <atom:input label="Subject"/>
                    @if ($long)
                        @foreach (range(1, 14) as $i)
                            <atom:input label="Field {{ $i }}"/>
                        @endforeach
                    @endif
                </atom:form.section>
            </div>

            <atom:form.actions sticky>
                <x-slot:start><span class="text-xs text-fg-subtle">Unsaved changes</span></x-slot:start>
                <atom:button type="submit" data-testid="short-submit">Send request</atom:button>
            </atom:form.actions>
        </atom:form>
    </atom:modal>
</div>

<div x-data x-init="$nextTick(() => atom.modal('short').slide())"></div>

@livewireScripts
</atom:html>
