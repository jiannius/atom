<?php

namespace Jiannius\Atom\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The backups atom:tiptap-migrate writes before it changes a stored value: one
 * row per migrated cell, in a table the command creates on the model's own
 * connection (atom has no package migration). The original is kept byte for
 * byte: as text when it is valid UTF-8 without a NUL, base64 otherwise, and a
 * backup is read back and compared before the caller is allowed to go on.
 * Nothing else drops the table or its rows but prune().
 */
class EditorBackups
{
    public const TABLE = 'atom_editor_backups';

    public function __construct(protected ?string $connection = null) {}

    /**
     * The backups on a connection (null is the default one)
     */
    public static function on(?string $connection = null): static
    {
        return new static($connection);
    }

    /**
     * The connection the backups live on
     */
    public function connection(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }

    /**
     * Whether the table exists yet
     */
    public function exists(): bool
    {
        return Schema::connection($this->connection)->hasTable(static::TABLE);
    }

    /**
     * Create the table when it is not there. Call it outside a transaction: DDL
     * commits one on some engines.
     */
    public function create(): void
    {
        if ($this->exists()) {
            return;
        }

        Schema::connection($this->connection)->create(static::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('batch', 36);
            $table->string('model', 191);
            $table->string('row_key', 191);
            $table->string('column_name', 191);
            $table->longText('original');
            $table->string('encoding', 10);
            $table->string('original_hash', 64);
            $table->string('migrated_hash', 64);
            $table->boolean('accepted_lossy')->default(false);
            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->index(['model', 'row_key', 'column_name']);
        });
    }

    /**
     * Keep an original value. The row is read back and compared byte for byte,
     * and a backup that does not hold the value throws: the caller must not
     * write over what it could not keep.
     *
     * @return int the id of the backup
     */
    public function store(string $batch, string $model, string $key, string $column, string $original, string $migrated, bool $acceptedLossy): int
    {
        [$stored, $encoding] = static::encode($original);

        $id = (int) $this->connection()->table(static::TABLE)->insertGetId([
            'batch' => $batch,
            'model' => $model,
            'row_key' => $key,
            'column_name' => $column,
            'original' => $stored,
            'encoding' => $encoding,
            'original_hash' => static::hash($original),
            'migrated_hash' => static::hash($migrated),
            'accepted_lossy' => $acceptedLossy,
            'migrated_at' => now()->toDateTimeString(),
        ]);

        $back = $this->connection()->table(static::TABLE)->where('id', $id)->first(['original', 'encoding']);

        if (! $back || ! is_string($back->original) || static::decode($back->original, $back->encoding) !== $original) {
            throw new \RuntimeException('the backup could not hold the original value byte for byte');
        }

        return $id;
    }

    /**
     * The backups not yet restored
     */
    public function pending(): Builder
    {
        return $this->connection()->table(static::TABLE)->whereNull('restored_at');
    }

    /**
     * Note that a backup has been put back
     */
    public function markRestored(int $id): void
    {
        $this->connection()->table(static::TABLE)->where('id', $id)->update(['restored_at' => now()->toDateTimeString()]);
    }

    /**
     * Delete the backups (of these models only, when given) and drop the table
     * when nothing is left. The only call that removes a backup.
     *
     * @param  array<int, string>|null  $models
     * @return int the number of backups deleted
     */
    public function prune(?array $models = null): int
    {
        if (! $this->exists()) {
            return 0;
        }

        $query = $this->connection()->table(static::TABLE);

        if ($models !== null) {
            $query->whereIn('model', $models);
        }

        $deleted = $query->delete();

        if ($this->connection()->table(static::TABLE)->doesntExist()) {
            Schema::connection($this->connection)->drop(static::TABLE);
        }

        return $deleted;
    }

    /**
     * The original value of a backup row, as it was stored in the column
     */
    public static function original(object $backup): string
    {
        return static::decode((string) $backup->original, (string) $backup->encoding);
    }

    /**
     * What goes in the backup column for a value, and how it is encoded
     *
     * @return array{0: string, 1: string} [stored, 'raw'|'base64']
     */
    public static function encode(string $value): array
    {
        return mb_check_encoding($value, 'UTF-8') && ! str_contains($value, "\0")
            ? [$value, 'raw']
            : [base64_encode($value), 'base64'];
    }

    /**
     * The value a stored backup holds
     */
    public static function decode(string $stored, string $encoding): string
    {
        if ($encoding !== 'base64') {
            return $stored;
        }

        $decoded = base64_decode($stored, true);

        if ($decoded === false) {
            throw new \UnexpectedValueException('the backup is not valid base64');
        }

        return $decoded;
    }

    /**
     * The hash that tells whether a value is the one a backup stands for
     */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
