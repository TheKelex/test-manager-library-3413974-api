<?php

namespace Tests\Feature;

use App\Adapters\Outbound\Persistence\Repositories\EloquentBookRepository;
use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrentLendingTest extends TestCase
{
    public function test_only_one_concurrent_request_can_borrow_the_last_copy(): void
    {
        $this->seed(CategorySeeder::class);
        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $bookId = (string) Str::uuid();
        $loanIds = [(string) Str::uuid(), (string) Str::uuid()];
        $itemIds = [(string) Str::uuid(), (string) Str::uuid()];
        $barrier = sys_get_temp_dir().'/library-concurrency-'.bin2hex(random_bytes(8));
        mkdir($barrier);
        DB::table('library.user')->insert([
            'id' => $userId,
            'username' => 'concurrency-'.bin2hex(random_bytes(6)),
            'password_hash' => 'hash',
            'role' => 'admin',
        ]);
        DB::table('library.member')->insert([
            'id' => $memberId,
            'full_name' => 'Concurrency member',
            'document_number' => 'DOC-'.bin2hex(random_bytes(6)),
        ]);
        (new EloquentBookRepository)->saveBook(Book::create(
            $bookId,
            'Last copy',
            'Author',
            Isbn::from('9780307474728'),
            '11111111-1111-4111-8111-111111111111',
            'Novela',
            1,
        ));

        try {
            $script = base_path('tests/Support/concurrent_lend.php');
            $processes = [];
            foreach (['first', 'second'] as $index => $worker) {
                $command = [
                    PHP_BINARY,
                    $script,
                    $bookId,
                    $memberId,
                    $userId,
                    $loanIds[$index],
                    $itemIds[$index],
                    $barrier,
                    $worker,
                ];
                $pipes = [];
                $process = proc_open($command, [
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

            self::assertEqualsCanonicalizing(['OK', 'BOOK_NOT_AVAILABLE'], $results);
            self::assertSame(0, (new EloquentBookRepository)->findActiveById($bookId)?->availableCopies);
            self::assertSame(1, DB::table('library.loan')->whereIn('id', $loanIds)->count());
        } finally {
            DB::table('library.loan_item')->whereIn('loan_id', $loanIds)->delete();
            DB::table('library.loan')->whereIn('id', $loanIds)->delete();
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
