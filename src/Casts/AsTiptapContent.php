<?php

namespace Jiannius\Atom\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Tiptap\Content;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\Mime\MimeTypes;

class AsTiptapContent implements CastsAttributes
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
     * Read a stored value. New rows are Tiptap JSON (returned as-is). Legacy
     * rows were stored by the old AsEditorContent cast (removed in v4) as
     * serialize()'d HTML — unserialize them (no classes allowed) to the raw HTML
     * string. This is the dual-read path: a host moves its rows to JSON with
     * atom:tiptap-migrate when it is ready, never in one step.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        [$serialized, $unserialized] = Content::unserialize($value);

        return $serialized ? $unserialized : $value;
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
            return $this->storableNonJson($value);
        }

        $persisted = [];

        $this->walkImages($doc, function (array &$node) use ($model, $key, &$persisted) {
            $src = $node['attrs']['src'] ?? null;

            if ($src && $this->isTemporaryUpload($src)) {
                $name = $this->temporaryName($src);
                $url = ($name ? $persisted[$name] ?? null : null) ?? $this->persist($model, $key, $src);

                if ($name && $url !== $src) {
                    $persisted[$name] = $url;
                }

                $node['attrs']['src'] = $url;
            }
        });

        return json_encode($doc);
    }

    /**
     * A string that is not a JSON doc is legacy HTML and is stored as-is (the
     * renderer only emits schema nodes). A PHP-serialized string never is: the
     * value can arrive from the browser, so a serialized string is unwrapped to
     * the HTML it holds, and anything else it holds (an object, array, scalar)
     * is dropped. One longer than Content::SANITIZE_MAX_BYTES is dropped unread:
     * unserialize() costs about 30 times its input in memory.
     */
    protected function storableNonJson(string $value): ?string
    {
        if (strlen($value) > Content::SANITIZE_MAX_BYTES && preg_match('/^[abdisSOCErRN][:;]/', $value)) {
            return null;
        }

        [$serialized, $unserialized] = Content::unserialize($value);

        if (! $serialized) {
            return $value;
        }

        return is_string($unserialized) && $unserialized !== '' ? $unserialized : null;
    }

    /**
     * The disk this cast writes editor images to. The purge command reads it
     * from here so the two can never disagree.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(static::diskName());
    }

    /**
     * The name of the disk returned by disk(), for telling two disks apart.
     */
    public static function diskName(): string
    {
        return config('atom.editor.disk') ?: config('filesystems.default');
    }

    /**
     * The folder, on that disk, this cast writes editor images to.
     */
    public static function folder(): string
    {
        return collect([data_get(static::disk()->getConfig(), 'folder'), 'editor'])->filter()->join('/');
    }

    /**
     * Every file URL a stored value references, read the way get() reads it.
     * A stored value can be Tiptap JSON or HTML, raw or serialize()'d, in
     * the column (a host that came from the old AsEditorContent cast has
     * serialize(<JSON>) there until atom:tiptap-migrate has run). So the value is decoded once,
     * then read as HTML (quoted and unquoted src, srcset, href) and, when it
     * decodes as a JSON document, as JSON too: its image nodes' srcs plus every
     * other src/href/srcset attribute in it (e.g. link marks). A value that is
     * not valid JSON is not an error, it is just read as text. Throws only for a
     * value that cannot be read at all: not a string, or looks serialized but
     * cannot be unserialized. The caller must not mistake "unreadable" for "no
     * images", and must not rely on this alone to decide a deletion.
     *
     * @return array<int, string>
     */
    public function imageSources(Model $model, string $key, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_string($value)) {
            throw new \UnexpectedValueException('stored value is not a string');
        }

        $decoded = $this->get($model, $key, $value, []);

        if (! is_string($decoded)) {
            throw new \UnexpectedValueException('stored value did not decode to a string');
        }

        $trimmed = ltrim($decoded);

        if ($trimmed === '') {
            return [];
        }

        if ($decoded === $value && preg_match('/^[abdisO]:\d/', $trimmed)) {
            throw new \UnexpectedValueException('stored value looks serialized but could not be unserialized');
        }

        $sources = $this->htmlSources($decoded);
        $doc = in_array($trimmed[0], ['{', '['], true) ? json_decode($decoded, true) : null;

        if (is_array($doc)) {
            $this->walkImages($doc, function (array &$node) use (&$sources) {
                if (is_string($node['attrs']['src'] ?? null)) {
                    $sources[] = $node['attrs']['src'];
                }
            });

            array_walk_recursive($doc, function ($item, $attribute) use (&$sources) {
                if (is_string($item) && in_array($attribute, ['src', 'href'], true)) {
                    $sources[] = $item;
                }
                elseif (is_string($item) && $attribute === 'srcset') {
                    array_push($sources, ...$this->srcsetUrls($item));
                }
            });
        }

        return $sources;
    }

    /**
     * Every src, srcset and href URL in an HTML string, quoted or not.
     *
     * @return array<int, string>
     */
    protected function htmlSources(string $html): array
    {
        $sources = [];

        if (preg_match_all('/\b(?:src|href)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sources[] = $match[1] ?: ($match[2] ?? '') ?: ($match[3] ?? '');
            }
        }

        if (preg_match_all('/\bsrcset\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                array_push($sources, ...$this->srcsetUrls($match[1] ?: ($match[2] ?? '') ?: ($match[3] ?? '')));
            }
        }

        return array_values(array_filter($sources, fn ($source) => $source !== ''));
    }

    /**
     * The URLs of a srcset attribute value ("a.jpg 1x, b.jpg 2x").
     *
     * @return array<int, string>
     */
    protected function srcsetUrls(string $srcset): array
    {
        return collect(explode(',', $srcset))
            ->map(fn ($candidate) => preg_split('/\s+/', trim($candidate))[0] ?? '')
            ->filter()
            ->values()
            ->all();
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
     * The name of the Livewire temporary file a src points at, or null when the
     * src is not a preview URL for a file Livewire itself would have named. The
     * src comes from the browser, so the last URL segment is only ever used as a
     * name inside Livewire's temporary directory, and only when it has the shape
     * Livewire generates (a random token, an optional "-meta<base64>-" part and
     * an extension, which may be empty or odd when it came from the client): a "..", an empty name, a separator or any other file's name
     * is refused here.
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
     * Persist a temporary upload to permanent storage and return its URL.
     * A model may override persistence via tiptapStoreImage($tmpPath, $key),
     * which gets a local copy of the file, and only for a jpeg, png, gif or
     * webp. A src that is not a Livewire temporary file, or a file that is not
     * one of those, is left as it is.
     */
    protected function persist(Model $model, string $key, string $src): string
    {
        $name = $this->temporaryName($src);
        $copy = $name ? $this->localCopy($name) : null;

        if (! $copy) {
            return $src;
        }

        [$local, $mime] = $copy;

        try {
            $extension = static::IMAGE_EXTENSIONS[$mime] ?? null;

            if (! $extension) {
                return $src;
            }

            $url = method_exists($model, 'tiptapStoreImage')
                ? $model->tiptapStoreImage($local, $key)
                : $this->storeImage($local, $extension);
        }
        finally {
            @unlink($local);
        }

        if (! $url) {
            return $src;
        }

        FileUploadConfiguration::storage()->delete([
            FileUploadConfiguration::path($name, false),
            FileUploadConfiguration::path($name.'.json', false),
        ]);

        return $url;
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
