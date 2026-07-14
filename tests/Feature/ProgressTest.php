<?php

describe('progress', function () {
    it('renders a determinate bar with the correct width and aria', function () {
        $html = renderBlade('<atom:progress :value="50" />');

        expect($html)
            ->toContain('data-atom-progress')
            ->toContain('role="progressbar"')
            ->toContain('aria-valuemin="0"')
            ->toContain('aria-valuemax="100"')
            ->toContain('aria-valuenow="50"')
            ->toContain('width: 50%');
    });

    it('derives the percent from value over max', function () {
        expect(renderBlade('<atom:progress :value="1" :max="4" />'))
            ->toContain('width: 25%')
            ->toContain('aria-valuenow="25"');
    });

    it('clamps out-of-range values to 0..100', function () {
        expect(renderBlade('<atom:progress :value="150" />'))->toContain('width: 100%');
        expect(renderBlade('<atom:progress :value="-10" />'))->toContain('width: 0%');
    });

    it('applies variant and size classes', function () {
        expect(renderBlade('<atom:progress :value="40" variant="success" />'))->toContain('bg-green-500');
        expect(renderBlade('<atom:progress :value="40" variant="danger" />'))->toContain('bg-red-500');
        expect(renderBlade('<atom:progress :value="40" size="sm" />'))->toContain('h-1.5');
    });

    it('shows the percent label when label is true', function () {
        expect(renderBlade('<atom:progress :value="60" :label="true" />'))->toContain('60%');
    });

    it('shows a custom string label instead of the percent', function () {
        expect(renderBlade('<atom:progress :value="60" label="Uploading" />'))
            ->toContain('<span>Uploading</span>')
            ->not->toContain('<span>60%</span>');
    });

    it('renders an animated indeterminate bar without a value', function () {
        $html = renderBlade('<atom:progress indeterminate />');

        expect($html)
            ->toContain('atom-progress-indeterminate')
            ->toContain('w-2/5')
            ->not->toContain('aria-valuenow')
            ->not->toContain('width:');
    });
});
