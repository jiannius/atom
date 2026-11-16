<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\CopyFormsHost;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The notes, severities and corners of the copy and form checks. What each finds is held by its fixture host
 * (DesignCheckFixturesTest); these hold what it says, what it reads and what it declines.
 */

describe('copy and form checks: metadata', function () {
    it('has the severity the plan gives each', function () {
        $rules = (new DesignCheck)->rules();

        expect($rules['status-as-badge']::SEVERITY)->toBe(DesignCheck::REVIEW)
            ->and($rules['empty-glyph']::SEVERITY)->toBe(DesignCheck::REVIEW)
            ->and($rules['danger-section']::SEVERITY)->toBe(DesignCheck::REVIEW)
            ->and(array_map(fn ($id) => $rules[$id]::SEVERITY, ['enum-title', 'title-case', 'create-verb', 'modal-title', 'modal-footer-padding', 'typed-optional']))->each->toBe(DesignCheck::INFO)
            ->and($rules['status-as-badge']::GROUP)->toBe('Copy and state')
            ->and($rules['typed-optional']::GROUP)->toBe('Forms and modals');
    });
});

describe('status-as-badge', function () {
    it('names where the badge sits', function () {
        $found = DesignHost::notes(<<<'BLADE'
        <atom:page.header title="a"><x-slot:status><atom:badge color="info">Open</atom:badge></x-slot:status></atom:page.header>
        <atom:badge :status="$order->status" />
        <atom:table.cell column="status"><atom:badge>x</atom:badge></atom:table.cell>
        BLADE, 'status-as-badge');

        expect($found[1])->toContain('`status` slot')
            ->and($found[2])->toContain('`:status="$order->status"`')
            ->and($found[3])->toContain('`status` column');
    });

    it('is one finding for a badge that is in a slot and also handed a status', function () {
        expect(DesignHost::scanSource('<atom:page.header title="a"><x-slot:status><atom:badge :status="$a->status" /></x-slot:status></atom:page.header>', 'status-as-badge'))->toHaveCount(1);
    });

    it('does not read a badge in a slot of another component, or inside a component within the header\'s status slot', function () {
        expect(DesignHost::scanSource('<x-card><x-slot:status><atom:badge>a</atom:badge></x-slot:status></x-card>', 'status-as-badge'))->toBe([])
            ->and(DesignHost::scanSource('<atom:page.header title="a"><x-slot:status><atom:tooltip><atom:badge>a</atom:badge></atom:tooltip></x-slot:status></atom:page.header>', 'status-as-badge'))->toBe([]);
    });

    it('is skipped on a view whose tags do not balance', function () {
        expect(DesignHost::scanSource('<atom:page.header title="a"><x-slot:status><atom:badge>a</atom:badge></x-slot:status>', 'status-as-badge'))->toBe([]);
    });
});

describe('empty-glyph', function () {
    it('reads the text clauses on a view whose tags do not balance, and the element clause only on a balanced one', function () {
        $open = "<atom:page>\n{{ \$a ?? '—' }}\n<atom:table.cell>-</atom:table.cell>\n";

        expect(array_column(DesignHost::scanSource($open, 'empty-glyph'), 'line'))->toBe([2])
            ->and(array_column(DesignHost::scanSource($open.'</atom:page>', 'empty-glyph'), 'line'))->toBe([2, 3]);
    });

    it('reads a lone hyphen only where it can only be a value', function () {
        $found = DesignHost::scanSource("{{ \$a ?: '-' }}\n@php \$b = \$c ?: '-'; @endphp\n<p>x ?? '-'</p>\n@php \$f = ['value' => \$g ?: '-']; @endphp\n", 'empty-glyph');

        expect(array_column($found, 'line'))->toBe([1, 4]);
    });

    it('does not read a PHP file that builds a mail, a PDF, a print or an export', function () {
        $php = "<?php\nclass A { function f() { return ['value' => '—', 'x' => \$a ?? '--']; } }\n";

        expect(CopyFormsHost::scan(['app/Pdf/A.php' => $php, 'app/Exports/A.php' => $php, 'app/Print/A.php' => $php, 'app/Support/MailBody.php' => str_replace('class A', 'class A extends Mailable', $php)], 'empty-glyph'))->toBe([])
            ->and(CopyFormsHost::scan(['app/Support/A.php' => $php], 'empty-glyph'))->toHaveCount(2);
    });

    it('names what was typed', function () {
        expect(DesignHost::notes("{{ \$a ?? '—' }}\n<atom:table.cell>-</atom:table.cell>\n<atom:dd filler=\"--\">a</atom:dd>\n", 'empty-glyph'))
            ->toBe([1 => 'a fallback to `—`', 2 => 'the only text is a dash', 3 => '`filler="--"` types the dash atom draws']);
    });
});

