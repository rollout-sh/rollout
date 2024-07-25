<?php

// app/Commands/WhoAmI.php

namespace App\Commands;

class WhoAmI extends BaseCommand
{
    protected $signature = 'whoami';
    protected $description = 'Displays the user information based on the current session';

    public function handle()
    {
        if (!$this->hasValidCredentials()) {
            $this->error('You are not logged in. Please log in first.');
            return self::FAILURE;
        }

        $response = $this->makeApiRequest('/user', 'GET');

        if ($response && $response['success']) {
            $user = $response['data'];
            $this->info("You are logged in as: {$user['email']}");
            if (!empty($user['name'])) {
                $this->info("Name: {$user['name']}");
            }
        } else {
            $this->error('Failed to retrieve user information: ' . ($response['message'] ?? 'Unknown error'));
            return self::FAILURE;
        }
    }
}
