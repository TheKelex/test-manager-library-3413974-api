<?php

declare(strict_types=1);
use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan\Loan;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $bookId, $memberId, $userId, $loanId, $itemId, $barrier, $worker] = $argv;
$readyFile = $barrier.'/'.$worker;
$otherFile = $barrier.'/'.($worker === 'first' ? 'second' : 'first');
file_put_contents($readyFile, 'ready', LOCK_EX);
$deadline = microtime(true) + 15;
while (! is_file($otherFile)) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Concurrency barrier timed out.\n");
        exit(2);
    }
    usleep(10000);
}

$books = $app->make(BookRepository::class);
$book = $books->findActiveById($bookId);
if ($book === null) {
    fwrite(STDERR, "Book not found.\n");
    exit(2);
}
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$loan = Loan::start($loanId, $memberId, $userId, $now)
    ->addBook($book, $itemId, $book->categoryName);

try {
    $app->make(LoanRepository::class)
        ->saveLendingAtomically($loan, [$book->withdraw()]);
    echo "OK\n";
} catch (UseCaseError $error) {
    if ($error->errorCode !== 'BOOK_NOT_AVAILABLE') {
        fwrite(STDERR, $error->errorCode.': '.$error->getMessage()."\n");
        exit(2);
    }
    echo $error->errorCode."\n";
}
