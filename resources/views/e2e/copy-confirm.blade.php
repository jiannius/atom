{{-- <atom:copy>, $clipboard and <atom:confirm> (issue #165). The spec stubs navigator.clipboard and opens the confirm with atom.confirm(). --}}
<atom:html title="E2E: Copy and confirm" :vite="[]" :dark="false">
<div class="flex flex-col items-start gap-4 p-4">
    <atom:copy value="hello" data-testid="copy-value"/>
    <atom:copy data-copy-value="from-attribute" data-testid="copy-attribute"/>
    <atom:copy value="slotted" data-testid="copy-slot"><button type="button">Copy link</button></atom:copy>
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
