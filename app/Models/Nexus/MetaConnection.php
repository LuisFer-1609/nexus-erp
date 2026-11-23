<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

class MetaConnection extends Model
{
    protected $table = 'meta_connections';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted'];
    }
}
