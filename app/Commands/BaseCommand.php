<?php
// app/Commands/BaseCommand.php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\File;

abstract class BaseCommand extends Command
{
    protected $client;

    public function __construct() {   
        parent::__construct();
        $this->refreshClient();
    }

    protected function refreshClient() {
        $this->client = new Client([
            'base_uri' => config('app.api.baseUrl'),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Accept' => 'application/json',
            ],
            'timeout' => 20.0,
        ]);
    }

    protected function getConfigDirectory() {
        $homeDirectory = getenv('HOME') ?: getenv('USERPROFILE');
        $configDirectory = $homeDirectory . '/.rollout';
        if (!file_exists($configDirectory)) {
            mkdir($configDirectory, 0700, true);
        }
        return $configDirectory;
    }

    protected function getToken() {
        $tokenFile = $this->getConfigDirectory() . '/auth_token.txt';
        return file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : null;
    }

    protected function saveToken($token) {
        $tokenFile = $this->getConfigDirectory() . '/auth_token.txt';
        file_put_contents($tokenFile, $token);
    }

    protected function deleteToken() {
        $tokenFile = $this->getConfigDirectory() . '/auth_token.txt';
        if (file_exists($tokenFile)) {
            unlink($tokenFile);
        }
    }

    protected function hasValidCredentials() {
        return $this->getToken() !== null;
    }

    protected function makeApiRequest($endpoint, $method = 'GET', $data = []) {
        try {
            $options = [];
            if ($method === 'POST' && !empty($data)) {
                $options['json'] = $data;
            } elseif (!empty($data)) {
                $options['query'] = $data;
            }

            $response = $this->client->request($method, config('app.api.basePath') . '/' . config('app.api.version') . $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);
                return [
                    'success' => false,
                    'statusCode' => $statusCode,
                    'message' => $body['message'] ?? 'Request failed',
                    'errors' => $body['errors'] ?? []
                ];
            } else {
                $this->error('API request failed: ' . $e->getMessage());
                return [
                    'success' => false,
                    'statusCode' => 500,
                    'message' => 'API request failed with no response',
                    'errors' => []
                ];
            }
        }
    }
}
