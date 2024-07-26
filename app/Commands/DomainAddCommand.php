<?php

namespace App\Commands;

class DomainAddCommand extends BaseCommand
{
    protected $signature = 'domain:add {domain : The domain to add} {deployment : The deployment identifier}';
    protected $description = 'Add a custom domain to a deployment. Example: domain:add example.com 123';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $domain = $this->argument('domain');
        $deployment = $this->argument('deployment');

        if (!$this->hasValidCredentials()) {
            $this->call('login');
            if (!$this->hasValidCredentials()) {
                $this->error('Authentication required to proceed. Please log in first.');
                return self::FAILURE;
            }
            $this->refreshClient();
        }

        $this->info("Attempting to add domain '{$domain}' to deployment with ID '{$deployment}'...");

        $result = $this->addDomainToDeployment($domain, $deployment);
        if (!$result['success']) {
            $this->error("Failed to add domain: " . $result['error']);
            return self::FAILURE;
        }

        $this->info("Domain '{$domain}' added to deployment '{$deployment}' successfully.");
        return self::SUCCESS;
    }

    protected function addDomainToDeployment($domain, $deployment) {
        try {
            $response = $this->makeApiRequest('/domains', 'POST', ['domain' => $domain, 'deployment' => $deployment]);
            if ($response['success']) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => $response['error']];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
