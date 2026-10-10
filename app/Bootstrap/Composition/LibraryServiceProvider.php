<?php

namespace App\Bootstrap\Composition;

use App\Adapters\Outbound\Persistence\Repositories\EloquentBookRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentCategoryRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentLoanRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentMemberRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentMostBorrowedReportReader;
use App\Adapters\Outbound\Persistence\Repositories\EloquentUserRepository;
use App\Adapters\Outbound\Runtime\SystemClock;
use App\Adapters\Outbound\Runtime\UuidGenerator;
use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Security\JwtTokenVerifier;
use App\Adapters\Outbound\Storage\LocalCoverStorage;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Ports\Outbound\IdGenerator;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Ports\Outbound\MostBorrowedReportReader;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookRepository::class, EloquentBookRepository::class);
        $this->app->singleton(CategoryRepository::class, EloquentCategoryRepository::class);
        $this->app->singleton(LoanRepository::class, EloquentLoanRepository::class);
        $this->app->singleton(MemberRepository::class, EloquentMemberRepository::class);
        $this->app->singleton(MostBorrowedReportReader::class, EloquentMostBorrowedReportReader::class);
        $this->app->singleton(UserRepository::class, EloquentUserRepository::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UuidGenerator::class);
        $this->app->singleton(PasswordHasher::class, Argon2idPasswordHasher::class);
        $this->app->singleton(CoverStorage::class, LocalCoverStorage::class);

        $this->app->singleton(JwtTokenIssuer::class, static function (): JwtTokenIssuer {
            $secret = (string) config('services.jwt.secret');
            if ($secret === '') {
                throw new RuntimeException('JWT_SECRET must be configured.');
            }

            return new JwtTokenIssuer($secret, (int) config('services.jwt.lifetime'));
        });
        $this->app->singleton(JwtTokenVerifier::class, static function (): JwtTokenVerifier {
            $secret = (string) config('services.jwt.secret');
            if (strlen($secret) < 32) {
                throw new RuntimeException('JWT_SECRET must contain at least 32 characters.');
            }

            return new JwtTokenVerifier($secret);
        });
        $this->app->alias(JwtTokenIssuer::class, TokenIssuer::class);
    }
}
