<?php

namespace App\Livewire;

class Facts
{
    /**
     * The facts of a job
     */
    public function facts(): array
    {
        return [
            ['label' => 'Site', 'value' => $this->site ?: '--'], // expect: empty-glyph
            ['label' => 'Due', 'value' => '—'], // expect: empty-glyph
            ['label' => 'Ref', 'value' => $this->ref ?: '-'], // expect: empty-glyph
            ['label' => 'Owner', 'value' => $this->owner ?? '–'], // expect: empty-glyph
            ['label' => 'Start', 'value' => '--'], // expect: empty-glyph
            ['label' => 'Gap', 'value' => $this->a - $this->b],
            ['label' => 'Lines', 'value' => implode(' - ', $this->lines)],
            ['label' => 'Note', 'value' => 'n/a'],
        ];
    }

    public function slug(string $title): string
    {
        // a lone hyphen outside a fact is a separator, not a missing value
        $separator = $this->separator ?: '-';

        return str($title)->slug($separator)->toString().$this->options['separator'] ?? '-';
    }
}
