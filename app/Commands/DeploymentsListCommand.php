<?php

// app/Commands/ListDeployments.php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class DeploymentsListCommand extends BaseCommand
{
    protected $signature = 'deployments:list {domain?}';
    protected $description = 'Lists all deployments for a specified domain';

    public function handle()
    {
        $domain = $this->argument('domain');

        try {
            $response = $this->makeApiRequest("/deployments?domain={$domain}");
            $deployments = $response['deployments'];

            foreach ($deployments as $deployment) {
                $this->line("Version: {$deployment['version']}, Domain: {$deployment['domain']}, Deployed at: {$deployment['deployed_at']}");
            }
        } catch (GuzzleException $e) {
            $this->error("Failed to retrieve deployments: " . $e->getMessage());
        }
    }
}
