<?php

// app/Commands/Login.php

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class LoginCommand extends BaseCommand
{
    protected $signature = 'login';
    protected $description = 'Log in to your Rollout.sh account';

    public function handle()
    {
        $email = $this->ask('Enter your email');
        $password = $this->secret('Enter your password');

        $client = new Client([
            'base_uri' => config('app.api.baseUrl'),
            'headers' => ['Accept' => 'application/json']
        ]);

        try {
            $response = $client->post(config('app.api.basePath') . '/' . config('app.api.version') . '/login', [
                'json' => ['email' => $email, 'password' => $password]
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['token'])) {
                $this->saveToken($data['token']);
                $this->info('Login successful!');
            } else {
                $this->error('Login failed. Checking for account existence...');
                if ($this->confirm('No account found with these credentials. Would you like to register?')) {
                    $this->call('register', ['email' => $email, 'password' => $password]);
                }
            }
        } catch (GuzzleException $e) {
            $this->error('Login failed: ' . $e->getMessage());
        }
    }
}
