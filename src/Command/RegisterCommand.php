<?php
// src/Command/RegisterCommand.php
namespace Rollout\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Rollout\Service\AuthService;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'register',
    description: 'Register a new user account'
)]
class RegisterCommand extends BaseCommand {

    private $authService;

    public function __construct(ConfigService $configService, AuthService $authService)
    {
        $this->authService = $authService;
        parent::__construct($configService);
    }

    protected function configure()
    {
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

        $result = $this->authService->register($email, $password);

        if ($result === true) {
            $io->success('User registered successfully!');
            return Command::SUCCESS;
        } else {
            $io->error($result);
            return Command::FAILURE;
        }
    }
}
