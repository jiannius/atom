<?php

namespace Jiannius\Atom\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Casts\Concerns\ReadsTemporaryUploads;
use Jiannius\Atom\Tiptap\Content;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

class AsEditorContent implements CastsAttributes
{
    use ReadsTemporaryUploads;

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_string($value)) {
            [$serialized, $unserialized] = Content::unserialize($value);

            if ($serialized) {
                return $unserialized;
            }
        }

        return $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Use regex to find all img tags with src containing "/livewire-{hash}/preview-file/"
        $tmps = [];

        if (is_string($value)) {
            if (preg_match_all('/<img\s[^>]*src=[\'"]([^\'"]*\/livewire-[^\/]+\/preview-file\/[^\'"\?]+(?:\?[^\'"]*)?)[\'"][^>]*>/i', $value, $matches)) {
                // $matches[1] contains the full URLs
                foreach ($matches[1] as $fullUrl) {
                    $tmps[] = $fullUrl;
                }
            }
        }

        $persisted = [];

        foreach ($tmps as $url) {
            $name = $this->temporaryName($url);

            if (! $name) {
                continue;
            }

            if (! array_key_exists($name, $persisted)) {
                $persisted[$name] = $this->persist($name);
            }

            if ($persisted[$name]) {
                $value = str($value)->replace($url, $persisted[$name])->toString();
            }
        }

        return $value ? serialize($value) : null;
    }

    /**
     * Persist one Livewire temporary upload (resized, jpeg, png, gif or webp
     * only) and return its URL, or null to leave the src as it is: the file is
     * missing, is not one of those types, or does not decode.
     */
    protected function persist(string $name): ?string
    {
        $copy = $this->localCopy($name);

        if (! $copy) {
            return null;
        }

        [$local, $mime] = $copy;

        try {
            $extension = static::IMAGE_EXTENSIONS[$mime] ?? null;
            $url = $extension ? $this->storeImage($local, $extension) : null;
        }
        finally {
            @unlink($local);
        }

        if ($url) {
            FileUploadConfiguration::storage()->delete([
                FileUploadConfiguration::path($name, false),
                FileUploadConfiguration::path($name.'.json', false),
            ]);
        }

        return $url;
    }

    /**
     * The disk this cast writes editor images to. The purge command reads it
     * from here so the two can never disagree.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(env('FILESYSTEM_DISK'));
    }

    /**
     * The name of the disk returned by disk(), for telling two disks apart.
     */
    public static function diskName(): string
    {
        return env('FILESYSTEM_DISK') ?: config('filesystems.default');
    }

    /**
     * The folder, on that disk, this cast writes editor images to.
     */
    public static function folder(): string
    {
        return collect([data_get(static::disk()->getConfig(), 'folder'), 'editor'])->filter()->join('/');
    }
}
