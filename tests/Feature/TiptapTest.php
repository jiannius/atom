<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('tiptap', function () {
    it('renders the editor shell with the alpine factory and toolbar role', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)
            ->toContain('x-data="tiptap(')
            ->toContain('x-modelable="editorContent"')
            ->toContain('wire:ignore')
            ->toContain('role="toolbar"')
            ->toContain('x-ref="editor"');
    });

    it('renders the full preset by default', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)
            ->toContain('aria-label="Heading"')
            ->toContain('aria-label="Link"')
            ->toContain('aria-label="Image"');
    });

    it('honours the basic preset (no table/youtube buttons)', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" toolbar="basic" />');

        expect($html)
            ->toContain('aria-label="Link"')
            ->not->toContain('aria-label="Youtube Video"');
    });

    it('renders no toolbar for the none preset', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" toolbar="none" />');

        expect($html)
            ->toContain('x-ref="editor"')
            ->not->toContain('role="toolbar"');
    });

    it('lets a toolbar slot replace the presets', function () {
        $html = renderBlade('<atom:tiptap wire:model="body"><x-slot:toolbar><button type="button" id="custom-btn">X</button></x-slot:toolbar></atom:tiptap>');

        expect($html)
            ->toContain('id="custom-btn"')
            ->toContain('role="toolbar"');
    });

    it('exposes accessible buttons with aria-pressed wiring', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)->toContain('x-bind:aria-pressed');
    });

    it('wires the image button to a livewire upload', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)
            ->toContain("uploadMultiple('_editor.images'")
            ->toContain('type="file"');
    });
});

describe('tiptap.chat', function () {
    it('renders the chat composer with submit + attach + paste/drop wiring', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" />');

        expect($html)
            ->toContain("x-data=\"tiptap(")
            ->toContain('chat: true')
            ->toContain('x-on:paste="paste($event)"')
            ->toContain('x-on:drop.prevent="drop($event)"')
            ->toContain('aria-label="Submit"')
            ->toContain('aria-label="Attach"');
    });

    it('renders the file tray template', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" />');

        expect($html)
            ->toContain('x-for="(file, i) in files"')
            ->toContain('files.splice(i, 1)')
            ->toContain('aria-label="Remove"');
    });

    it('omits the rich-text toolbar in chat mode', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" />');

        expect($html)->not->toContain('role="toolbar"');
    });
});

describe('tiptap.mention', function () {
    it('renders the mention dropdown wired to a wire callback (string)', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" mention="searchUsers" />');

        expect($html)
            ->toContain('class="tiptap-mention"')
            ->toContain('x-data="mention(')
            ->toContain('searchUsers')          // callback name passed to the factory
            ->toContain('x-ref="dropdown"');
    });

    it('renders the mention dropdown from a static option array', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" :mention="[\'Alice\', \'Bob\']" />');

        expect($html)
            ->toContain('class="tiptap-mention"')
            ->toContain('Alice')
            ->toContain('Bob');
    });

    it('does not render a mention dropdown when no mention prop is given', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)->not->toContain('tiptap-mention');
    });

    it('enables mentions in the chat composer too', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" mention="searchUsers" />');

        expect($html)->toContain('class="tiptap-mention"');
    });
});

describe('editor alias (back-compat)', function () {
    it('<atom:editor> renders the tiptap shell and forwards props', function () {
        $html = renderBlade('<atom:editor wire:model="body" toolbar="basic" />');

        expect($html)
            ->toContain('x-data="tiptap(')
            ->toContain('x-modelable="editorContent"')
            ->toContain('aria-label="Link"');   // basic preset forwarded through
    });

    it('<atom:editor.chat> renders the tiptap chat shell', function () {
        $html = renderBlade('<atom:editor.chat wire:model="message" />');

        expect($html)
            ->toContain('chat: true')
            ->toContain('aria-label="Submit"');
    });

    it('<atom:editor.content> renders stored content server-side', function () {
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'aliased']]]]]);
        $html = renderBlade('<atom:editor.content>'.$json.'</atom:editor.content>');

        expect($html)
            ->toContain('editor-content')
            ->toContain('<p>aliased</p>');
    });
});
