<?php

namespace Rollout\Service;

use ZipArchive;

class DeploymentService
{
    public function deploy($appId, $filePath, $customDomain = null)
    {
        try {
            // Validate the application (dummy check, in reality, should check in DB or config)
            if (!$this->isValidAppId($appId)) {
                return [
                    'success' => false,
                    'message' => 'App ID is not valid or authorized',
                ];
            }

            // Handle domain logic (using a local list for simplicity)
            $subdomain = $this->generateSubdomain($appId);

            if ($customDomain && !$this->isValidCustomDomain($customDomain)) {
                return [
                    'success' => false,
                    'message' => 'Custom domain already exists or is invalid',
                ];
            }

            // Handle file upload
            $uploadResult = $this->handleFileUpload($filePath, $appId);

            if (!$uploadResult['success']) {
                return $uploadResult;
            }

            // Extract the zip file
            $this->extractDeployment($uploadResult['path'], $appId, $this->getNextVersion($appId));

            return [
                'success' => true,
                'message' => 'Deployment successful',
                'subdomain' => $subdomain,
                'customDomain' => $customDomain,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Deployment failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function isValidAppId($appId)
    {
        // Simulate checking the app ID, replace with actual logic
        return is_numeric($appId) && $appId > 0;
    }

    private function isValidCustomDomain($domain)
    {
        // Simulate domain validation, replace with actual logic
        $existingDomains = ['example.com', 'test.com']; // Simulate existing domains
        return !in_array($domain, $existingDomains);
    }

    private function generateSubdomain($appId)
    {
        // Simulate subdomain generation
        return "app-{$appId}.rollout.sh";
    }

    private function handleFileUpload($filePath, $appId)
    {
        try {
            // Simulate file upload by copying it to a deployment directory
            $destinationPath = __DIR__ . "/../deployments/$appId";
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $storedPath = $destinationPath . '/' . basename($filePath);
            copy($filePath, $storedPath);

            return [
                'success' => true,
                'path' => $storedPath,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload the deployment file',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function extractDeployment($path, $appId, $version)
    {
        $zip = new ZipArchive;
        $storagePath = __DIR__ . "/../deployments/$appId/$version";

        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        if ($zip->open($path) === true) {
            $zip->extractTo($storagePath);
            $zip->close();

            // Create symlink to the current deployment
            $currentSymlink = __DIR__ . "/../deployments/$appId/current";

            if (file_exists($currentSymlink)) {
                unlink($currentSymlink);
            }

            symlink($storagePath, $currentSymlink);
        } else {
            throw new \Exception('Failed to extract deployment.');
        }
    }

    private function getNextVersion($appId)
    {
        // Simulate versioning logic, replace with actual logic
        $deploymentDir = __DIR__ . "/../deployments/$appId";
        $versions = scandir($deploymentDir);
        $versionNumbers = array_filter($versions, 'is_numeric');
        return $versionNumbers ? max($versionNumbers) + 1 : 1;
    }
}
