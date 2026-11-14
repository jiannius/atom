<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

// Every row of buttons is spaced by gap-2 (8px), whatever draws it.
it('spaces each row of buttons by gap-2', function (string $blade, string $xpath) {
    $row = domQuery(renderBlade($blade), $xpath);

    expect($row)->not->toBeEmpty()
        ->and(domClasses($row[0]))->toContain('gap-2')->not->toContain('gap-3', 'gap-1.5', 'space-x-2');
})->with([
    'page header actions' => ['<atom:page.header title="T"><x-slot:primary><button>Go</button></x-slot:primary></atom:page.header>', '//*[@data-atom-page-actions]'],
    'table toolbar end group' => ['<atom:table.toolbar>x<x-slot:menu><atom:menu.item>A</atom:menu.item></x-slot:menu></atom:table.toolbar>', '//*[@data-atom-table-toolbar-end]'],
    'confirm dialog buttons' => ['<atom:confirm />', '//form/div[contains(@class, "justify-end")]'],
    'form actions' => ['<atom:form.actions><button>A</button><button>B</button></atom:form.actions>', '//*[@data-atom-form-actions]'],
    'form actions bar' => ['<atom:form.actions bar><button>A</button></atom:form.actions>', '//*[@data-atom-form-actions-buttons]'],
    'form modal actions' => ['<atom:form.modal name="m"><x-slot:actions><button>A</button></x-slot:actions></atom:form.modal>', '//*[@data-atom-form-modal-actions]'],
    'sidebar shell actions' => ['<atom:layouts.sidebar :vite="false"><x-slot:actions><button>A</button><button>B</button></x-slot:actions></atom:layouts.sidebar>', '//*[@data-atom-shell-actions]'],
    'header shell actions' => ['<atom:layouts.header :vite="false"><x-slot:actions><button>A</button><button>B</button></x-slot:actions></atom:layouts.header>', '//*[@data-atom-shell-actions]'],
    'button group with a gap' => ['<atom:button.group gap><button>A</button></atom:button.group>', '//*[@data-atom-button-group or contains(@class, "group/group")]'],
]);
