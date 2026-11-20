<?php

namespace Jiannius\Atom\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\MissingAttributeException;

/**
 * What the shells (`layouts.sidebar`, `layouts.header`) show of the signed-in user: avatar, name, email and initials, all read-only.
 *
 * A host's User model may have no public `initials()` method (or one that returns no usable string): the initials are then derived from the
 * display name. An avatar, name or email the model refuses to read (`MissingAttributeException` only, as `Model::shouldBeStrict()` throws it) reads as null.
 */
class ShellUser
{
    /**
     * @param  Authenticatable|null  $user  nobody is signed in when null
     */
    public function __construct(
        protected ?Authenticatable $user,
        public readonly mixed $avatar,
        public readonly mixed $name,
        public readonly ?string $initials,
    ) {}

    /**
     * Read the user's avatar and name now and work out the initials.
     */
    public static function from(?Authenticatable $user): static
    {
        $read = fn (string $attribute): mixed => static::readAttribute($user, $attribute);
        $name = $read('name');

        $initials = $user && method_exists($user, 'initials') && (new \ReflectionMethod($user, 'initials'))->isPublic() ? $user->initials() : null;
        $initials = is_string($initials) || $initials instanceof \Stringable ? trim((string) $initials) : null;
        $initials = $user && blank($initials)
            ? str((string) $name)->squish()->initials()->take(2)->upper()->toString()
            : $initials;

        return new static($user, $read('avatar'), $name, $initials);
    }

    /**
     * The user's email, read when asked for: a shell with no account menu never reads it.
     */
    public function email(): mixed
    {
        return static::readAttribute($this->user, 'email');
    }

    /**
     * One attribute of the user, or null when nobody is signed in, it is unset, or the model refuses to read it.
     */
    protected static function readAttribute(?Authenticatable $user, string $attribute): mixed
    {
        try {
            return isset($user->{$attribute}) ? $user->{$attribute} : null;
        } catch (MissingAttributeException) {
            return null;
        }
    }
}
