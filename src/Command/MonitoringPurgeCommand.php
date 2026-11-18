<?php

namespace Wexample\SymfonyMonitoring\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Wexample\SymfonyMonitoring\Service\IncidentPurgeService;

/**
 * The retention applied, once a day on the Scheduler — or by hand.
 */
#[AsCommand(name: 'monitoring:purge', description: 'Delete the incidents closed for longer than the retention.')]
#[AsPeriodicTask('1 day', from: '03:15')]
class MonitoringPurgeCommand extends Command
{
    public function __construct(
        private readonly IncidentPurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $purged = $this->purgeService->purge();

        if (null === $purged) {
            $io->note('No retention: closed incidents are kept.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('%d incidents deleted.', $purged));

        return Command::SUCCESS;
    }
}
