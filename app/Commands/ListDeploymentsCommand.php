<?php

// app/Commands/ListDeployments.php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ListDeploymentsCommand extends Command
{
    protected $signature = 'deployments:list {domain?}';
    protected $description = 'Lists all deployments for a specified domain';

    public function handle()
    {
        $domain = $this->argument('domain');

        $client = new Client([
            'base_uri' => config('app.api.baseUrl'),
        ]);

        try {
            $response = $client->request('GET', config('app.api.basePath') . "/" . config('app.api.version') ."/deployments?domain={$domain}");
            $deployments = json_decode($response->getBody()->getContents(), true)['deployments'];

            foreach ($deployments as $deployment) {
                $this->line("Version: {$deployment['version']}, Domain: {$deployment['domain']}, Deployed at: {$deployment['deployed_at']}");
            }
        } catch (GuzzleException $e) {
            $this->error("Failed to retrieve deployments: " . $e->getMessage());
        }
    }
}
