<?php

namespace App\Adapters\Inbound\Console;

use App\Application\UseCases\EnsureInitialAdmin;
use Illuminate\Console\Command;

final class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';

    protected $description = 'Create the initial administrator from environment credentials.';

    public function handle(EnsureInitialAdmin $ensure): int
    {
        $admin = $ensure->execute(
            (string) config('library.admin_username'),
            (string) config('library.admin_password'),
        );
        $this->components->info('Initial administrator is ready: '.$admin->username);

        return self::SUCCESS;
    }
}
