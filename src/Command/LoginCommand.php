<?php
// src/Command/LoginCommand.php

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

#[AsCommand(
    name: 'login',
    description: 'Log in to the service'
)]
class LoginCommand extends BaseCommand {

    public function __construct(ConfigService $configService, ApiClientService $apiClientService) {
        parent::__construct($configService, $apiClientService);
    }

    protected function configure() {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        if (!$email) {
            $email = $io->ask('Email');
        }

        if (!$password) {
            $password = $io->askHidden('Password');
        }

        if ($this->isLoggingEnabled) {
            $io->note('Attempting to log in with provided credentials.');
        }

        $response = $this->apiClientService->makeApiRequest('POST', '/auth/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ]
        ]);

        if ($this->isLoggingEnabled) {
            $io->note('API request completed.');
        }

        if ($response['success'] && $response['data']['token']) {
            $config = $this->configService->readConfig();
            $config['token'] = $response['data']['token'];
            $this->configService->writeConfig($config);
            $io->success('Logged in successfully!');
            if ($this->isLoggingEnabled) {
                $io->note('Token saved to configuration.');
            }
            return Command::SUCCESS;
        } else {
            $io->error($response['message'] ?? 'An error occurred during login.');
            if ($this->isLoggingEnabled) {
                $io->error('Login attempt failed.');
            }
            return Command::FAILURE;
        }
    }
}
