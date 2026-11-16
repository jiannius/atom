<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The notes and messages of the tone and confirm checks. What each finds is held by its fixture host (DesignCheckFixturesTest);
 * these hold what it says, and the corners a fixture line cannot show.
 */

describe('tone-missing', function () {
    it('says which tone the verb suggests, and what to write for an item or a button', function () {
        expect(DesignHost::notes("<atom:button>Cancel order</atom:button>\n<atom:menu.item>Archive</atom:menu.item>\n<atom:button>Delete draft</atom:button>\n<atom:menu.item>Purge</atom:menu.item>\n", 'tone-missing'))
            ->toBe([
                1 => '"Cancel order" is a warning: `variant="warning" inverted` with `confirm`',
                2 => '"Archive" is a warning: `variant="warning"` with `confirm`',
                3 => '"Delete draft" is a danger: `type="delete"`',
                4 => '"Purge" is a danger: `variant="delete"`',
            ]);
    });

    it('names the delete icon on an item, with any label', function () {
        expect(DesignHost::notes("<atom:menu.item icon=\"delete\">Move</atom:menu.item>\n<atom:button icon=\"delete\">Move</atom:button>\n", 'tone-missing'))
            ->toBe([1 => 'an item with the delete icon is a danger: `variant="delete"`']);
    });

    it('matches the verb as the first whole word of the visible label, in any case and with punctuation after it', function () {
        expect(array_keys(DesignHost::notes("<atom:button>DELETE</atom:button>\n<atom:button>Delete…</atom:button>\n<atom:button>delete?</atom:button>\n<atom:button>Cancellation policy</atom:button>\n<atom:button>Order cancel</atom:button>\n<atom:button>Deletes</atom:button>\n", 'tone-missing')))->toBe([1, 2, 3]);
    });

    it('does not flag a lone Cancel, a Cancel of an edit, or Reset of the screen', function (string $label) {
        expect(DesignHost::notes('<atom:button>'.$label.'</atom:button>', 'tone-missing'))->toBe([]);
    })->with(['Cancel', 'cancel', 'Cancel edit', 'Cancel changes', 'Cancel search', 'Reset filters', 'Reset search', 'Reset form', 'Reset view', 'Reset zoom', 'Reset password', 'Remove filter', 'Discard changes']);

    it('flags a Cancel of something and a lone Reset in a menu', function () {
        expect(array_keys(DesignHost::notes("<atom:button>Cancel order</atom:button>\n<atom:menu.item>Reset</atom:menu.item>\n<atom:button>Cancel subscription</atom:button>\n", 'tone-missing')))->toBe([1, 2, 3]);
    });

    it('does not flag a lone Reset or Discard where the form is, but does elsewhere', function () {
        expect(DesignHost::notes('<atom:form><atom:button>Reset</atom:button><atom:button>Discard</atom:button></atom:form>', 'tone-missing'))->toBe([])
            ->and(DesignHost::notes('<atom:modal name="a"><atom:button>Discard</atom:button></atom:modal>', 'tone-missing'))->toBe([])
            ->and(DesignHost::notes('<atom:table.toolbar><atom:button>Reset</atom:button></atom:table.toolbar>', 'tone-missing'))->toBe([])
            ->and(array_keys(DesignHost::notes('<atom:card><atom:button>Discard</atom:button></atom:card>', 'tone-missing')))->toBe([1]);
    });

    it('does not flag a Remove beside a form control, whatever the control, and flags one beside none', function () {
        foreach (['<atom:input wire:model="a" />', '<atom:select.native wire:model="a" />', '<atom:date-picker wire:model="a" />', '<input name="a">', '<textarea></textarea>'] as $control) {
            expect(DesignHost::notes('<div>'.$control.'<atom:button>Remove</atom:button></div>', 'tone-missing'))->toBe([]);
        }

        expect(array_keys(DesignHost::notes('<div><atom:button>Remove</atom:button><atom:badge>New</atom:badge></div>', 'tone-missing')))->toBe([1])
            ->and(array_keys(DesignHost::notes('<atom:page><atom:button>Remove</atom:button><div><atom:input wire:model="a" /></div></atom:page>', 'tone-missing')))->toBe([1]);
    });

    it('does not flag what it cannot read', function () {
        expect(DesignHost::notes('<atom:button>{{ $verb }}</atom:button><atom:button>Delete {{ $name }}</atom:button><atom:button :variant="$v">Delete</atom:button><atom:menu.item :variant="$v">Delete</atom:menu.item><atom:button :type="$t">Delete</atom:button><atom:button></atom:button>', 'tone-missing'))->toBe([]);
    });

    it('reads a tone from a ghost button\'s color, and any variant that is not plain as a choice', function () {
        expect(DesignHost::notes("<atom:button variant=\"ghost\" color=\"danger\">Delete</atom:button>\n<atom:button variant=\"ghost\" color=\"error\">Delete</atom:button>\n<atom:button variant=\"ghost\" color=\"warning\">Archive</atom:button>\n<atom:button variant=\"primary\">Archive</atom:button>\n<atom:button variant=\"success\">Archive</atom:button>\n<atom:button type=\"submit\">Archive</atom:button>\n<atom:button variant=\"nonsense\">Archive</atom:button>\n", 'tone-missing'))->toBe([]);
    });

    it('is a review, and a structural check', function () {
        $rule = (new DesignCheck)->rules()['tone-missing'];

        expect($rule->structural())->toBeTrue()->and($rule::SEVERITY)->toBe(DesignCheck::REVIEW)->and($rule::GROUP)->toBe('Tone and confirm');
    });
});

