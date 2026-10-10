<?php

namespace App\Adapters\Outbound\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

abstract class LibraryRecord extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
