<?php

namespace Tests\Feature;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $librarianToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $passwords = $this->app->make(PasswordHasher::class);
        $users = $this->app->make(UserRepository::class);
        $users->saveUser(new User('aaaaaaaa-aaaa-4aaa-8aaa-000000000001', 'admin', $passwords->hash('admin-secret'), 'admin'));
        $users->saveUser(new User('aaaaaaaa-aaaa-4aaa-8aaa-000000000002', 'librarian', $passwords->hash('librarian-secret'), 'librarian'));
        $tokens = $this->app->make(TokenIssuer::class);
        $this->adminToken = $tokens->issue('aaaaaaaa-aaaa-4aaa-8aaa-000000000001', 'admin');
        $this->librarianToken = $tokens->issue('aaaaaaaa-aaaa-4aaa-8aaa-000000000002', 'librarian');
    }

    public function test_login_and_errors_follow_the_uniform_contract(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'admin-secret',
        ])->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'role'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 28800)
            ->assertJsonPath('role', 'admin');

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'wrong',
        ])->assertUnauthorized()->assertExactJson([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'El usuario o la contraseña no son válidos.',
            'details' => [],
        ]);
        $this->getJson('/api/v1/books')->assertUnauthorized()->assertJsonStructure(['code', 'message', 'details']);
    }

    public function test_roles_routes_book_member_loan_return_and_report_contract(): void
    {
        $admin = ['Authorization' => 'Bearer '.$this->adminToken];
        $librarian = ['Authorization' => 'Bearer '.$this->librarianToken];

        $this->getJson('/api/v1/categories', $admin)->assertOk()->assertJsonCount(5, 'items');
        $this->postJson('/api/v1/books', [
            'title' => 'Contract Book',
            'author' => 'Author',
            'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => 2,
        ], $librarian)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN_ROLE');

        $book = $this->postJson('/api/v1/books', [
            'title' => 'Contract Book',
            'author' => 'Author',
            'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => 2,
        ], $admin)->assertCreated()->assertJsonPath('available_copies', 2)->json();
        $this->postJson('/api/v1/members', [
            'full_name' => 'Ana Pérez',
            'document_number' => 'DOC-1',
        ], $librarian)->assertCreated();
        $member = $this->postJson('/api/v1/members', [
            'full_name' => 'Luis López',
            'document_number' => 'DOC-2',
        ], $librarian)->assertCreated()->json();

        $loan = $this->postJson('/api/v1/loans', [
            'member_id' => $member['id'],
            'items' => [['book_id' => $book['id']]],
        ], $librarian)->assertCreated()->assertJsonPath('items.0.book_title', 'Contract Book')->json();
        $this->getJson('/api/v1/loans/'.$loan['id'], $librarian)
            ->assertOk()->assertJsonPath('status', 'open');
        $this->postJson('/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return', [], $librarian)
            ->assertOk()->assertJsonMissing(['returned_at' => null])->assertJsonStructure(['returned_at']);
        $this->getJson('/api/v1/loans?status=closed', $librarian)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-10&to=2026-10-10', $admin)
            ->assertOk()->assertJsonStructure(['from', 'to', 'rows']);
    }

    public function test_request_validation_and_duplicate_documents_use_contract_codes(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->adminToken];
        $this->postJson('/api/v1/users', [
            'username' => 'new-user',
            'password' => 'secret',
            'role' => 'admin',
        ], $auth)->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['code', 'message', 'details' => ['*' => ['field', 'issue']]]);

        $payload = ['full_name' => 'Ana', 'document_number' => 'DOC-1'];
        $this->postJson('/api/v1/members', $payload, $auth)->assertCreated();
        $this->postJson('/api/v1/members', $payload, $auth)
            ->assertStatus(409)
            ->assertJsonPath('code', 'DOCUMENT_TAKEN');
    }

    public function test_initial_admin_command_is_idempotent_and_uses_environment_password(): void
    {
        config(['library.admin_username' => 'bootstrap-admin']);
        $this->artisan('library:bootstrap-admin')
            ->expectsOutputToContain('Initial administrator is ready: bootstrap-admin')
            ->assertSuccessful();
        $this->artisan('library:bootstrap-admin')->assertSuccessful();
        $created = $this->app->make(UserRepository::class)->findByUsername('bootstrap-admin');
        self::assertNotNull($created);
        self::assertTrue($this->app->make(PasswordHasher::class)->verify(
            (string) config('library.admin_password'),
            $created->passwordHash,
        ));

        $this->postJson('/api/v1/auth/login', [
            'username' => 'bootstrap-admin',
            'password' => (string) config('library.admin_password'),
        ])->assertOk()->assertJsonPath('role', 'admin');
    }

    public function test_book_catalog_cover_and_user_routes_keep_contract_shapes(): void
    {
        Storage::fake('covers');
        $admin = ['Authorization' => 'Bearer '.$this->adminToken];
        $created = $this->postJson('/api/v1/users', [
            'username' => 'New Librarian',
            'password' => 'new-secret',
            'role' => 'librarian',
        ], $admin)->assertCreated()->assertJsonStructure(['id', 'username', 'role'])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('password_hash')
            ->json();

        $book = $this->postJson('/api/v1/books', [
            'title' => 'Cover Contract',
            'author' => 'Author',
            'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => 1,
        ], $admin)->assertCreated()->json();
        $this->postJson('/api/v1/books', [
            'title' => 'Duplicate ISBN',
            'author' => 'Author',
            'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => 1,
        ], $admin)->assertStatus(409)->assertJsonPath('code', 'ISBN_TAKEN');

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aXcAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($png);
        $file = UploadedFile::fake()->createWithContent(
            'cover.png',
            $png.str_repeat("\0", 2 * 1024 * 1024 - strlen($png)),
        );
        $uploaded = $this->put('/api/v1/books/'.$book['id'].'/cover', ['file' => $file], $admin);
        $uploaded->assertOk()->assertJsonStructure(['cover_key']);
        $coverKey = $uploaded->json('cover_key');
        Storage::disk('covers')->assertExists($coverKey);
        $oversized = UploadedFile::fake()->createWithContent(
            'too-large.png',
            $png.str_repeat("\0", 2 * 1024 * 1024 + 1 - strlen($png)),
        );
        $this->put('/api/v1/books/'.$book['id'].'/cover', ['file' => $oversized], $admin)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->get('/api/v1/books/'.$book['id'].'/cover', $admin)->assertOk()->assertHeader('content-type', 'image/png');
        $this->deleteJson('/api/v1/books/'.$book['id'].'/cover', [], $admin)->assertNoContent();
        Storage::disk('covers')->assertMissing($coverKey);

        $this->getJson('/api/v1/books/'.$book['id'], $admin)->assertOk()
            ->assertJsonPath('category.name', 'Novela');
        $this->putJson('/api/v1/books/'.$book['id'], [
            'title' => 'Edited',
            'author' => 'Author',
            'category_id' => '22222222-2222-4222-8222-222222222222',
            'total_copies' => 2,
        ], $admin)->assertOk()->assertJsonPath('available_copies', 2);
        $this->getJson('/api/v1/books?q=Edited&size=1', $admin)->assertOk()->assertJsonPath('total', 1);
        $this->deleteJson('/api/v1/books/'.$book['id'], [], $admin)->assertNoContent();
        $this->getJson('/api/v1/books/'.$book['id'], $admin)->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonStructure(['code', 'message', 'details']);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-10&to=2026-10-10', [
            'Authorization' => 'Bearer '.$this->librarianToken,
        ])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }
}
