<?php

namespace Jiannius\Atom\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Tiptap\Content;

class AsEditorContent implements CastsAttributes
{
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

        foreach ($tmps as $url) {
            $base = head(explode('?', $url));
            $tmpname = str($base)->afterLast('/')->toString();
            $tmppath = storage_path('app/private/'.config('livewire.temporary_file_upload.directory').'/'.$tmpname);

            if (file_exists($tmppath)) {
                // resize image
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($tmppath);
                $image->scaleDown(width: 1000);
                $image->save(quality: 80);

                // save to disk
                $disk = static::disk();
                $folder = static::folder();
                $extension = pathinfo($tmppath, PATHINFO_EXTENSION);
                $filename = strtolower(str()->random(20)).'-'.time().'.'.$extension;
                $path = $disk->putFileAs($folder, $tmppath, $filename, 'public');
                $value = str($value)->replace($url, $disk->url($path))->toString();

                // delete temporary file
                unlink($tmppath);
            }
        }

        return $value ? serialize($value) : null;
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
