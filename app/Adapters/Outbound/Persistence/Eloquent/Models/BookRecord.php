<?php

namespace App\Adapters\Outbound\Persistence\Eloquent\Models;

final class BookRecord extends LibraryRecord
{
    protected $table = 'library.book';
}
