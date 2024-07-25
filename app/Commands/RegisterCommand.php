<?php

// app/Commands/RegisterCommand.php

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class RegisterCommand extends BaseCommand
{
    protected $signature = 'register';
    protected $description = 'Register a new user account';

    public function handle()
    {
        // Prompt for user input
        $email = $this->ask('Enter your email');
        $password = $this->secret('Enter your password'); // Uses 'secret' method to hide input for security

        // Optional: Add validation logic here if needed
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email format.");
            return self::FAILURE;
        }

        if (empty($password) || strlen($password) < 8) {
            $this->error("Password must be at least 8 characters long.");
            return self::FAILURE;
        }

        $response = $this->makeApiRequest('/register', 'POST', [
            'email' => $email,
            'password' => $password
        ]);

        if ($response && $response['success']) {
            $this->saveToken($response['data']['token']);
            $this->info('Registration successful!');
        } else {
            $this->error('Failed to register: ' . ($response['message'] ?? 'Unknown error'));
            if (!empty($response['errors'])) {
                foreach ($response['errors'] as $field => $messages) {
                    foreach ((array) $messages as $message) {
                        $this->error($field . ': ' . $message);
                    }
                }
            }
            return self::FAILURE;
        }
    }
}
