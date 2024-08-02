<?php
// src/Command/BaseCommand.php
namespace Rollout\Command;

use Rollout\Service\ApiClientService;
use Symfony\Component\Console\Command\Command;
use Rollout\Service\ConfigService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BaseCommand extends Command {

    protected $configService, $apiClientService;
    protected $isLoggingEnabled;

    public function __construct(ConfigService $configService, ApiClientService $apiClientService) {
        $this->configService = $configService;
        $this->apiClientService = $apiClientService;
        parent::__construct();
    }

    protected function configure() {
        $this
            ->addOption('verbose', null, InputOption::VALUE_NONE, 'Enable verbose output');
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);

        // Determine if logging should be enabled
        $this->isLoggingEnabled = getenv('APP_ENV') === 'development' || $input->getOption('verbose');

        if ($this->isLoggingEnabled) {
            $io->note('Logging is enabled.');
        }

        $io->text('
    ____        ____            __ 
   / __ \____  / / /___  __  __/ /_
  / /_/ / __ \/ / / __ \/ / / / __/
 / _, _/ /_/ / / / /_/ / /_/ / /_  
/_/ |_|\____/_/_/\____/\__,_/\__/  
                                   
');
        $io->writeln('<fg=blue>Rollout - Static Site Hosting for Developers on Steroids!</>');
        $io->newLine();
    }

}
