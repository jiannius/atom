<atom:html title="E2E: Native select reopen" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Plain-Blade page for the native select spec (issue #84). A single, a multiple and a disabled native select, so a
     click on the wrapper outside the <select> can be told from a click on the <select> itself. --}}
<div class="p-4 space-y-4">
    <div data-probe="single">
        <atom:select label="Payment mode" icon="search">
            <atom:select.option value="cash">Cash</atom:select.option>
            <atom:select.option value="card">Card</atom:select.option>
        </atom:select>
    </div>

    <div data-probe="multiple">
        <atom:select label="Tags" multiple>
            <atom:select.option value="a">Alpha</atom:select.option>
            <atom:select.option value="b">Beta</atom:select.option>
        </atom:select>
    </div>

    <div data-probe="disabled">
        <atom:select label="Locked" disabled>
            <atom:select.option value="x">Ex</atom:select.option>
        </atom:select>
    </div>
</div>

{{-- atom.js needs Livewire's bundled Alpine (alpine:init) to start --}}
@livewireScripts
</atom:html>
