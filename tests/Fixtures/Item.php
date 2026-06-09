<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /** @param Builder $query */
    public function scopeSearch(Builder $query, ?string $value): void
    {
        if ($value) {
            $query->where('name', 'like', "%{$value}%");
        }
    }

    /** @param Builder $query */
    public function scopeStatus(Builder $query, mixed $value): void
    {
        if ($value) {
            $query->whereIn('status', (array) $value);
        }
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }
}
