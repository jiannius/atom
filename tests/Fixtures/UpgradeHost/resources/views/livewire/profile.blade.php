<?php

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

new class extends Component {
    use AtomComponent;

    public $name;
};
?>

<atom:html editor>
    <atom:form recaptcha>
        <atom:editor wire:model="bio" />
        <atom:editor.chat />
        <atom:editor.content>Some <b>static</b> prose</atom:editor.content>
        <atom:tiptap.content :content="$bio" />
        <x-atom::editor.content :content="$bio" />
    </atom:form>
</atom:html>
