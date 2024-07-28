<?php
// src/Command/BaseCommand.php
namespace Rollout\Command;

use Symfony\Component\Console\Command\Command;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BaseCommand extends Command
{
    protected $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $io->title('Welcome to Rollout CLI');
        $io->text('Rollout CLI helps you manage your deployments and custom domains with ease.');
        $io->newLine();
    }

}
