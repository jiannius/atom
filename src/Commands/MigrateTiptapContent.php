<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Jiannius\Atom\Casts\AsTiptapContent;
use Jiannius\Atom\Tiptap\Content;
use Tiptap\Editor;

class MigrateTiptapContent extends Command
{
    protected $signature = 'atom:tiptap-migrate {--dry}';
    protected $description = 'Convert legacy editor HTML columns (cast as AsTiptapContent) to Tiptap JSON. Switch cast from AsEditorContent to AsTiptapContent before running.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $editor = new Editor(['extensions' => Content::extensions()]);
        $count = 0;

        foreach ($this->models() as $class) {
            $model = app($class);
            $columns = collect($model->getCasts())
                ->filter(fn ($cast) => $cast === AsTiptapContent::class)
                ->keys();

            if ($columns->isEmpty()) {
                continue;
            }

            foreach ($model->newQuery()->withoutGlobalScopes()->cursor() as $row) {
                $dirty = false;

                foreach ($columns as $column) {
                    $value = $row->getRawOriginal($column);
                    $html = @unserialize($value);          // legacy AsEditorContent stored serialize()'d HTML
                    if ($html === false && $value !== 'b:0;') {
                        $html = $value;                    // raw
                    }

                    if (! is_string($html) || $html === '' || $this->isJson($html)) {
                        continue;                          // already JSON or empty
                    }

                    $json = $editor->setContent($html)->getJSON();
                    if (! $this->option('dry')) {
                        $row->{$column} = $json;           // AsTiptapContent::set stores it
                    }
                    $dirty = true;
                    $count++;
                }

                if ($dirty && ! $this->option('dry')) {
                    $row->saveQuietly();
                }
            }
        }

        $this->info(($this->option('dry') ? '[dry] ' : '').'Migrated '.$count.' column value(s) to Tiptap JSON.');
    }

    /**
     * Is this string already a Tiptap JSON document?
     */
    protected function isJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    /**
     * All App\Models\* class names.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function models()
    {
        $path = app_path('Models');
        $models = collect();

        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $models->push('App\\Models\\'.pathinfo($file, PATHINFO_FILENAME));
                }
            }
        }

        return $models;
    }
}
