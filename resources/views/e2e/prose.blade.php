@php
// <atom:prose> on the consumer's Tailwind: ?tw=floor|latest, ?dark=1. A message bubble of each size over markdown that tries to
// run script (raw html, a handler on an image, javascript: links), the same text over the byte limit (shown as it is), stored
// content, and the slot. Invented, generic data.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';

$markdown = "## Plan\n\nA **bold** step, a [link](https://example.com), `code`, and a [bad link](javascript:window.__pwned=1).\n\n- one\n- two\n\n<img src=x onerror=\"window.__pwned=1\">\n\n![tracking pixel](https://example.invalid/pixel.png)\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\n```php\necho 1;\n```";
$long = "# Heading stays text\n<script>window.__pwned=1</script>\n".str_repeat('word ', 7000);
$doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Stored content']]]]];
@endphp

<atom:html title="E2E: Prose" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="grid max-w-xl gap-6 p-6">
    <div class="rounded-panel bg-neutral-bg px-3.5 py-2.5" data-testid="bubble-sm"><atom:prose :markdown="$markdown" size="sm" data-testid="prose-sm"/></div>
    <div class="rounded-panel bg-neutral-bg px-3.5 py-2.5" data-testid="bubble-base"><atom:prose :markdown="$markdown" data-testid="prose-base"/></div>

    <atom:prose :markdown="$long" size="sm" data-testid="prose-long"/>
    <atom:prose :content="$doc" data-testid="prose-content"/>
    <atom:prose data-testid="prose-slot"><p>Slot <b>markup</b></p></atom:prose>

    <atom:timeline kind="message" label="Conversation">
        <atom:timeline.item speaker="Assistant" time="10:02" data-testid="message">
            <atom:prose :markdown="$markdown" size="sm"/>
        </atom:timeline.item>
    </atom:timeline>
</div>
</atom:html>