describe('tone-unconfirmed', function () {
    /**
     * What tone-unconfirmed finds in a host of these files, as file:line
     */
    $scan = function (array $files): array {
        $dir = DesignHost::temp($files);

        try {
            $result = (new DesignCheck)->scan($dir, ['tone-unconfirmed']);

            return array_map(fn ($finding) => $finding['file'].':'.$finding['line'], $result['checks']['tone-unconfirmed']['findings']);
        } finally {
            DesignHost::remove($dir);
        }
    };

    $item = '<atom:menu.item variant="warning" wire:click="suspend">Suspend</atom:menu.item>';

    it('names the tone and what to add', function () {
        expect(DesignHost::notes("<atom:menu.item variant=\"warning\" wire:click=\"a\">A</atom:menu.item>\n<atom:button variant=\"danger\" wire:click=\"b\">B</atom:button>\n<atom:menu.item variant=\"remove\" wire:click=\"c\">C</atom:menu.item>\n", 'tone-unconfirmed'))
            ->toBe([
                1 => '`variant="warning"` acts without asking: add `confirm`',
                2 => '`variant="danger"` acts without asking: add `confirm`',
                3 => '`variant="danger"` acts without asking: add `confirm`',
            ]);
    });

    it('drops a finding whose method calls confirm in a PHP class, whichever receiver and wherever the class is', function () use ($scan, $item) {
        $view = ['resources/views/a.blade.php' => $item];

        expect($scan($view))->toBe(['resources/views/a.blade.php:1'])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { \$this->confirm(heading: 'x'); } }"]))->toBe([])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { atom()->confirm(heading: 'x'); } }"]))->toBe([])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { app('atom')?->confirm(heading: 'x'); } }"]))->toBe([])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function Suspend() { if (\$a) { foreach (\$b as \$c) { \$this->confirm(heading: \"{\$c}\"); } } } }"]))->toBe([])
            ->and($scan($view + ['modules/Billing/app/Livewire/A.php' => "<?php\nclass A { public function suspend() { \$this->confirm(heading: 'x'); } }"]))->toBe([]);
    });

    it('keeps a finding whose method is another one, one that does not call confirm, or one that only names it', function () use ($scan, $item) {
        $view = ['resources/views/a.blade.php' => $item];

        expect($scan($view + ['app/A.php' => "<?php\nclass A { public function resume() { \$this->confirm(heading: 'x'); } }"]))->toBe(['resources/views/a.blade.php:1'])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { \$this->toast('confirm'); } public function other() { \$this->confirm(heading: 'x'); } }"]))->toBe(['resources/views/a.blade.php:1'])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { \$this->confirmation(heading: 'x'); } }"]))->toBe(['resources/views/a.blade.php:1'])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { // \$this->confirm(heading: 'x')\n } }"]))->toBe(['resources/views/a.blade.php:1'])
            ->and($scan($view + ['app/A.php' => "<?php\nclass A { public function suspend() { \$this->toast('x'); } public function other() { \$this->confirm(heading: 'x'); } }"]))->toBe(['resources/views/a.blade.php:1']);
    });

    it('reads the methods of a single-file Livewire component, and not a Blade file with no PHP in it', function () use ($scan, $item) {
        $component = "<?php\nnew class extends Component { public function suspend() { \$this->confirm(heading: 'x'); } };\n?>\n".$item;

        expect($scan(['resources/views/livewire/a.blade.php' => $component]))->toBe([])
            ->and($scan(['resources/views/a.blade.php' => $item, 'resources/views/b.blade.php' => "<p>function suspend() { \$this->confirm() }</p>"]))->toBe(['resources/views/a.blade.php:1']);
    });

    it('reads a method the click calls through $wire, a parent or a modifier', function () use ($scan) {
        $php = ['app/A.php' => "<?php\nclass A { public function suspend() { \$this->confirm(heading: 'x'); } }"];

        expect($scan(['resources/views/a.blade.php' => '<atom:button variant="danger" x-on:click="$wire.suspend()">A</atom:button><atom:button variant="danger" wire:click.prevent="$parent.suspend">B</atom:button>'] + $php))->toBe([])
            ->and($scan(['resources/views/a.blade.php' => '<atom:button variant="danger" x-on:click="$wire.other()">A</atom:button><atom:button variant="danger" @click="$wire.suspend(); $wire.other()">B</atom:button>'] + $php))->toBe(['resources/views/a.blade.php:1']);
    });

    it('asks nothing of a click that does not call a Livewire method', function () {
        expect(DesignHost::notes("<atom:button variant=\"danger\" x-on:click=\"open = true\">A</atom:button>\n<atom:button variant=\"danger\">B</atom:button>\n<atom:button variant=\"danger\" wire:click=\"{{ \$m }}\">C</atom:button>\n<atom:button variant=\"danger\" x-on:click=\"window.confirm('Sure?') && \$wire.go()\">D</atom:button>\n", 'tone-unconfirmed'))->toBe([]);
    });

    it('reads PHP and Blade, and is a structural review', function () {
        $rule = (new DesignCheck)->rules()['tone-unconfirmed'];

        expect($rule->kinds())->toBe(['blade', 'php'])->and($rule->structural())->toBeTrue()->and($rule::SEVERITY)->toBe(DesignCheck::REVIEW);
    });
});

