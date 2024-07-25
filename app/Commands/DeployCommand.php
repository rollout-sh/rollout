<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class DeployCommand extends BaseCommand
{
    protected $signature = 'deploy {path? : The path to the project directory} {domain? : The custom domain to deploy to}';
    protected $description = 'Deploys your project to Rollout.sh, optionally to a specified domain';

    public function __construct()
    {
        parent::__construct();
        $this->setupClient();
    }

    public function handle() {
        $path = $this->argument('path') ?: getcwd();
        $domain = $this->argument('domain');

        if (!$this->hasValidCredentials()) {
            $this->call('login');
            if (!$this->hasValidCredentials()) {
                $this->error('Authentication required to proceed.');
                return self::FAILURE;
            }
        }

        $path = $this->argument('path') ?: getcwd();
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
            $response = $this->client->request('GET', config('app.api.basePath') . "/" . config('app.api.version') . "/validate-domain?domain={$domain}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function requestGeneratedDomain()
    {
        try {
            $response = $this->client->request('GET', config('app.api.basePath') . "/" . config('app.api.version') . "/generate-domain");
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

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $files = iterator_to_array($iterator, false); // Collect all files first to determine progress accurately
        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        $currentSize = 0;
        $maxFileSize = 500 * 1024 * 1024; // 500 MB limit
        $maxTotalSize = 5 * 1024 * 1024 * 1024; // 5 GB total limit
        $fileLimit = 0;

        foreach ($files as $file) {
            // Get relative path from the base path
            $relativePath = $iterator->getSubPathName();

            if ($this->shouldBeIgnored($relativePath, $ignorePatterns)) {
                $progressBar->advance();
                continue;
            }

            if ($file->getSize() > $maxFileSize) {
                $this->error("File too large: {$relativePath}");
                $zip->close();
                @unlink($zipFilename);
                $progressBar->finish();
                return false;
            }

            $currentSize += $file->getSize();
            if ($currentSize > $maxTotalSize || $fileLimit++ > count($files)) {
                $this->error("Project too large or too many files.");
                $zip->close();
                @unlink($zipFilename);
                $progressBar->finish();
                return false;
            }

            $zip->addFile($file->getRealPath(), $relativePath);
            $progressBar->advance();
        }

        $zip->close();
        $progressBar->finish();
        $this->info("\nCompression complete.");
        return $zipFilename;
    }


    private function shouldBeIgnored($path, $patterns)
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) return true;
        }
        return false;
    }

    private function loadIgnorePatterns($path)
    {
        $ignoreFile = $path . '/.rolloutignore';
        return file_exists($ignoreFile) ? file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    }


    private function uploadProject($filePath, $domain)
    {
        $fileSize = filesize($filePath);
        $progressBar = $this->output->createProgressBar($fileSize);
        $progressBar->start();

        try {
            $response = $this->client->request('POST', config('app.api.basePath') . "/" . config('app.api.version') . "/deploy?domain={$domain}", [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath)
                    ]
                ],
                'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use ($progressBar) {
                    if ($uploadTotal > 0) {
                        $progressBar->setProgress($uploadedBytes);
                    }
                }
            ]);

            $progressBar->finish();
            $this->info("\nUpload complete.");

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);  // Decode the JSON response

            if (isset($data['success']) && $data['success']) {
                $this->info($data['message']);  // Show success message from server
                return true;
            } else {
                $this->error("Deployment failed: " . ($data['error'] ?? 'Unknown error'));  // Show error message from server
                return false;
            }
        } catch (GuzzleException $e) {
            $progressBar->finish();
            $this->error("\nFailed to deploy: " . $e->getMessage());
            return false;
        }
    }
}