describe('enum-title', function () {
    it('is one finding for a wrapper and names what it reads', function () {
        $found = DesignHost::notes("{{ ucfirst(str_replace('_', ' ', \$a->status->name)) }}\n{{ str(\$b->type->value)->title() }}\n", 'enum-title');

        expect($found)->toHaveCount(2)
            ->and($found[1])->toContain('ucfirst(…->status->name)')
            ->and($found[2])->toContain('str(…->type->value)->title()');
    });

    it('reads a title only when it follows the str() call, not a title of something else on the line', function () {
        expect(DesignHost::scanSource("{{ str(\$a->status->name) }} {{ \$b->title() }}\n{{ \$x->title() . str(\$a->status->name) }}\n{{ str(\$a->status->name)->slug() }}\n", 'enum-title'))->toBe([]);
    });

    it('does not read a PHP file under a mail or PDF directory', function () {
        $php = "<?php\nclass A { function f() { return Str::title(\$this->order->status->name); } }\n";

        expect(CopyFormsHost::scan(['app/Mail/A.php' => $php, 'app/Pdf/A.php' => $php], 'enum-title'))->toBe([])
            ->and(CopyFormsHost::scan(['app/Livewire/A.php' => $php], 'enum-title'))->toHaveCount(1);
    });
});

describe('title-case', function () {
    it('keeps a one-word candidate only for a word that another label writes in lowercase', function () {
        $views = fn ($other) => ['resources/views/a.blade.php' => '<atom:button>Use Template</atom:button>', 'resources/views/b.blade.php' => $other];

        expect(CopyFormsHost::scan($views('<atom:button>Save as template</atom:button>'), 'title-case'))->toHaveCount(1)
            ->and(CopyFormsHost::scan($views('<atom:button>Save as layout</atom:button>'), 'title-case'))->toBe([])
            ->and(CopyFormsHost::scan($views('<atom:button>Save as Template</atom:button>'), 'title-case'))->toBe([]);
    });

    it('does not take a label\'s own lowercase word as the evidence', function () {
        expect(DesignHost::scanSource('<atom:button>Use Template or template</atom:button>', 'title-case'))->toBe([]);
    });

    it('takes the lowercase word from a PHP toast too', function () {
        expect(CopyFormsHost::scan([
            'resources/views/a.blade.php' => '<atom:button>Use Template</atom:button>',
            'app/Livewire/A.php' => "<?php\nclass A { function f() { \$this->toast('Saved the template'); } }\n",
        ], 'title-case'))->toHaveCount(1);
    });

    it('does not count a word that follows a sentence break, a bracket, a quote or a dash', function () {
        $labels = ['Open: Overdue Invoices', 'Notes (Optional Fields)', 'Export “Annual Report” now', 'Show — Overdue Invoices', 'Done. Send Invoices'];
        $found = DesignHost::scanSource(implode("\n", array_map(fn ($label) => "<atom:button>{$label}</atom:button>", $labels)), 'title-case');

        expect(array_column($found, 'line'))->toBe([]);
    });

    it('does not count acronyms, mixed case, words with a digit, months, weekdays or a possessive', function () {
        $labels = ['Pay SST Now', 'Export MyInvois Data', 'Print A4 Pages', 'Due Monday March', 'Show Acme\'s Invoices', 'Sync WhatsApp Chats'];

        expect(DesignHost::scanSource(implode("\n", array_map(fn ($label) => "<atom:button>{$label}</atom:button>", $labels)), 'title-case'))->toBe([]);
    });

    it('does not read the label of a tag that holds a slot, or one that is bound or echoed', function () {
        expect(DesignHost::scanSource("<atom:menu.item><x-slot:end>Ctrl K</x-slot:end>Open Orders</atom:menu.item>\n<atom:button>{{ \$a }} Open Orders</atom:button>\n<atom:input :label=\"\$a ? 'Open Orders' : 'x'\" />\n", 'title-case'))->toBe([]);
    });

    it('reads the text arguments of toast and confirm, the first positional and the named ones', function () {
        $php = "<?php\nclass A { function f() {\n\$this->toast('Saved the Invoice', 'success', 'Big Heading');\n\$this->confirm(message: 'Remove This Row', heading: 'Remove Row');\n\$this->notify('Remove This Row');\n} }\n";
        $found = CopyFormsHost::scan(['app/Livewire/A.php' => $php], 'title-case');

        expect(array_column($found, 0))->toBe(['app/Livewire/A.php:4']);
    });
});

