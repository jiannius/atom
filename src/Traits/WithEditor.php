<?php

namespace Jiannius\Atom\Traits;

use Livewire\WithFileUploads;

/**
 * Image upload for <atom:tiptap>: the toolbar uploads into $_editor.images and
 * gets temporary preview URLs back. Brings Livewire's WithFileUploads along.
 */
trait WithEditor
{
    use WithFileUploads;

    public $_editor = [
        'images' => [],
    ];

    /**
     * Echo an editor image upload back as temporary preview URLs
     */
    public function updatedWithEditor($property, $value)
    {
        // we generate a temporary preview url for editor images upload
        // upon persist the editor content to the model, it will then search all the temporary url
        // and store it in the disk (model need to use the AsEditorContent casts to the desired column)
        // in case the editor content is not persisted (eg, user decided not to save the content)
        // the temporary upload will just go through the normal purging process by livewire
        if ($property === '_editor.images') {
            $images = [];

            foreach ($value as $upload) {
                $images[] = $upload->temporaryUrl();
            }

            $this->fill(['_editor.images' => $images]);
        }
    }
}
