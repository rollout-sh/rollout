<?php
// src/Service/ConfigService.php

namespace Rollout\Service;

class ConfigService {

    private $configFile;

    public function __construct() {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE'); // Handle Windows and Unix-like OS
        $configDir = $homeDir . '/.rollout';

        if (!file_exists($configDir)) {
            mkdir($configDir, 0700, true);
        }

        $this->configFile = $configDir . '/config.json';
    }

    public function readConfig() {
        if (!file_exists($this->configFile)) {
            return [];
        }
        $json = file_get_contents($this->configFile);
        return json_decode($json, true);
    }

    public function writeConfig($data) {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->configFile, $json);
    }

    public function getActiveSubdomain() {
        $config = $this->readConfig();
        return $config['active_subdomain'] ?? null;
    }

    public function setActiveSubdomain($subdomain) {
        $config = $this->readConfig();
        $config['active_subdomain'] = $subdomain;
        $this->writeConfig($config);
    }

    public function saveConfigForPath($path, $data) {
        $config = $this->readConfig();

        // Normalize the path (convert to absolute path for consistency)
        $absolutePath = realpath($path);

        if ($absolutePath === false) {
            throw new \Exception("Invalid path: $path");
        }

        // Store the configuration for the specific path
        $config['deployments'][$absolutePath] = $data;

        $this->writeConfig($config);
    }

    public function getConfigForPath($path) {
        $config = $this->readConfig();

        // Normalize the path (convert to absolute path for consistency)
        $absolutePath = realpath($path);

        if ($absolutePath === false) {
            return null;
        }

        // Retrieve the configuration for the specific path
        return $config['deployments'][$absolutePath] ?? null;
    }
}
