<?php
// src/Command/DeployCommand.php

namespace Rollout\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Rollout\Service\ApiClientService;
use Rollout\Service\AuthService;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use ZipArchive;

#[AsCommand(
    name: 'deploy',
    description: 'Deploy a static site'
)]
class DeployCommand extends BaseCommand {

    private $authService;
    private $apiClientService;

    public function __construct(ConfigService $configService, AuthService $authService, ApiClientService $apiClientService)
    {
        $this->authService = $authService;
        $this->apiClientService = $apiClientService;
        parent::__construct($configService);
    }

    protected function configure()
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the static site directory', getcwd())
            ->addOption('app', 'a', InputOption::VALUE_OPTIONAL, 'App name for the deployment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        $providedAppName = $input->getOption('app');

        // Check if token exists
        $config = $this->configService->readConfig();
        if (!isset($config['token'])) {
            $io->error('You are not logged in. Please log in first.');
            return Command::FAILURE;
        }

        // Automatically determine app name from current folder or provided option
        $appName = $this->determineAppName($path, $providedAppName);

        // Create or get the app
        $appId = $this->getOrCreateApp($appName, $config['token']);
        if (!$appId) {
            $io->error('Failed to create or get the app.');
            return Command::FAILURE;
        }

        // Create a zip of the deployment folder
        $zipPath = $this->createZip($path);

        // Upload the zip to the server
        $result = $this->uploadDeployment($zipPath, $appId, $config['token']);

        if ($result === true) {
            $io->success('Deployment successful!');
            return Command::SUCCESS;
        } else {
            $io->error($result);
            return Command::FAILURE;
        }
    }

    private function determineAppName($path, $providedAppName)
    {
        if ($providedAppName) {
            return $providedAppName;
        }

        // Determine app name from current folder
        $config = $this->configService->readConfig();
        $currentFolder = basename($path);

        if (!isset($config['apps'][$currentFolder])) {
            $response = $this->apiClientService->makeApiRequest('POST', '/generate-app-name');
            if ($response['success']) {
                $config['apps'][$currentFolder] = $response['appName'];
                $this->configService->writeConfig($config);
            } else {
                throw new \RuntimeException('Failed to generate a unique app name. Please try again.');
            }
        }

        return $config['apps'][$currentFolder];
    }

    private function getOrCreateApp($appName, $token)
    {
        $response = $this->apiClientService->makeApiRequest('POST', '/apps', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => [
                'name' => $appName,
            ]
        ]);

        if (!$response['success']) {
            return null;
        }

        // Fetch the latest app details
        $appId = $response['app']['id'];
        $appDetails = $this->fetchAppDetails($appId, $token);

        if (!$appDetails['success']) {
            error_log('Failed to fetch app details: ' . $appDetails['error']);
            return null;
        }

        // Update config with latest app details if necessary
        $config = $this->configService->readConfig();
        $config['apps'][$appName] = $appDetails['app'];
        $this->configService->writeConfig($config);

        return $appId;
    }

    private function fetchAppDetails($appId, $token)
    {
        return $this->apiClientService->makeApiRequest('GET', "/apps/{$appId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    private function createZip($path)
    {
        $zip = new ZipArchive();
        $zipPath = tempnam(sys_get_temp_dir(), 'deploy') . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot create zip file.');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($path) + 1);

                if (!$this->shouldIgnoreFile($relativePath)) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();
        return $zipPath;
    }

    private function shouldIgnoreFile($relativePath)
    {
        $ignoredPatterns = [
            '/\.git/',
            '/\.DS_Store/',
            '/\.rolloutignore/',
            '/node_modules/',
            '/.*\.php$/'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (preg_match($pattern, $relativePath)) {
                return true;
            }
        }

        // Check against .rolloutignore
        $ignoreFile = getcwd() . '/.rolloutignore';
        if (file_exists($ignoreFile)) {
            $ignoredFiles = file($ignoreFile, FILE_IGNORE_NEW_LINES);
            foreach ($ignoredFiles as $ignoredFile) {
                if (fnmatch($ignoredFile, $relativePath)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function uploadDeployment($zipPath, $appId, $token)
    {
        $response = $this->apiClientService->makeApiRequest('POST', '/deploy', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => fopen($zipPath, 'r'),
                    'filename' => basename($zipPath),
                ],
                [
                    'name'     => 'app_id',
                    'contents' => $appId,
                ],
            ]
        ]);

        return $response['success'] ? true : 'Deployment failed: ' . $response['error'];
    }
}
