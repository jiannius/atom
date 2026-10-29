<?php

use Illuminate\Support\Facades\Vite;
use Jiannius\Atom\Services\TableColumns;

// Column visibility, the server half (the browser half is tests/e2e/table-column-visibility.spec.js): a named column prints its meta,
// a linked cell its token, and the table prints a source, one rule per hideable column and the list of columns hidden by default.
// A column name becomes a CSS token, so the name pattern is the injection gate.

/** A table with the given column markup, every named column one cell wide */
function columnsTable(string $columns, string $props = '', string $rows = '<atom:table.row><atom:table.cell column="email">a@example.com</atom:table.cell></atom:table.row>'): string
{
    return renderBlade('<atom:table :empty="false" '.$props.'><x-slot:columns>'.$columns.'</x-slot:columns><x-slot:rows>'.$rows.'</x-slot:rows></atom:table>');
}

function columnSource(string $html): ?DOMElement
{
    return domQuery($html, '//*[@data-atom-column-source]')[0] ?? null;
}

function scrollBox(string $html): DOMElement
{
    return domQuery($html, '//*[@data-atom-table-scroll]')[0];
}

/** Whether the callback throws an InvalidArgumentException, which a render wraps in a ViewException */
function throwsInvalidName(callable $render): bool
{
    try {
        $render();
    } catch (Throwable $e) {
        for (; $e; $e = $e->getPrevious()) {
            if ($e instanceof InvalidArgumentException) {
                return true;
            }
        }
    }

    return false;
}

const COLUMNS = '<atom:table.column checkbox hideable />'
    .'<atom:table.column name="name">Name</atom:table.column>'
    .'<atom:table.column name="email" hideable>Email</atom:table.column>'
    .'<atom:table.column name="phone" hideable hidden>Phone</atom:table.column>';

it('marks a named th and a linked td', function () {
    $html = columnsTable(COLUMNS);

    $email = domQuery($html, '//th[@data-atom-column="email"]')[0];
    expect($email)->not->toBeNull();
    expect(json_decode($email->getAttribute('data-atom-column-meta'), true))->toBe(['name' => 'email', 'label' => 'Email', 'hideable' => true, 'hidden' => false]);

    expect(domQuery($html, '//td[@data-atom-column="email"]'))->toHaveCount(1);
});

it('prints a source with the columns, in order, and none for a table that names none', function () {
    $source = columnSource(columnsTable(COLUMNS));

    expect(array_column(json_decode($source->getAttribute('data-columns'), true), 'name'))->toBe(['name', 'email', 'phone']);
    expect($source->hasAttribute('hidden'))->toBeTrue();

    $plain = columnsTable('<atom:table.column>Name</atom:table.column>');

    expect(columnSource($plain))->toBeNull();
    expect($plain)->not->toContain('data-atom-column-scope')->not->toContain('data-atom-column-css')->not->toContain('data-atom-column-hide');
});

it('marks the scroll box and lists the columns hidden by default on it', function () {
    $box = scrollBox(columnsTable(COLUMNS));

    expect($box->hasAttribute('data-atom-column-scope'))->toBeTrue();
    expect($box->getAttribute('data-atom-column-hide'))->toBe('phone');
});

it('prints exactly one rule per hideable column and none for a locked one', function () {
    $html = columnsTable(COLUMNS);

    preg_match('/<style data-atom-column-css>(.*?)<\/style>/s', $html, $style);
    $rules = explode("\n", $style[1]);

    expect($rules)->toBe([
        '[data-atom-column-hide~="email"] [data-atom-column="email"]{display:none!important}',
        '[data-atom-column-hide~="phone"] [data-atom-column="phone"]{display:none!important}',
    ]);
    expect(substr_count($html, '<style data-atom-column-css'))->toBe(1);
});

it('prints no stylesheet and no hide list when every named column is locked, but still the source', function () {
    $html = columnsTable('<atom:table.column name="email">Email</atom:table.column>');

    expect(columnSource($html))->not->toBeNull();
    expect($html)->not->toContain('data-atom-column-css')->not->toContain('data-atom-column-hide');
});

it('keeps hidden as the plain HTML attribute on a column that is not hideable', function () {
    $html = columnsTable('<atom:table.column name="locked" hidden>Locked</atom:table.column><atom:table.column hidden>Plain</atom:table.column>');

    expect(domQuery($html, '//th[@data-atom-column="locked"][@hidden]'))->toHaveCount(1);
    expect(domQuery($html, '//th[not(@data-atom-column)][@hidden]'))->toHaveCount(1);

    // and on a hideable one it is the default, not an attribute
    $hideable = columnsTable('<atom:table.column name="phone" hideable hidden>Phone</atom:table.column>');
    expect(domQuery($hideable, '//th[@data-atom-column="phone"][@hidden]'))->toHaveCount(0);
});