describe('confirm-tone', function () {
    it('names both tones', function () {
        expect(DesignHost::notes('<atom:confirm.trigger heading="a" button-confirm="b"><atom:menu.item variant="warning">A</atom:menu.item></atom:confirm.trigger>', 'confirm-tone'))
            ->toBe([1 => 'the dialog is accent and the item is warning: drop the wrapper and use `confirm`'])
            ->and(DesignHost::notes('<atom:confirm.trigger variant="warning" heading="a" button-confirm="b"><atom:button type="delete">A</atom:button></atom:confirm.trigger>', 'confirm-tone'))
            ->toBe([1 => 'the dialog is warning and the button is danger: drop the wrapper and use `confirm`']);
    });

    it('reads an element nested in markup of its own, and not a second one beside it', function () {
        expect(array_keys(DesignHost::notes('<atom:confirm.trigger heading="a" button-confirm="b"><div><atom:button variant="warning">A</atom:button></div></atom:confirm.trigger>', 'confirm-tone')))->toBe([1])
            ->and(DesignHost::notes('<atom:confirm.trigger heading="a" button-confirm="b"><atom:button variant="warning">A</atom:button><atom:button>B</atom:button></atom:confirm.trigger>', 'confirm-tone'))->toBe([])
            ->and(DesignHost::notes('<atom:confirm.trigger heading="a" button-confirm="b"><x-host.action variant="warning" /></atom:confirm.trigger>', 'confirm-tone'))->toBe([])
            ->and(DesignHost::notes('<atom:confirm.trigger heading="a" button-confirm="b"><button type="button"><atom:icon.warning /></button></atom:confirm.trigger>', 'confirm-tone'))->toBe([]);
    });

    it('takes an unknown trigger variant for the accent dialog, as the dialog does', function () {
        expect(DesignHost::notes('<atom:confirm.trigger variant="info" heading="a" button-confirm="b"><atom:button variant="warning">A</atom:button></atom:confirm.trigger>', 'confirm-tone'))
            ->toBe([1 => 'the dialog is accent and the button is warning: drop the wrapper and use `confirm`']);
    });

    it('is a review', function () {
        expect((new DesignCheck)->rules()['confirm-tone']::SEVERITY)->toBe(DesignCheck::REVIEW);
    });
});

