<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Casts\AsEditorContent;

class PurgeEditorImages extends Command
{
    protected $signature = 'atom:purge-editor-images {--force}';
    protected $description = 'Purge unused editor images from the disk';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('force')) {
            if (!$this->confirm('Are you sure you want to purge ALL editor images? This action cannot be undone.')) {
                $this->info('Purge cancelled.');
                return;
            }

            $this->emptyPurgedFolder();
        }
        else {
            $files = $this->getStorageFiles();
            $images = $this->getImagesFromModels();

            foreach ($files as $path) {
                $exists = $images->contains(fn ($image) => str($image)->endsWith($path));
                if ($exists) continue;

                // move the unused file to editor-purged folder just in case we need it later
                $this->moveToPurgedFolder($path);
                $this->getDisk()->delete($path);

                $this->info('Deleted: '.$path);
            }

            $this->newLine(2);
            $this->info('Purge completed.');
        }
    }

    /**
     * Get all files in the editor folder
     */
    public function getStorageFiles()
    {
        $disk = $this->getDisk();
        $folder = collect([data_get($disk->getConfig(), 'folder'), 'editor'])->filter()->join('/');

        return collect($disk->files($folder));
    }

    /**
     * Get all images from models
     */
    public function getImagesFromModels()
    {
        $models = $this->getModels();
        $images = collect();

        foreach ($models as $model) {
            $model = app($model);
            $columns = collect($model->getCasts())->filter(fn ($cast) => $cast === AsEditorContent::class)->keys()->values();

            if ($columns->isEmpty()) continue;

            $rows = $model->withoutGlobalScopes()
                ->select($columns->all())
                ->where(fn ($q) => $columns->each(fn ($column) => $q->orWhereNotNull($column)))
                ->get();

            foreach ($rows as $row) {
                foreach ($columns as $column) {
                    $content = $row->{$column};

                    if ($content && is_string($content)) {
                        if (preg_match_all('/<img\s[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches)) {
                            foreach ($matches[1] as $src) {
                                $images->push($src);
                            }
                        }
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Get all models with editor content column
     */
    public function getModels()
    {
        $path = app_path('Models');
        $models = [];

        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if ($file === '.' || $file === '..') continue;
                $fullPath = $path.DIRECTORY_SEPARATOR.$file;

                if (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                    $model = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);
                    $models[] = $model;
                }
            }
        }

        return collect($models);
    }

    /**
     * Get the disk
     */
    public function getDisk()
    {
        return Storage::disk(env('FILESYSTEM_DISK'));
    }

    /**
     * Move the file to the purged folder
     */
    public function moveToPurgedFolder($path)
    {
        $content = file_get_contents($this->getDisk()->url($path));
        Storage::disk('local')->put('editor-purged/'.basename($path), $content);
    }

    /**
     * Empty the purged folder
     */
    public function emptyPurgedFolder()
    {
        $path = storage_path('app/private/editor-purged');

        if (is_dir($path)) {
            $files = glob($path . '/*');

            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }

            $this->info('All files in editor-purged have been deleted.');
        } else {
            $this->info('editor-purged folder does not exist.');
        }
    }
}
