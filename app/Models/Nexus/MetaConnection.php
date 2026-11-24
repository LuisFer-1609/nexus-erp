<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon|null $expires_at */
class MetaConnection extends Model
{
    protected $table = 'meta_connections';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'expires_at' => 'datetime'];
    }
}
