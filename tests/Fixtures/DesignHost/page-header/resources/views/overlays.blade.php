{{-- a title in an overlay, a hidden block or an empty state is not the page's: none of these is flagged --}}
<atom:page>
    <atom:page.header title="Jobs" />

    <div class="fixed inset-0" x-show="open"><div class="p-6"><h1>Fixed</h1></div></div>
    <div class="fixed"><h1>Fixed alone</h1></div>
    <div class="absolute"><h1>Absolute</h1></div>
    <div class="inset-0"><h1>Inset</h1></div>
    <div class="z-50"><h1>Layer</h1></div>
    <div class="hidden"><h1>Hidden class</h1></div>
    <div hidden><h1>Hidden attribute</h1></div>
    <div class="max-w-sm text-center"><h1>Empty state</h1></div>
    <div role="dialog"><h1>Dialog</h1></div>
    <div role="alert"><h1>Alert</h1></div>
    <div aria-modal="true"><h1>Modal</h1></div>
    <div x-show="open"><h1>Shown on a click</h1></div>
    <template x-if="open"><div><h1>Template</h1></div></template>
    <details class="border p-4"><summary><h1>Summary</h1></summary></details>
    <article class="border"><h1>Article</h1></article>

    <div class="flex"><h1>A plain wrapper</h1></div> {{-- expect: page-header --}}
</atom:page>
