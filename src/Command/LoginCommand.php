<?php
// src/Command/LoginCommand.php
namespace Rollout\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Rollout\Service\ApiClientService;
use Rollout\Service\AuthService;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'login',
    description: 'Log in to the service'
)]
class LoginCommand extends BaseCommand {

    private $authService;
    private $apiClientService;

    public function __construct(ConfigService $configService, AuthService $authService, ApiClientService $apiClientService)
    {
        $this->authService = $authService;
        $this->apiClientService = $apiClientService;
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

        $response = $this->apiClientService->makeApiRequest('POST', '/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ]
        ]);

        if ($response['message'] && $response['token']) {
            $config = $this->configService->readConfig();
            $config['token'] = $response['token'];
            $this->configService->writeConfig($config);
            $io->success('Logged in successfully!');
            return Command::SUCCESS;
        } else {
            $io->error($response['message']);
            return Command::FAILURE;
        }
    }
}
