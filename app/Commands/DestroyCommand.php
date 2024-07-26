<?php

namespace App\Commands;

class DestroyCommand extends BaseCommand
{
    protected $signature = 'destroy {domain : The domain to destroy}';
    protected $description = 'Destroys the specified deployment';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $domain = $this->argument('domain');

        if (!$this->hasValidCredentials()) {
            $this->call('login');
            if (!$this->hasValidCredentials()) {
                $this->error('Authentication required to proceed.');
                return self::FAILURE;
            }
            $this->refreshClient();
        }

        $this->info("Preparing to destroy deployment for domain: {$domain}");

        $result = $this->destroyDeployment($domain);
        if (!$result['success']) {
            $error = isset($result['error']) ? $result['error'] : 'Unknown error';
            $this->error("Failed to destroy deployment: " . $error);
            return self::FAILURE;
        }

        $this->info("Deployment for domain {$domain} destroyed successfully.");

        // Clear domain configuration if it exists
        $path = $this->getConfigDirectory();
        if ($this->readConfig("{$path}_domain") === $domain) {
            $this->writeConfig("{$path}_domain", null);
        }

        return self::SUCCESS;
    }

    protected function destroyDeployment($domain) {
        // Logic to send a destroy request to the API
        try {
            $response = $this->makeApiRequest('/destroy', 'POST', ['domain' => $domain]);
            if (isset($response['success']) && $response['success']) {
                return ['success' => true];
            } else {
                $error = isset($response['error']) ? $response['error'] : 'Unknown error';
                return ['success' => false, 'error' => $error];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
