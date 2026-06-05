<?php

declare(strict_types=1);

namespace Shared\Domain\Concerns;

use Illuminate\Support\Str;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (mixed $model): void {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::uuid();
            }
        });
    }
}
