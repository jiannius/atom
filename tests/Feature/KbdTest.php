<?php

describe('kbd', function () {
    it('renders one cap per token and maps tokens to symbols', function () {
        $html = renderBlade('<atom:kbd keys="cmd shift k"/>');

        expect(substr_count($html, 'data-atom-kbd-key'))->toBe(3);
        expect($html)
            ->toContain('⌘')
            ->toContain('⇧')
            ->toContain('>K</kbd>'); // unknown single char is upper-cased
    });

    it('splits on a plus sign too', function () {
        $html = renderBlade('<atom:kbd keys="ctrl+alt+del"/>');

        expect(substr_count($html, 'data-atom-kbd-key'))->toBe(3);
        expect($html)
            ->toContain('⌃')
            ->toContain('⌥')
            ->toContain('⌦');
    });

    it('renders the slot as a single cap when no keys prop is given', function () {
        $html = renderBlade('<atom:kbd>Esc</atom:kbd>');

        expect(substr_count($html, 'data-atom-kbd-key'))->toBe(1);
        expect($html)
            ->toContain('data-atom-kbd')
            ->toContain('Esc');
    });
});