describe('create-verb', function () {
    it('names the label', function () {
        expect(DesignHost::notes("<atom:button>New invoice</atom:button>\n<atom:table/><atom:page.header title=\"a\"><x-slot:primary><atom:button>Add customer</atom:button></x-slot:primary></atom:page.header>\n", 'create-verb'))
            ->toBe([1 => '"New invoice": a new record is "Create …"', 2 => '"Add customer" in the list header\'s primary slot: a new record is "Create …"']);
    });

    it('reads "Add" only in a view that has a table', function () {
        $header = '<atom:page.header title="a"><x-slot:primary><atom:button>Add customer</atom:button></x-slot:primary></atom:page.header>';

        expect(DesignHost::scanSource($header, 'create-verb'))->toBe([])
            ->and(DesignHost::scanSource($header.'<atom:table />', 'create-verb'))->toHaveCount(1);
    });

    it('does not read PHP', function () {
        expect((new DesignCheck)->rules()['create-verb']->kinds())->toBe(['blade'])
            ->and(CopyFormsHost::scan(['app/Mail/A.php' => "<?php\n\$this->toast('New invoice');\n"], 'create-verb'))->toBe([]);
    });
});

describe('modal-title', function () {
    it('names the hand heading', function () {
        expect(DesignHost::notes("<atom:modal><atom:heading>a</atom:heading><atom:form/></atom:modal>\n<atom:modal><h2>a</h2><atom:form/></atom:modal>", 'modal-title'))
            ->toBe([1 => 'the modal\'s first heading is `<atom:heading>`: use `<atom:page.header level="2" size="sm">`', 2 => 'the modal\'s first heading is `<h2>`: use `<atom:page.header level="2" size="sm">`']);
    });

    it('stops at the first heading: a later hand heading after the page.header is not read', function () {
        expect(DesignHost::scanSource('<atom:modal><atom:page.header title="a" level="2" size="sm"/><atom:heading>b</atom:heading><atom:form/></atom:modal>', 'modal-title'))->toBe([]);
    });
});

describe('modal-footer-padding', function () {
    it('names the class', function () {
        expect(DesignHost::notes('<atom:form.actions class="gap-2 p-6">a</atom:form.actions>', 'modal-footer-padding'))->toBe([1 => '`p-6`: use `sticky` (a modal or slide-over) or `bar` (a page)']);
    });

    it('reads every padding utility, behind a variant or an important mark, and no other class', function () {
        $found = fn (string $class) => DesignHost::scanSource('<atom:form.actions class="'.$class.'">a</atom:form.actions>', 'modal-footer-padding') !== [];

        foreach (['p-4', 'px-2', 'py-2', 'pt-1', 'pb-1', 'ps-1', 'pe-1', 'pl-1', 'pr-1', 'md:p-4', '!p-4', 'p-4!', 'p-[1rem]', 'hover:md:px-3', 'p-0.5'] as $class) {
            expect($found($class))->toBeTrue($class);
        }

        foreach (['p-0', 'px-0', 'top-4', 'gap-4', 'max-w-lg', 'pointer-events-none', 'place-items-center', 'mt-4', 'flex'] as $class) {
            expect($found($class))->toBeFalse($class);
        }
    });
});

describe('danger-section', function () {
    it('names why', function () {
        $section = fn ($variant) => '<atom:form.section variant="'.$variant.'">a</atom:form.section>';

        expect(DesignHost::notes('<atom:form>'.$section('danger')."\n".$section('plain').'</atom:form>', 'danger-section'))->toBe([1 => 'followed by another section: the danger section goes last'])
            ->and(DesignHost::notes('<atom:form>'.$section('danger')."\n".$section('danger').'</atom:form>', 'danger-section'))->toBe([1 => 'followed by another section: the danger section goes last', 2 => 'a second danger section in the form: keep one, last']);
    });

    it('does not compare sections in two forms, or in the branches of one @if', function () {
        $danger = '<atom:form.section variant="danger">a</atom:form.section>';

        expect(DesignHost::scanSource("<atom:form>{$danger}</atom:form><atom:form>{$danger}<atom:form.section>b</atom:form.section></atom:form>", 'danger-section'))->toHaveCount(1)
            ->and(DesignHost::scanSource("<atom:form>@if (\$a) {$danger} @else {$danger} @endif</atom:form>", 'danger-section'))->toBe([]);
    });

    it('does not read a bound variant', function () {
        expect(DesignHost::scanSource('<atom:form><atom:form.section :variant="$v">a</atom:form.section><atom:form.section>b</atom:form.section></atom:form>', 'danger-section'))->toBe([]);
    });
});

describe('typed-optional', function () {
    it('quotes the label', function () {
        expect(DesignHost::notes('<atom:input label="Notes (optional)" />', 'typed-optional'))->toBe([1 => '"Notes (optional)": use `<atom:form marker="optional">` and `required` on the others']);
    });
});
