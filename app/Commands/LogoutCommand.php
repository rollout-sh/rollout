<?php

// app/Commands/Logout.php

namespace App\Commands;

class LogoutCommand extends BaseCommand
{
    protected $signature = 'logout';
    protected $description = 'Log out from your Rollout.sh account';

    public function handle() {
        $this->deleteToken();
        $this->info('Logged out successfully!');
    }
}
