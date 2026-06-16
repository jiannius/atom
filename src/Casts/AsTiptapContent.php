<?php

namespace Jiannius\Atom\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AsTiptapContent implements CastsAttributes
{
    /**
     * Read a stored value. New rows are Tiptap JSON (returned as-is). Legacy
     * rows were stored by the v2 AsEditorContent cast as serialize()'d HTML —
     * unserialize them to the raw HTML string. No forced migration.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $unserialized = @unserialize($value);

        if ($unserialized !== false || $value === 'b:0;') {
            return $unserialized;
        }

        return $value;
    }

    /**
     * Prepare a value for storage. The editor sends a Tiptap JSON string; walk
     * its image nodes and persist any Livewire temporary uploads, then store
     * the (possibly rewritten) JSON. Returns null when empty.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (empty($value)) {
            return null;
        }

        if (! is_string($value)) {
            $value = json_encode($value);
        }

        $doc = json_decode($value, true);

        if (! is_array($doc)) {
            return $value;
        }

        $this->walkImages($doc, function (array &$node) use ($model, $key) {
            $src = $node['attrs']['src'] ?? null;

            if ($src && $this->isTemporaryUpload($src)) {
                $node['attrs']['src'] = $this->persist($model, $key, $src);
            }
        });

        return json_encode($doc);
    }

    /**
     * Recursively walk every image node in the document, mutating in place.
     */
    protected function walkImages(array &$node, callable $callback): void
    {
        if (($node['type'] ?? null) === 'image') {
            $callback($node);
        }

        if (! empty($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as &$child) {
                if (is_array($child)) {
                    $this->walkImages($child, $callback);
                }
            }
        }
    }

    /**
     * Is this src a Livewire temporary preview URL?
     */
    protected function isTemporaryUpload(string $src): bool
    {
        return (bool) preg_match('/\/livewire-[^\/]+\/preview-file\//', $src);
    }

    /**
     * Persist a temporary upload to permanent storage and return its URL.
     * A model may override persistence via tiptapStoreImage($tmpPath, $key).
     */
    protected function persist(Model $model, string $key, string $src): string
    {
        $base = head(explode('?', $src));
        $tmpname = str($base)->afterLast('/')->toString();
        $tmppath = storage_path('app/private/'.config('livewire.temporary_file_upload.directory').'/'.$tmpname);

        if (! file_exists($tmppath)) {
            return $src;
        }

        if (method_exists($model, 'tiptapStoreImage')) {
            $url = $model->tiptapStoreImage($tmppath, $key);
            @unlink($tmppath);

            return $url;
        }

        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        $image = $manager->read($tmppath);
        $image->scaleDown(width: 1000);
        $image->save(quality: 80);

        $disk = Storage::disk(config('atom.editor.disk') ?: config('filesystems.default'));
        $folder = collect([data_get($disk->getConfig(), 'folder'), 'editor'])->filter()->join('/');
        $extension = pathinfo($tmppath, PATHINFO_EXTENSION);
        $filename = strtolower(str()->random(20)).'-'.time().'.'.$extension;
        $path = $disk->putFileAs($folder, $tmppath, $filename, 'public');

        @unlink($tmppath);

        return $disk->url($path);
    }
}
