<?php

namespace Tests\Feature;

use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Security\JwtTokenVerifier;
use App\Adapters\Outbound\Storage\LocalCoverStorage;
use App\Application\Errors\UseCaseError;
use App\Application\UseCases\UploadCover;
use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeCoverStorage;
use Tests\Fakes\FakeLibrary;
use Tests\TestCase;
use UnexpectedValueException;

class SecurityAndStorageAdaptersTest extends TestCase
{
    public function test_argon2id_password_hash_is_not_the_original_secret(): void
    {
        $hasher = new Argon2idPasswordHasher;
        $hash = $hasher->hash('private-password');

        self::assertNotSame('private-password', $hash);
        self::assertTrue($hasher->verify('private-password', $hash));
        self::assertFalse($hasher->verify('wrong-password', $hash));
    }

    public function test_jwt_contains_only_required_identity_claims_and_expires_after_eight_hours(): void
    {
        $secret = str_repeat('s', 64);
        $issuer = new JwtTokenIssuer($secret);
        $token = $issuer->issue('user-id', 'librarian');
        $verifier = new JwtTokenVerifier($secret);

        $claims = $verifier->verify($token);
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));

        self::assertSame(['sub' => 'user-id', 'role' => 'librarian'], $claims);
        self::assertSame(28800, $decoded->exp - $decoded->iat);
        self::assertEqualsCanonicalizing(['exp', 'iat', 'role', 'sub'], array_keys((array) $decoded));
    }

    public function test_jwt_rejects_invalid_signature_and_claims(): void
    {
        $token = (new JwtTokenIssuer(str_repeat('a', 64)))->issue('user-id', 'admin');

        $this->expectException(UnexpectedValueException::class);
        (new JwtTokenVerifier(str_repeat('b', 64)))->verify($token);
    }

    public function test_private_cover_storage_uses_opaque_key_and_can_delete(): void
    {
        Storage::fake('covers');
        $storage = new LocalCoverStorage;
        $key = $storage->put('book-id', 'image-bytes', 'image/png');

        self::assertMatchesRegularExpression('~^covers/[0-9a-f-]+\.png$~', $key);
        Storage::disk('covers')->assertExists($key);
        $storage->delete($key);
        Storage::disk('covers')->assertMissing($key);
    }

    public function test_upload_accepts_exact_two_mebibytes_and_replaces_old_binary_after_save(): void
    {
        $library = new FakeLibrary;
        $book = Book::create('book-id', 'Title', 'Author', Isbn::from('9780307474728'), 'category-1', 'Novela', 1)
            ->withCover('old-cover.png');
        $library->books[$book->id] = $book;
        $covers = new FakeCoverStorage;
        $covers->files['old-cover.png'] = 'old-image';
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aXcAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($png);
        $contents = $png.str_repeat("\0", 2 * 1024 * 1024 - strlen($png));

        $updated = (new UploadCover($library, $covers))->execute($book->id, $contents);

        self::assertSame(2 * 1024 * 1024, strlen($contents));
        self::assertSame('covers/book-id.png', $updated->coverKey);
        self::assertArrayNotHasKey('old-cover.png', $covers->files);
    }

    public function test_upload_rejects_oversized_or_non_image_bytes_before_storage(): void
    {
        $library = new FakeLibrary;
        $book = Book::create('book-id', 'Title', 'Author', Isbn::from('9780307474728'), 'category-1', 'Novela', 1);
        $library->books[$book->id] = $book;
        $covers = new FakeCoverStorage;
        $upload = new UploadCover($library, $covers);

        try {
            $upload->execute($book->id, str_repeat('x', 2 * 1024 * 1024 + 1));
            self::fail('Oversized image was accepted.');
        } catch (UseCaseError $error) {
            self::assertSame('VALIDATION_ERROR', $error->errorCode);
        }
        try {
            $upload->execute($book->id, 'not an image');
            self::fail('Invalid image was accepted.');
        } catch (UseCaseError $error) {
            self::assertSame('VALIDATION_ERROR', $error->errorCode);
        }
        self::assertSame([], $covers->files);
    }
}
