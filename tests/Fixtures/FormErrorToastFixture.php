<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * Hosts <atom:form>'s error toast: several forms on one component (so the e2e can check
 * that only the submitter reacts), a modal form, a reCAPTCHA form, a form that a successful
 * save removes from the page, a form that never validates, one with seven failing fields, a
 * form with one of every atom control (all failing) and two forms that raise a field-less error.
 * `?error-toast=0`, `?disabled=1` and `?recaptcha=1` on the page flip the form props.
 */
class FormErrorToastFixture extends Component
{
    use AtomComponent;

    /** Every field of the "controls" form, which fails them all. */
    public const CONTROL_KEYS = [
        'selectListbox', 'selectNative', 'selectMultiple', 'dateSingle', 'dateRange', 'timePick',
        'tiptapEager', 'tiptapLazy', 'editorField', 'upload', 'agree', 'channels', 'plan', 'phone',
        'otp', 'mail', 'color', 'textField', 'toggled', 'volume', 'stars', 'notes',
    ];

    public ?string $name = null;

    public ?string $nickname = null;

    public ?string $email = null;

    public ?string $other = null;

    public ?string $modalField = null;

    public ?string $twin = null;

    public ?string $goneField = null;

    public bool $gone = false;

    public ?string $plain = null;

    /** @var array<string, ?string> */
    public array $rows = ['a' => null, 'b' => null, 'c' => null, 'd' => null, 'e' => null, 'f' => null, 'g' => null];

    public ?string $orphanField = null;

    // Livewire sends the browser only the errors keyed to a public property, so a field-less
    // error has to be keyed to one that no field renders
    public ?string $general = null;

    public ?string $orphanOnlyField = null;

    // one property per control on the "controls" form; CONTROL_KEYS lists them
    public ?string $selectListbox = null;

    public ?string $selectNative = null;

    /** @var array<int, string> */
    public array $selectMultiple = [];

    public ?string $dateSingle = null;

    public ?string $dateRange = null;

    public ?string $timePick = null;

    public ?string $tiptapEager = null;

    public ?string $tiptapLazy = null;

    public ?string $editorField = null;

    public $upload = null;

    public ?string $agree = null;

    /** @var array<int, string> */
    public array $channels = [];

    public ?string $plan = null;

    public ?string $phone = null;

    public ?string $otp = null;

    public ?string $mail = null;

    public ?string $color = null;

    public ?string $textField = null;

    public ?string $toggled = null;

    public ?string $volume = null;

    public ?string $stars = null;

    public ?string $notes = null;

    public bool $errorToast = true;

    public bool $disabled = false;

    public bool $recaptcha = false;

    public int $saves = 0;

    /**
     * Validate the first form; a pass raises the app's own "Saved" toast. The other two forms succeed quietly.
     */
    public function save(): void
    {
        $this->validate([
            'name' => 'required',
            'nickname' => 'required',
            'email' => 'required|email',
        ], [
            'name.required' => 'Name is required.',
            // the same sentence as `name`, to prove the toast lists each message once
            'nickname.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be valid.',
        ]);

        $this->saves++;
        $this->toast('Saved');
    }

    /**
     * Validate the second form on the same component.
     */
    public function saveOther(): void
    {
        // a quiet success: no toast of the app's own, so a closed error toast is a closed one
        $this->validate(['other' => 'required'], ['other.required' => 'Other is required.']);
    }

    /**
     * A form that validates nothing: it must not re-toast another form's errors.
     */
    public function savePlain(): void
    {
        $this->saves += 0;
    }

    /**
     * Validate a form that a pass removes from the page.
     */
    public function saveGone(): void
    {
        $this->validate(['goneField' => 'required'], ['goneField.required' => 'Gone field is required.']);

        $this->gone = true;
    }

    /**
     * Fail seven fields, each with its own message.
     */
    public function saveMany(): void
    {
        $rules = [];
        $messages = [];

        foreach (array_keys($this->rows) as $key) {
            $rules["rows.$key"] = 'required';
            $messages["rows.$key.required"] = "Row $key is required.";
        }

        $this->validate($rules, $messages);
    }

    /**
     * Fail one field of the form and one that no form renders.
     */
    public function saveOrphanWithField(): void
    {
        $this->addError('orphanField', 'Orphan field is required.');
        $this->addError('general', 'Something general went wrong.');
    }

    /**
     * Raise only an error that no form renders.
     */
    public function saveOrphanOnly(): void
    {
        $this->addError('general', 'Something general went wrong.');
    }

    /**
     * Fail every control on the "controls" form.
     */
    public function saveControls(): void
    {
        foreach (self::CONTROL_KEYS as $key) {
            $this->addError($key, "$key failed.");
        }
    }

    /**
     * An action that is not a form submit, run while errors from an earlier submit are
     * still on the component.
     */
    public function touch(): void
    {
        $this->saves += 0;
    }

    /**
     * The default `submit` method of the modal's <atom:form>.
     */
    public function submit(): void
    {
        $this->validate(['modalField' => 'required'], ['modalField.required' => 'Modal field is required.']);
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::form-error-toast');
    }
}
