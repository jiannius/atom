<?php

namespace Jiannius\Atom\Casts\Concerns;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\Mime\MimeTypes;

/**
 * How the editor casts read a Livewire temporary upload named by a src that
 * came from the browser. The casts must define disk() and folder().
 */
trait ReadsTemporaryUploads
{
    /**
     * The image types a temporary upload is persisted as, by detected mime type.
     */
    protected const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * The name of the Livewire temporary file a src points at, or null when the
     * src is not a preview URL for a file Livewire itself would have named. The
     * src comes from the browser, so the last URL segment is only ever used as a
     * name inside Livewire's temporary directory, and only when it has the shape
     * Livewire generates (a random token, an optional "-meta<base64>-" part and
     * an extension, which may be empty or odd when it came from the client): a
     * "..", an empty name, a separator or any other file's name is refused here.
     */
    protected function temporaryName(string $src): ?string
    {
        $path = preg_split('/[?#]/', $src, 2)[0];
        $name = rawurldecode(substr($path, (int) strrpos($path, '/') + 1));

        return preg_match('/^[A-Za-z0-9]{20,}(?:-meta[A-Za-z0-9+_=]*-)?\.[A-Za-z0-9_-]{0,20}$/D', $name) ? $name : null;
    }

    /**
     * Copy a Livewire temporary file to a local file with an extension taken
     * from its content, so it is read through Livewire's own disk and directory
     * (a non-default or remote temp disk works) and never from a path the client
     * chose. Returns null when there is no such file. On a local disk the file
     * must also be a regular file that really sits inside the temporary
     * directory.
     *
     * @return array{0: string, 1: string}|null [local path, mime type]
     */
    protected function localCopy(string $name): ?array
    {
        $storage = FileUploadConfiguration::storage();
        $path = FileUploadConfiguration::path($name, false);

        if (! $storage->fileExists($path)) {
            return null;
        }

        if ($storage->getAdapter() instanceof LocalFilesystemAdapter) {
            $real = realpath($storage->path($path));
            $directory = realpath($storage->path(FileUploadConfiguration::path('', false)));

            if ($real === false || $directory === false || ! str_starts_with($real, $directory.DIRECTORY_SEPARATOR)) {
                return null;
            }
        }

        $stream = $storage->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        $local = tempnam(sys_get_temp_dir(), 'atom-tiptap-');
        $target = fopen($local, 'wb');
        $copied = stream_copy_to_stream($stream, $target);

        fclose($stream);
        fclose($target);

        if ($copied === false) {
            @unlink($local);

            return null;
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($local);
        $extension = static::IMAGE_EXTENSIONS[$mime] ?? (MimeTypes::getDefault()->getExtensions($mime)[0] ?? 'bin');

        rename($local, $local.'.'.$extension);

        return [$local.'.'.$extension, $mime];
    }

    /**
     * Resize an image (max width 1000, quality 80) and store it on the editor
     * disk. Returns null for a file that has an image type but cannot be decoded; a
     * driver that is missing or misconfigured throws.
     */
    protected function storeImage(string $local, string $extension): ?string
    {
        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($local);
            $image->scaleDown(width: 1000);
            $image->save(quality: 80);
        }
        catch (\Intervention\Image\Exceptions\DecoderException) {
            return null;
        }

        $disk = static::disk();
        $filename = strtolower(str()->random(20)).'-'.time().'.'.$extension;

        return $disk->url($disk->putFileAs(static::folder(), $local, $filename, 'public'));
    }
}
