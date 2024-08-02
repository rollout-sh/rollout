<?php

namespace Rollout\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ApiClientService {
    
    private $client;
    private $apiUrl;
    private $configService;

    public function __construct(ConfigService $configService, $apiUrl = 'https://app.rollout.sh.test/api/v1') {
        $this->configService = $configService;
        $this->apiUrl = $apiUrl;

        $this->client = new Client([
            'timeout' => 10.0, // Set a timeout for the requests
        ]);
    }

    public function makeApiRequest($method, $endpoint, $options = []) {
        // Set default headers and merge with any additional headers provided in $options
        $defaultHeaders = [
            'Accept' => 'application/json',
        ];

        // Check if token exists
        $config = $this->configService->readConfig();
        if (isset($config['token'])) {
            $defaultHeaders['Authorization'] = 'Bearer ' . $config['token'];
        }

        if (isset($options['headers'])) {
            $options['headers'] = array_merge($defaultHeaders, $options['headers']);
        } else {
            $options['headers'] = $defaultHeaders;
        }

        try {
            $response = $this->client->request($method, "{$this->apiUrl}{$endpoint}", $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                return json_decode($body, true);
            }
            return [
                'success' => false,
                'error' => 'An error occurred during the API request: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ];
        }
    }
}
