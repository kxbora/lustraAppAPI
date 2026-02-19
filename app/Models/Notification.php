<?php

namespace App\Models;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Notification extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function createSafe(array $attributes): self
    {
        if (! Schema::hasColumn('notifications', 'type')) {
            unset($attributes['type']);
        }

        try {
            return self::create($attributes);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'column "type"')) {
                unset($attributes['type']);
                return self::create($attributes);
            }

            throw $exception;
        }
    }
}
