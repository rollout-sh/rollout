<?php
// src/Service/AuthService.php

namespace Rollout\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AuthService
{
    private $configService;
    private $client;
    private $apiUrl = 'https://app.rollout.sh.test/api/v1'; // Update with your API URL

    public function __construct(ConfigService $configService) {
        $this->configService = $configService;
        $this->client = new Client();
    }

    public function register($email, $password) {
        try {
            $response = $this->client->post("{$this->apiUrl}/auth/register", [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                ]
            ]);

            return $response->getStatusCode() === 201;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);

                return $data['message'] ?? 'An error occurred during registration.';
            }
            return 'An error occurred during registration.';
        }
    }

    public function login($email, $password) {
        try {
            $response = $this->client->post("{$this->apiUrl}/login", [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                $config = $this->configService->readConfig();
                $config['token'] = $data['token'];
                $this->configService->writeConfig($config);
                return true;
            }

            return 'Invalid credentials.';
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);

                return $data['message'] ?? 'An error occurred during login.';
            }
            return 'An error occurred during login.';
        }
    }
}
