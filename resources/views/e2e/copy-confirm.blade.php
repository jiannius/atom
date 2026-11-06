@php
// <atom:copy>, $clipboard, <atom:sharer>'s copy link and <atom:confirm> (issue #165). The spec stubs navigator.clipboard and opens the
// confirm with atom.confirm(), from <atom:confirm.trigger> and from PHP (tests/Fixtures/ConfirmPassphraseFixture.php).
// ?tw=floor or ?tw=latest: the consumer's Tailwind, for the field's error border.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Copy and confirm" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="flex flex-col items-start gap-4 p-4">
    <atom:copy value="hello" data-testid="copy-value"/>
    <atom:copy data-copy-value="from-attribute" data-testid="copy-attribute"/>
    <atom:copy value="slotted" data-testid="copy-slot"><button type="button">Copy link</button></atom:copy>
    <atom:copy :value="0" data-testid="copy-zero"/>
    <atom:sharer url="https://example.com/post" title="A post" :sites="[]" data-testid="sharer"/>
</div>

{{-- what <atom:confirm.trigger> dispatches on accept, recorded for the spec --}}
<div x-data x-on:confirmed="window.__confirmed = $event.detail" class="p-4">
    <atom:confirm.trigger passphrase="example.com"><button type="button">Delete domain</button></atom:confirm.trigger>
</div>

<div class="p-4">
    <livewire:atom-e2e-confirm-passphrase />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
