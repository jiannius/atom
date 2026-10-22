<?php

namespace Jiannius\Atom\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The backups atom:tiptap-migrate writes before it changes a stored value: one
 * row per migrated cell, in a table the command creates on the model's own
 * connection (atom has no package migration). The original is kept byte for
 * byte: as text when it is valid UTF-8 without a NUL, base64 otherwise, and a
 * backup is read back and compared before the caller is allowed to go on.
 * Nothing but prune() deletes a backup; the table is never dropped.
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
     * Keep an original value. The row is read back and compared byte for byte
     * (the key, model and column too: a non-strict MySQL truncates a string
     * that is too long without an error), and a backup that does not hold the
     * value throws: the caller must not write over what it could not keep.
     *
     * @return int the id of the backup
     */
    public function store(string $batch, string $model, string $key, string $column, string $original, string $migrated, bool $acceptedLossy): int
    {
        foreach (['row key' => $key, 'model' => $model, 'column' => $column] as $what => $value) {
            if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 191 || str_contains($value, "\0")) {
                throw new EditorBackupException('the '.$what.' is not valid UTF-8 or is longer than 191 characters, so it cannot be backed up');
            }
        }

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

        $back = $this->connection()->table(static::TABLE)->where('id', $id)->first(['original', 'encoding', 'row_key', 'model', 'column_name']);

        if (! $back || ! is_string($back->original) || static::decode($back->original, $back->encoding) !== $original) {
            throw new EditorBackupException('the backup could not hold the original value byte for byte');
        }

        if ($back->row_key !== $key || $back->model !== $model || $back->column_name !== $column) {
            throw new EditorBackupException('the backup could not hold the row key, model and column as they are');
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
     * Delete the backups (of these models only, when given). The only call that
     * removes a backup. The table stays, even empty: dropping it could race a
     * migration that is writing to it, and an empty table costs nothing.
     *
     * @param  array<int, string>|null  $models
     * @param  array<int, string>|null  $columns
     * @return int the number of backups deleted
     */
    public function prune(?array $models = null, ?array $columns = null): int
    {
        if (! $this->exists()) {
            return 0;
        }

        $query = $this->connection()->table(static::TABLE);

        if ($models !== null) {
            $query->whereIn('model', $models);
        }

        if ($columns) {
            $query->whereIn('column_name', $columns);
        }

        return $query->delete();
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
     * Whether a backup still holds the value it was made from: its original
     * must hash to the hash written beside it
     */
    public static function intact(object $backup): bool
    {
        try {
            return hash_equals((string) $backup->original_hash, static::hash(static::original($backup)));
        }
        catch (Throwable) {
            return false;
        }
    }

    /**
     * Say what failed without saying what the row held: a database error is its
     * class and SQLSTATE (its message carries the SQL and the bound values), one
     * of atom's own is its message (a fixed string), a LogicException (an
     * unconfigured connection, say) its message, which has no row in it, anything
     * else its class.
     * Capped, and safe to print or log.
     */
    public static function describe(Throwable $e): string
    {
        $class = class_basename($e);

        if ($e instanceof \PDOException || $e instanceof \Illuminate\Database\QueryException) {
            $info = $e->errorInfo ?? [];

            return $class.' (SQLSTATE '.($info[0] ?? $e->getCode()).(isset($info[1]) ? ', driver code '.$info[1] : '').')';
        }

        if ($e instanceof EditorBackupException || $e instanceof \UnexpectedValueException || $e instanceof \LogicException) {
            return mb_substr(mb_scrub($e->getMessage(), 'UTF-8'), 0, 300);
        }

        return $class;
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
