@php
// The danger section and the cleared-queue empty state on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?accent=%23rrggbb
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
@endphp

<atom:html title="E2E: form extras" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
@if ($accent)
<style>:root { --color-accent: {{ $accent }}; }</style>
@endif

<div class="mx-auto grid max-w-3xl gap-8 p-6">
    <atom:form x-data x-on:submit.prevent :error-toast="false" data-testid="settings-form">
        <atom:form.section heading="Domain" subheading="The address the site answers on.">
            <atom:input label="Domain" value="example.test"/>
        </atom:form.section>

        <atom:form.section variant="danger" heading="Archive this site" subheading="It stops serving traffic. You can restore it for 30 days." data-testid="danger-section">
            <div class="@md:col-span-2">
                <atom:button variant="danger" type="button">Archive site</atom:button>
            </div>
        </atom:form.section>
    </atom:form>

    <div class="rounded-panel border bg-surface" data-testid="empty-done">
        <atom:empty kind="done"/>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="empty-done-sm">
        <atom:empty kind="done" size="sm"/>
    </div>
</div>

@livewireScripts
</atom:html>