it('never lists a checkbox column, whatever it is given', function () {
    $html = columnsTable('<atom:table.column checkbox name="select" hideable hidden />'.'<atom:table.column name="email" hideable>Email</atom:table.column>');

    expect(array_column(json_decode(columnSource($html)->getAttribute('data-columns'), true), 'name'))->toBe(['email']);
    expect($html)->not->toContain('"select"');
    // a checkbox column's `hidden` is still the plain attribute
    expect(domQuery($html, '//th[@hidden]'))->toHaveCount(1);
});

it('does not link a checkbox cell', function () {
    $html = renderBlade('<atom:table.cell checkbox="1" column="email" />');

    expect($html)->not->toContain('data-atom-column');
});

it('takes the label from the header text, falling back to the name', function () {
    $html = columnsTable('<atom:table.column name="first_name" hideable>  <b>First</b>   &amp; last </atom:table.column><atom:table.column name="avatar-url" hideable><atom:icon.add /></atom:table.column>');

    $labels = array_column(json_decode(columnSource($html)->getAttribute('data-columns'), true), 'label', 'name');

    expect($labels)->toBe(['first_name' => 'First & last', 'avatar-url' => 'Avatar Url']);
});

it('round-trips a label with quotes, angle brackets, a script end tag and CJK through the payload', function () {
    $label = 'Q"uote\' <b>x</b> & </script></style> 姓名';
    $html = renderBlade('<atom:table :empty="false"><x-slot:columns><atom:table.column name="tricky" hideable>{{ $label }}</atom:table.column><atom:table.column name="after" hideable>After</atom:table.column></x-slot:columns><x-slot:rows></x-slot:rows></atom:table>', ['label' => $label]);

    $columns = json_decode(columnSource($html)->getAttribute('data-columns'), true);

    expect($columns[0]['label'])->toBe('Q"uote\' <b>x</b> & </script></style> 姓名');
    expect(array_column($columns, 'name'))->toBe(['tricky', 'after']);
    // the label is data only: no stray end tag reaches the markup outside an escaped attribute
    expect($html)->not->toContain('</script></style>');
});

it('throws for a name or a column outside the pattern, before anything is printed', function (mixed $name) {
    $render = fn () => renderBlade('<atom:table.column :name="$name" hideable>X</atom:table.column>', ['name' => $name]);
    $cell = fn () => renderBlade('<atom:table.cell :column="$name">X</atom:table.cell>', ['name' => $name]);

    expect(throwsInvalidName($render))->toBeTrue();
    expect(throwsInvalidName($cell))->toBeTrue();
})->with([
    'an end tag' => '</style><script>alert(1)</script>',
    'a quote' => 'a"b',
    'a bracket' => 'a]b',
    'a space' => 'a b',
    'a dot' => 'a.b',
    'a newline' => "a\nb",
    'empty' => '',
    'unicode' => 'prénom',
    'too long' => str_repeat('a', 65),
    'an array' => [['a']],
]);

it('accepts the longest name and every allowed character', function () {
    $name = str_repeat('a', 60).'_-9Z';
    $html = columnsTable('<atom:table.column name="'.$name.'" hideable>X</atom:table.column>');

    expect(strlen($name))->toBe(64);
    expect($html)->toContain('[data-atom-column-hide~="'.$name.'"]');
});

it('never lets a forged meta attribute into the stylesheet', function () {
    // markup a host wrote by hand rather than through the column component
    $payload = TableColumns::payload('<th data-atom-column-meta="'.e(json_encode(['name' => '</style>x', 'label' => 'a', 'hideable' => true])).'"></th>'
        .'<th data-atom-column-meta="not json"></th>'
        .'<th data-atom-column-meta="'.e(json_encode(['name' => 'ok', 'label' => 'Ok', 'hideable' => true])).'"></th>'
        .'<th data-atom-column-meta="'.e(json_encode(['name' => 'ok', 'label' => 'Again', 'hideable' => false])).'"></th>');

    expect(array_column($payload, 'name'))->toBe(['ok']);
    expect($payload[0]['label'])->toBe('Ok');
    expect(TableColumns::css($payload))->not->toContain('<');
});

