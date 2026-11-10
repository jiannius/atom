<atom:docs.example
title="Basic"
description="Actual uploads go through Livewire's WithFileUploads (included in WithEditor) — bind with wire:model. This preview renders the trigger UI only."
view="atom::docs.demos.uploader.basic"/>

<atom:docs.example
title="Dropzone"
description="A drag-and-drop drop target. Drop files onto it or click to browse — dropped files are handed to the same wire:model-bound input. This preview renders the surface only."
view="atom::docs.demos.uploader.dropzone"/>

<atom:docs.example
title="Hint beside the button"
description="The default slot replaces the upload button; the end slot adds content beside it (a size or type hint) and keeps the button. The dropzone has the same slot, under the zone, and so does a file input (atom:input type=file)."
view="atom::docs.demos.uploader.end"/>

<atom:docs.example
title="Disabled"
description="A disabled uploader opens no file chooser, from its button or from a trigger in the slot, and a disabled dropzone ignores a drop. Both follow a disabled atom:form."
view="atom::docs.demos.uploader.disabled"/>
