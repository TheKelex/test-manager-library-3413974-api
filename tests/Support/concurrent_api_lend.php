<?php

declare(strict_types=1);

[$script, $baseUrl, $token, $memberId, $bookId, $barrier, $worker] = $argv;
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

$request = curl_init($baseUrl.'/api/v1/loans');
curl_setopt_array($request, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'member_id' => $memberId,
        'items' => [['book_id' => $bookId]],
    ], JSON_THROW_ON_ERROR),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 20,
]);
$body = curl_exec($request);
if ($body === false) {
    fwrite(STDERR, curl_error($request)."\n");
    exit(2);
}
$status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
curl_close($request);
$payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
if ($status === 201 && isset($payload['id'])) {
    echo "201\n";
    exit(0);
}
if ($status === 409 && ($payload['code'] ?? null) === 'BOOK_NOT_AVAILABLE') {
    echo "409:BOOK_NOT_AVAILABLE\n";
    exit(0);
}

fwrite(STDERR, 'Unexpected HTTP '.$status." response.\n");
exit(2);
