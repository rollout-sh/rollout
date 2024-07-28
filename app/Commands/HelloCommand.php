<?php

namespace App\Commands;

class HelloCommand extends BaseCommand
{
    protected $signature = 'hello';
    protected $description = '';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $this->line('

        ___       ____          __ 
        / _ \___  / / /__  __ __/ /_
       / , _/ _ \/ / / _ \/ // / __/
      /_/|_|\___/_/_/\___/\_,_/\__/ 
                                    
      
');
        $this->line('Hey you!');
        $this->line('');
        $this->line('<fg=blue>Thank you for chosing Rollout - Dead simple static site hosting for developers!</>');
        $this->line('');
        $this->line('My aim is to build a static site hosting provider that\'s simple and intuitive to use.
But with a twist - handling everythign from commandlinne!');
        $this->line('');
        $this->line('Support: support:list');
        $this->line('');
        $this->line('Website: <href=https://github.com;fg=blue;options=underscore>rollout.sh</>');
        $this->line('Getting Started: <href=https://github.com;fg=blue;options=underscore>rollout.sh/docs</>');
        return self::SUCCESS;
    }

    protected function addDomainToDeployment($domain) {
        try {
            $response = $this->makeApiRequest('/domains', 'POST', ['domain' => $domain]);
            if ($response['success']) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => $response['error']];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