describe('confirm-copy', function () {
    it('says what is wrong, one note for each tag', function () {
        expect(DesignHost::notes("<atom:confirm.trigger heading=\"a\"><atom:link>A</atom:link></atom:confirm.trigger>\n<atom:confirm.trigger heading=\"a\" button-confirm=\"Yes\"><atom:link>A</atom:link></atom:confirm.trigger>\n<atom:button x-on:click=\"atom.confirm({ heading: 'a' })\">A</atom:button>\n<atom:confirm.trigger heading=\"a\" message=\"It CANNOT be undone\" button-confirm=\"Undo\"><atom:link>A</atom:link></atom:confirm.trigger>\n", 'confirm-copy'))
            ->toBe([
                1 => 'a confirm trigger with no `button-confirm`: its button reads "Confirm"',
                2 => 'the confirm button says "Yes": repeat the verb ("Archive")',
                3 => 'a confirm with no `buttonConfirm`: its button reads "Confirm"',
                4 => 'the confirm text has an all-caps word ("CANNOT"): write it in sentence case',
            ]);
    });

    it('reports the first problem of a tag only: a generic button before a missing one before a shout', function () {
        expect(DesignHost::notes('<atom:confirm.trigger heading="A THING" message="NEVER" button-confirm="OK"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))
            ->toBe([1 => 'the confirm button says "OK": repeat the verb ("Archive")'])
            ->and(DesignHost::notes('<atom:confirm.trigger heading="A THING"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))
            ->toBe([1 => 'a confirm trigger with no `button-confirm`: its button reads "Confirm"']);
    });

    it('takes a button text of Confirm, Yes, OK, Okay, Proceed or Continue, in any case and with punctuation', function (string $text, bool $flagged) {
        $notes = DesignHost::notes('<atom:confirm.trigger heading="a" button-confirm="'.$text.'"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy');

        expect($notes !== [])->toBe($flagged);
    })->with([['Confirm', true], ['yes', true], ['OK', true], ['Okay', true], ['PROCEED', true], ['Continue.', true], ['Yes!', true], ['Confirm deletion', false], ['Continue editing', false], ['Archive', false], ['Okayama', false]]);

    it('reads the four places a button text is written, and a translated one', function () {
        $found = fn (string $source) => DesignHost::notes($source, 'confirm-copy') !== [];

        expect($found("<atom:menu.item :confirm=\"['buttonConfirm' => 'Yes']\">A</atom:menu.item>"))->toBeTrue()
            ->and($found("<atom:menu.item :confirm=\"['buttonConfirm' => __('Yes')]\">A</atom:menu.item>"))->toBeTrue()
            ->and($found("<atom:button x-on:click=\"atom.confirm({ heading: 'a', buttonConfirm: 'OK' })\">A</atom:button>"))->toBeTrue()
            ->and($found("<script>atom.confirm({ heading: 'a', buttonConfirm: 'OK' })</script>"))->toBeTrue()
            ->and($found("@php \$this->confirm(heading: 'a', buttonConfirm: 'Yes') @endphp"))->toBeTrue()
            ->and($found("<atom:menu.item :confirm=\"['buttonConfirm' => \$verb]\">A</atom:menu.item>"))->toBeFalse()
            ->and($found("<atom:menu.item :confirm=\"['buttonConfirm' => 'Yes please']\">A</atom:menu.item>"))->toBeFalse();
    });

    it('does not read a confirm call it cannot see into, or an artisan command', function () {
        $dir = DesignHost::temp([
            'app/Console/Commands/A.php' => "<?php\nclass A { function handle() { \$this->confirm('Proceed?'); } }",
            'app/Jobs/B.php' => "<?php\nuse Illuminate\\Console\\Command;\nclass B extends Command { function handle() { \$this->confirm('Proceed?'); } }",
            'app/Livewire/C.php' => "<?php\nclass C { function a(\$o) { \$this->confirm(\$o); \$this->confirm(...\$o); \$this->confirm(heading: 'x', ...\$o); } }",
            'app/Livewire/D.php' => "<?php\nclass D { function a() { \$this->confirm(heading: 'Archive?'); } }",
        ]);

        try {
            $result = (new DesignCheck)->scan($dir, ['confirm-copy']);

            expect(array_column($result['checks']['confirm-copy']['findings'], 'file'))->toBe(['app/Livewire/D.php']);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('does not say a trigger has no button text when it spreads attributes or binds the text', function () {
        expect(DesignHost::notes('<atom:confirm.trigger {{ $attributes }} heading="a"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))->toBe([])
            ->and(DesignHost::notes('<atom:confirm.trigger :button-confirm="$verb" heading="a"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))->toBe([])
            ->and(DesignHost::notes('<atom:confirm.trigger heading="a"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))->toHaveCount(1);
    });

    it('knows an acronym that is written in capitals on purpose', function () {
        expect(DesignHost::notes('<atom:confirm.trigger heading="Reset the SMTP settings?" message="The HTML and JSON stay." button-confirm="Reset"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))->toBe([])
            ->and(DesignHost::notes('<atom:confirm.trigger heading="Reset the SMTP settings?" message="Do not, ever, EXPORT it." button-confirm="Reset"><atom:link>A</atom:link></atom:confirm.trigger>', 'confirm-copy'))->toHaveCount(1);
    });

    it('is an info, reads PHP and Blade and runs on a view read as text only', function () {
        $rule = (new DesignCheck)->rules()['confirm-copy'];

        expect($rule->kinds())->toBe(['blade', 'php'])->and($rule->structural())->toBeFalse()->and($rule::SEVERITY)->toBe(DesignCheck::INFO);
    });
});

describe('one-red', function () {
    it('says which button it is', function () {
        expect(DesignHost::notes("<atom:button variant=\"danger\">A</atom:button>\n<atom:button variant=\"ghost\" color=\"danger\">B</atom:button>\n<atom:button variant=\"ghost\" class=\"text-danger-fg\">C</atom:button>\n", 'one-red'))
            ->toBe([
                1 => '`variant="danger"` is the solid fill: use `type="delete"` or `inverted`',
                2 => 'a ghost with `color="danger"`: use `type="delete"` or `variant="danger" inverted`',
                3 => 'a ghost in a red text class: use `type="delete"` or `variant="danger" inverted`',
            ]);
    });

    it('is one finding for a button whichever way it is red', function () {
        expect(DesignHost::notes('<atom:button variant="ghost" color="danger" class="text-danger-fg">A</atom:button>', 'one-red'))->toHaveCount(1);
    });

    it('does not flag a delete, the tint, or a button in a dialog of its own, but does flag a ghost there', function () {
        expect(DesignHost::notes('<atom:modal name="a"><atom:button variant="danger">A</atom:button></atom:modal>', 'one-red'))->toBe([])
            ->and(DesignHost::notes('<atom:modal name="a"><atom:button variant="ghost" color="danger">A</atom:button></atom:modal>', 'one-red'))->toHaveCount(1)
            ->and(DesignHost::notes('<atom:button variant="danger" inverted>A</atom:button><atom:button variant="danger" type="delete">B</atom:button><atom:button variant="danger" :inverted="false">C</atom:button>', 'one-red'))->toBe([]);
    });

    it('is a review, and a structural check', function () {
        $rule = (new DesignCheck)->rules()['one-red'];

        expect($rule->structural())->toBeTrue()->and($rule::SEVERITY)->toBe(DesignCheck::REVIEW);
    });
});

describe('the tone and confirm group', function () {
    it('sits in its own group, with the five checks', function () {
        $group = array_keys(array_filter((new DesignCheck)->rules(), fn ($rule) => $rule::GROUP === 'Tone and confirm'));

        expect($group)->toContain('tone-missing', 'tone-unconfirmed', 'confirm-tone', 'confirm-copy', 'one-red');
    });
});
