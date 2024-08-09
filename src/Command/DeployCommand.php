<?php
// src/Command/DeployCommand.php

namespace Rollout\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Rollout\Service\ApiClientService;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Exception;

#[AsCommand(
    name: 'deploy',
    description: 'Deploy an application'
)]
class DeployCommand extends BaseCommand {

    public function __construct(ConfigService $configService, ApiClientService $apiClientService) {
        parent::__construct($configService, $apiClientService);
    }

    protected function configure()
    {
        $this
            ->addOption('app', null, InputOption::VALUE_OPTIONAL, 'Name of the application to deploy')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Path to the application (default is current directory)')
            ->addOption('domain', null, InputOption::VALUE_OPTIONAL, 'Custom domain for the deployment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $appName = $input->getOption('app');
        $path = $input->getOption('path') ?: getcwd(); // Default to current directory
        $customDomain = $input->getOption('domain');

        if (!is_dir($path)) {
            $io->error('The specified path does not exist or is not a directory.');
            return Command::FAILURE;
        }

        if ($this->isLoggingEnabled) {
            $io->note("Deployment process started for path: $path");
        }

        try {
            // Load or initialize config
            $config = $this->configService->getConfigForPath($path);

            // print_r($config); die;

            $appId = $config['app_id'] ?? null;
            $domain = $config['domain'] ?? null;

            // 1. If config has config for the current folder, then use it
                // verify the application ownership by calling the api.
                    // if api response is valid, proceed with the deployment
                    // else show an error to the user
            // 2. else if does not have connfig, treat it as a new deployment
                // save variables, path, app, and domain from the config
                // if user passed any of these variables, then replace these

            // Step 1: Check if app_name is provided
            if ($appName) {
                // Verify or create the app using the API
                $appIdForAppName = $this->getAppIdByName($appName, $io);
                if (!$appIdForAppName) {
                    // check if appId is in thhe config
                    if(!$appId) {
                        $appId = $this->createApp($appName, $io);
                    }
                }
            } elseif(!$appId) {
                // Step 2: No app_name provided, create a new app
                $appId = $this->createApp(null, $io); // API will generate an app name
            }

            // Step 3: Check the config for existing settings
            if (!$appName && !$path) {
                $existingConfig = $this->configService->getConfigForPath($path);
                if ($existingConfig) {
                    $appId = $existingConfig['app_id'];
                    $customDomain = $existingConfig['domain'] ?? $customDomain;
                }
            }

            // Step 4: Handle domain logic
            if ($customDomain) {
                $this->handleCustomDomain($appId, $customDomain, $io);
            } else {
                $customDomain = $this->getOrCreateDomain($appId, $io);
            }

            // Step 5: Deploy the app
            $this->deployApp($appId, $path, $customDomain, $io);

            // Save deployment details in config
            $this->configService->saveConfigForPath($path, [
                'app_id' => $appId,
                'domain' => $customDomain,
            ]);

            $io->success('Deployment completed successfully!');
            if ($this->isLoggingEnabled) {
                $io->note('Deployment details saved in config.');
            }
            return Command::SUCCESS;

        } catch (Exception $e) {
            $io->error('An error occurred during deployment: ' . $e->getMessage());
            if ($this->isLoggingEnabled) {
                $io->error('Exception details: ' . $e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function getAppIdByName($appName, SymfonyStyle $io)
    {
        $response = $this->apiClientService->makeApiRequest('GET', "/apps/$appName");

        if ($response['success']) {
            if ($this->isLoggingEnabled) {
                $io->note('App found: ' . $response['data']['id']);
            }
            return $response['data']['id'];
        }

        $io->warning('App not found: ' . $appName);
        return null;
    }

    private function createApp($appName, SymfonyStyle $io)
    {
        $response = $this->apiClientService->makeApiRequest('POST', '/apps', [
            'json' => [
                'name' => $appName,
            ],
        ]);

        if ($response['success']) {
            $appId = $response['data']['id'];
            $io->success('App created successfully with ID: ' . $appId);
            return $appId;
        }

        throw new Exception('Failed to create app: ' . ($response['message'] ?? 'Unknown error'));
    }

    private function handleCustomDomain($appId, $customDomain, SymfonyStyle $io)
    {
        $response = $this->apiClientService->makeApiRequest('POST', "/apps/$appId/domains", [
            'json' => [
                'domain' => $customDomain,
            ],
        ]);

        if (!$response['success']) {
            throw new Exception('Failed to handle custom domain: ' . ($response['message'] ?? 'Unknown error'));
        }

        if ($this->isLoggingEnabled) {
            $io->note('Custom domain handled: ' . $customDomain);
        }
    }

    private function getOrCreateDomain($appId, SymfonyStyle $io)
    {
        $response = $this->apiClientService->makeApiRequest('POST', "/apps/$appId/domains");

        if ($response['success']) {
            $domain = $response['data']['domain']['name'];
            if ($this->isLoggingEnabled) {
                $io->note('Domain retrieved/created: ' . $domain);
            }
            return $domain;
        }

        throw new Exception('Failed to retrieve/create domain: ' . ($response['message'] ?? 'Unknown error'));
    }

    private function deployApp($appId, $path, $domain, SymfonyStyle $io)
    {
        $zipPath = $this->createDeploymentPackage($path);

        $response = $this->apiClientService->makeApiRequest('POST', "/deployments", [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($zipPath, 'r'),
                ],
                [
                    'name' => 'domain',
                    'contents' => $domain,
                ],
                [
                    'name' => 'app_id',
                    'contents' => $appId,
                ],
            ],
        ]);

        if (!$response['success']) {
            throw new Exception('Deployment failed: ' . ($response['message'] ?? 'Unknown error'));
        }

        if ($this->isLoggingEnabled) {
            $io->note('Deployment executed successfully.');
        }
    }

    private function createDeploymentPackage($path)
    {
        $zip = new \ZipArchive();
        $zipPath = sys_get_temp_dir() . '/deployment.zip';

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Failed to create deployment package.');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($path) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return $zipPath;
    }
}
