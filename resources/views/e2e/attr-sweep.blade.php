<atom:html title="E2E: Attribute sweep" :vite="[]" :dark="true" class="min-h-screen bg-white" data-sweep="body">
{{-- Controls that take a caller class: it lands on the visible root while the
     behaviour (name, value, keyboard) stays on the hidden input. --}}
<div class="p-4 space-y-4">
    <atom:checkbox label="Agree" name="agree" id="sweep-checkbox" class="mt-3" data-sweep="checkbox" />
    <atom:toggle label="Notify" name="notify" id="sweep-toggle" class="mt-3" data-sweep="toggle" />

    <div role="radiogroup" aria-label="Plan">
        <atom:radio label="Free" name="plan" value="free" class="mt-3" data-sweep="radio-free" />
        <atom:radio label="Pro" name="plan" value="pro" class="mt-3" data-sweep="radio-pro" />
    </div>

    {{-- roots that now print their bag: Alpine must still boot on them --}}
    <atom:darkmode-toggle id="sweep-darkmode" class="sweep-darkmode" />

    <div x-data>
        <div data-lightbox>
            <img
            src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA="
            data-lightbox-url="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA="
            data-lightbox-name="One"
            x-on:click="$dispatch('lightbox')"
            width="40" height="40" />
        </div>

        <atom:lightbox id="sweep-lightbox" class="sweep-lightbox" />
    </div>
</div>

{{-- atom.js needs Livewire's bundled Alpine (alpine:init) to start --}}
@livewireScripts
</atom:html>
