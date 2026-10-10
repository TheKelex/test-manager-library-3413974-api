<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArchitectureBoundariesTest extends TestCase
{
    public function test_domain_and_application_do_not_depend_on_framework_or_adapters(): void
    {
        foreach (['app/Domain', 'app/Application'] as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                $source = File::get($file->getPathname());
                self::assertStringNotContainsString('Illuminate\\', $source, $file->getRelativePathname());
                self::assertStringNotContainsString('App\\Adapters\\', $source, $file->getRelativePathname());
                self::assertStringNotContainsString('App\\Http\\', $source, $file->getRelativePathname());
            }
        }
    }

    public function test_infrastructure_construction_and_port_bindings_stay_in_composition_root(): void
    {
        foreach (['app/Application', 'app/Adapters/Inbound/Http/Controllers'] as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                $source = File::get($file->getPathname());
                self::assertStringNotContainsString('new Eloquent', $source, $file->getRelativePathname());
                self::assertStringNotContainsString('DB::', $source, $file->getRelativePathname());
            }
        }

        $composition = File::get(base_path('app/Bootstrap/Composition/LibraryServiceProvider.php'));
        foreach ([
            'BookRepository::class',
            'CategoryRepository::class',
            'LoanRepository::class',
            'MemberRepository::class',
            'MostBorrowedReportReader::class',
            'PasswordHasher::class',
            'TokenIssuer::class',
            'UserRepository::class',
        ] as $port) {
            self::assertStringContainsString($port, $composition);
        }
    }
}
