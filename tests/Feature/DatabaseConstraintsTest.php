<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_database_rejects_non_positive_book_total(): void
    {
        $this->expectException(QueryException::class);
        DB::table('library.book')->insert([
            'id' => 'cccccccc-cccc-4ccc-8ccc-000000000001',
            'title' => 'Invalid',
            'author' => 'Author',
            'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => 0,
            'available_copies' => 0,
            'cover_key' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_database_rejects_roles_outside_the_contract(): void
    {
        $this->expectException(QueryException::class);
        DB::table('library.user')->insert([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001',
            'username' => 'invalid-role',
            'password_hash' => 'hash',
            'role' => 'superuser',
        ]);
    }
}
