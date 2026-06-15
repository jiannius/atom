<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    // <atom:button>/<atom:tooltip> may pull in components that read $errors.
    view()->share('errors', new ViewErrorBag);
});

describe('uploader', function () {
    it('renders the default upload trigger over a hidden file input', function () {
        $html = renderBlade('<atom:uploader />');

        expect($html)
            ->toContain('group/uploader')
            ->toContain('type="file"')
            ->toContain('x-ref="fileinput"')
            ->toContain('data-atom-icon')   // upload icon
            ->toContain('Upload');
    });

    it('passes file attributes through to the input', function () {
        $html = renderBlade('<atom:uploader wire:model="photo" accept="image/*" multiple />');

        expect($html)
            ->toContain('wire:model="photo"')
            ->toContain('accept="image/*"')
            ->toContain('multiple');
    });

    it('exposes the cancel control as a labelled, properly-closed button', function () {
        // Regression: the cancel button was self-closed (`<button ... />`) so the
        // stop icon rendered OUTSIDE it and the button had no accessible name.
        $html = renderBlade('<atom:uploader wire:model="photo" />');

        expect($html)
            ->toContain('aria-label="Cancel upload"')
            ->toContain('$wire.cancelUpload')
            ->not->toContain('justify-center" />');   // no self-closed button tag
    });

    it('lets a slot replace the default trigger', function () {
        $html = renderBlade('<atom:uploader>BROWSE</atom:uploader>');

        expect($html)
            ->toContain('BROWSE')
            ->toContain('type="file"')
            ->not->toContain('Cancel upload');   // default chrome is skipped
    });

    it('wires the Livewire upload lifecycle and progress bar', function () {
        $html = renderBlade('<atom:uploader />');

        expect($html)
            ->toContain('livewire-upload-start')
            ->toContain('livewire-upload-progress')
            ->toContain('width: ${progress}%');
    });
});

describe('uploader.dropzone', function () {
    it('renders a dashed drag-and-drop drop target', function () {
        $html = renderBlade('<atom:uploader.dropzone />');

        expect($html)
            ->toContain('group/uploader')
            ->toContain('border-dashed')
            ->toContain('type="file"')
            ->toContain('data-atom-icon')
            ->toContain('Drop files here');
    });

    it('binds the drag handlers and drop-to-input wiring', function () {
        $html = renderBlade('<atom:uploader.dropzone />');

        expect($html)
            ->toContain('x-on:dragover.prevent')
            ->toContain('x-on:dragleave.prevent')
            ->toContain('x-on:drop.prevent')
            ->toContain('files = e.dataTransfer.files')
            ->toContain("dispatchEvent(new Event('change'");
    });

    it('passes file attributes through to the input', function () {
        $html = renderBlade('<atom:uploader.dropzone wire:model="files" accept=".pdf" multiple />');

        expect($html)
            ->toContain('wire:model="files"')
            ->toContain('accept=".pdf"')
            ->toContain('multiple');
    });

    it('accepts a custom label', function () {
        $html = renderBlade('<atom:uploader.dropzone label="Drop your resume" />');

        expect($html)->toContain('Drop your resume');
    });

    it('lets a slot replace the default surface but keeps drop wiring', function () {
        $html = renderBlade('<atom:uploader.dropzone>DRAG HERE</atom:uploader.dropzone>');

        expect($html)
            ->toContain('DRAG HERE')
            ->toContain('type="file"')
            ->toContain('x-on:drop.prevent')
            ->not->toContain('Drop files here');   // default surface is skipped
    });

    it('wires the Livewire upload lifecycle and progress bar', function () {
        $html = renderBlade('<atom:uploader.dropzone />');

        expect($html)
            ->toContain('livewire-upload-start')
            ->toContain('livewire-upload-progress')
            ->toContain('width: ${progress}%');
    });
});
