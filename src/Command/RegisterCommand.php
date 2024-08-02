<?php
// src/Command/RegisterCommand.php

namespace Rollout\Command;

use Rollout\Service\ApiClientService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Rollout\Service\AuthService;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'register',
    description: 'Registers a new user to the service'
)]
class RegisterCommand extends BaseCommand {

    private $authService;

    public function __construct(ConfigService $configService, ApiClientService $apiClientService) {
        parent::__construct($configService, $apiClientService);
        $this->authService = new AuthService($this->configService);
    }

    protected function configure() {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password');
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

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid email format.');
            return Command::FAILURE;
        }

        // Validate password length
        if (strlen($password) < 8) {
            $io->error('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        if ($this->isLoggingEnabled) {
            $io->note('Attempting to register user with provided credentials.');
        }

        $result = $this->authService->register($email, $password);

        if ($result === true) {
            $io->success('User registered successfully!');
            if ($this->isLoggingEnabled) {
                $io->note('Registration successful.');
            }
            return Command::SUCCESS;
        } else {
            $io->error($result);
            if ($this->isLoggingEnabled) {
                $io->note('Registration failed: ' . $result);
            }
            return Command::FAILURE;
        }
    }
}
