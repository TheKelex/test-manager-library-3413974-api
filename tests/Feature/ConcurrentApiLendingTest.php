<?php

namespace Tests\Feature;

use App\Adapters\Outbound\Persistence\Repositories\EloquentBookRepository;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrentApiLendingTest extends TestCase
{
    public function test_concurrent_http_requests_return_one_created_and_one_conflict(): void
    {
        $this->seed(CategorySeeder::class);
        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $bookId = (string) Str::uuid();
        $loanIds = [];
        $barrier = sys_get_temp_dir().'/library-api-concurrency-'.bin2hex(random_bytes(8));
        mkdir($barrier);
        $token = $this->app->make(TokenIssuer::class)->issue($userId, 'admin');
        DB::table('library.user')->insert([
            'id' => $userId,
            'username' => 'api-concurrency-'.bin2hex(random_bytes(6)),
            'password_hash' => 'hash',
            'role' => 'admin',
        ]);
        DB::table('library.member')->insert([
            'id' => $memberId,
            'full_name' => 'Concurrent API member',
            'document_number' => 'DOC-'.bin2hex(random_bytes(6)),
        ]);
        (new EloquentBookRepository)->saveBook(Book::create(
            $bookId,
            'Concurrent API copy',
            'Author',
            Isbn::from('9780307474728'),
            '11111111-1111-4111-8111-111111111111',
            'Novela',
            1,
        ));

        try {
            $script = base_path('tests/Support/concurrent_api_lend.php');
            $processes = [];
            foreach (['first', 'second'] as $worker) {
                $process = proc_open([
                    PHP_BINARY,
                    $script,
                    'http://127.0.0.1:8000',
                    $token,
                    $memberId,
                    $bookId,
                    $barrier,
                    $worker,
                ], [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipes, base_path());
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes[1], $pipes[2]];
            }

            $results = [];
            foreach ($processes as [$process, $stdout, $stderr]) {
                $results[] = trim(stream_get_contents($stdout));
                $errorOutput = trim(stream_get_contents($stderr));
                fclose($stdout);
                fclose($stderr);
                self::assertSame(0, proc_close($process), $errorOutput);
            }

            self::assertEqualsCanonicalizing(['201', '409:BOOK_NOT_AVAILABLE'], $results);
            self::assertSame(0, (new EloquentBookRepository)->findActiveById($bookId)?->availableCopies);
            $loanIds = DB::table('library.loan')->where('member_id', $memberId)->pluck('id')->all();
            self::assertCount(1, $loanIds);
        } finally {
            $persistedLoanIds = DB::table('library.loan')->where('member_id', $memberId)->pluck('id')->all();
            DB::table('library.loan_item')->whereIn('loan_id', $persistedLoanIds)->delete();
            DB::table('library.loan')->whereIn('member_id', [$memberId])->delete();
            DB::table('library.book')->where('id', $bookId)->delete();
            DB::table('library.member')->where('id', $memberId)->delete();
            DB::table('library.user')->where('id', $userId)->delete();
            foreach (['first', 'second'] as $worker) {
                $path = $barrier.'/'.$worker;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($barrier);
        }
    }
}