it('prints the first-paint script only with a persist-key, byte for byte the same whatever the key', function () {
    $none = columnsTable(COLUMNS);
    $a = columnsTable(COLUMNS, 'persist-key="contacts"');
    $b = columnsTable(COLUMNS, 'persist-key="inv\'oices.&quot;7"');

    expect($none)->not->toContain('<script');

    $scriptOf = fn (string $html) => domQuery($html, '//*[@data-atom-table-scroll]/script')[0]->textContent;

    expect($scriptOf($a))->toBe(TableColumns::SCRIPT)->toBe($scriptOf($b));
    // the script is the first child of the scroll box, before the table it hides columns of
    expect(domQuery($a, '//*[@data-atom-table-scroll]/*[1][self::script]'))->toHaveCount(1);
    // the key is data on the scroll box and on the source, never in the script
    expect(scrollBox($a)->getAttribute('data-atom-column-key'))->toBe('contacts');
    expect(columnSource($a)->getAttribute('data-key'))->toBe('contacts');
    expect(TableColumns::SCRIPT)->not->toContain('contacts');
});

it('prints no script when the table has no hideable column, or the host gives the choices', function () {
    expect(columnsTable('<atom:table.column name="email">Email</atom:table.column>', 'persist-key="k"'))->not->toContain('<script');
    expect(columnsTable(COLUMNS, 'persist-key="k" :columns-state="[]"'))->not->toContain('<script');
});

it('carries the CSP nonce on the script when one is set, and none when it is not', function () {
    expect(columnsTable(COLUMNS, 'persist-key="k"'))->toContain('<script>(function (s)')->not->toContain('nonce');

    Vite::useCspNonce('abc123');

    $html = columnsTable(COLUMNS, 'persist-key="k"');
    $script = domQuery($html, '//*[@data-atom-table-scroll]/script')[0];

    expect($script->getAttribute('nonce'))->toBe('abc123');
    expect($script->textContent)->toBe(TableColumns::SCRIPT);
});

it('computes the hidden list from the host\'s columns-state, over the defaults', function () {
    // email hidden by the user, phone (hidden by default) shown by the user
    $html = columnsTable(COLUMNS, ':columns-state="[\'email\' => false, \'phone\' => true]"');

    expect(scrollBox($html)->getAttribute('data-atom-column-hide'))->toBe('email');
    expect(json_decode(columnSource($html)->getAttribute('data-state'), true))->toBe(['email' => false, 'phone' => true]);
});

it('drops what a host\'s columns-state cannot say, and ignores a locked column\'s choice', function () {
    $html = columnsTable(COLUMNS, ':columns-state="[\'name\' => false, \'</style>\' => false, \'email\' => \'no\', \'gone\' => false, \'phone\' => false]"');

    expect(scrollBox($html)->getAttribute('data-atom-column-hide'))->toBe('phone');
    expect(json_decode(columnSource($html)->getAttribute('data-state'), true))->toBe(['name' => false, 'gone' => false, 'phone' => false]);
    expect($html)->not->toContain('</style>x');

    // the empty state is an object, not a list
    expect(columnSource(columnsTable(COLUMNS, ':columns-state="[]"'))->getAttribute('data-state'))->toBe('{}');
    // no state given: no attribute
    expect(columnSource(columnsTable(COLUMNS))->hasAttribute('data-state'))->toBeFalse();
});

it('still lists the columns while the table is empty or in its skeleton', function () {
    $empty = renderBlade('<atom:table :empty="true"><x-slot:columns>'.COLUMNS.'</x-slot:columns></atom:table>');
    $skeleton = renderBlade('<atom:table skeleton><x-slot:columns>'.COLUMNS.'</x-slot:columns></atom:table>');

    foreach ([$empty, $skeleton] as $html) {
        expect(columnSource($html))->not->toBeNull();
        expect(scrollBox($html)->getAttribute('data-atom-column-hide'))->toBe('phone');
        expect($html)->not->toContain('<th');
    }
});

it('prints the source and the stylesheet before the first visible child of the table', function () {
    $html = columnsTable(COLUMNS);
    $root = domQuery($html, '//*[@data-atom-table]')[0];
    $order = [];

    foreach ($root->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $order[] = $child->hasAttribute('data-atom-column-source') ? 'source' : ($child->nodeName === 'style' ? 'style' : 'box');
        }
    }

    expect($order)->toBe(['source', 'style', 'box']);
});

it('leaves a table with no named column exactly as it was', function () {
    $html = columnsTable('<atom:table.column>Name</atom:table.column>', '', '<atom:table.row><atom:table.cell>a</atom:table.cell></atom:table.row>');

    expect($html)->not->toMatch('/data-atom-column(?!s)/')->not->toContain('x-init');
});
