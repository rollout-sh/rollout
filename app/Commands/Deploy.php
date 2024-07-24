<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class Deploy extends Command
{
    protected $signature = 'deploy {path? : The path to the project directory} {domain? : The custom domain to deploy to}';
    protected $description = 'Deploys your project to Rollout.sh, optionally to a specified domain';
    protected $client;

    public function __construct()
    {
        parent::__construct();
        
        $this->client = new Client([
            'base_uri' => config('app.api.baseUrl'),
            'timeout'  => 20.0,
            'headers' => [
                'Accept'     => 'application/json',
            ]
        ]);
    }

    public function handle()
    {
        $path = $this->argument('path') ?: getcwd();
        $domain = $this->argument('domain');

        if (!file_exists($path)) {
            $this->error("The specified path does not exist.");
            return self::FAILURE;
        }

        $this->info("Preparing to deploy project from: {$path}");

        $domainResult = $this->getOrGenerateDomain($domain);
        if (!$domainResult['success']) {
            $this->error("Failed to prepare the domain: " . $domainResult['error']);
            return self::FAILURE;
        }

        $domain = $domainResult['domain'];
        $this->info("Deploying to: {$domain}...");

        $zipPath = $this->compressProject($path);
        if (!$zipPath) {
            $this->error("Failed to compress the project.");
            return self::FAILURE;
        }

        $this->info("Project compressed successfully.");

        if ($this->uploadProject($zipPath, $domain)) {
            $this->info("Project deployed successfully to {$domain}.");
            @unlink($zipPath); // Clean up the temporary zip file
            return self::SUCCESS;
        } else {
            @unlink($zipPath); // Clean up the temporary zip file if deployment fails
            return self::FAILURE;
        }
    }

    private function getOrGenerateDomain($specifiedDomain)
    {
        if ($specifiedDomain) {
            return $this->validateDomain($specifiedDomain);
        }
        return $this->requestGeneratedDomain();
    }

    private function validateDomain($domain)
    {
        try {
            $response = $this->client->request('GET', config('app.api.basePath')."/".config('app.api.version')."/validate-domain?domain={$domain}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function requestGeneratedDomain()
    {
        try {
            $response = $this->client->request('GET', config('app.api.basePath')."/".config('app.api.version')."/generate-domain");
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function compressProject($path)
    {
        $ignorePatterns = $this->loadIgnorePatterns($path);
        $zip = new ZipArchive();
        $zipFilename = sys_get_temp_dir() . '/project_' . md5(time()) . '.zip';

        if ($zip->open($zipFilename, ZipArchive::CREATE) !== true) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        $totalSize = 0;
        $maxFileSize = 500 * 1024 * 1024; // 500 MB limit
        $maxFileCount = 10000; // Limit to 10,000 files

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($path) + 1);

            if ($this->shouldBeIgnored($relativePath, $ignorePatterns)) {
                continue;
            }

            if ($file->getSize() > $maxFileSize) {
                $this->error("File too large: {$relativePath}");
                return false;
            }

            $totalSize += $file->getSize();
            if ($totalSize > $maxFileSize || $fileCount++ > $maxFileCount) {
                $this->error("Project too large or too many files.");
                return false;
            }

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
        return $zipFilename;
    }

    private function shouldBeIgnored($path, $patterns) {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) return true;
        }
        return false;
    }

    private function loadIgnorePatterns($path) {
        $ignoreFile = $path . '/.rolloutignore';
        return file_exists($ignoreFile) ? file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    }


    private function uploadProject($filePath, $domain)
    {
        try {
            $response = $this->client->request('POST', config('app.api.basePath')."/".config('app.api.version'). "/deploy?domain={$domain}", [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($filePath, 'r')
                    ]
                ]
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);  // Decode the JSON response

            if ($data['success']) {
                $this->info($data['message']);  // Show success message from server
                return true;
            } else {
                $this->error("Deployment failed: " . $data['error']);  // Show error message from server
                return false;
            }
        } catch (GuzzleException $e) {
            $this->error("Failed to deploy: " . $e->getMessage());
            return false;
        }
    }

}
