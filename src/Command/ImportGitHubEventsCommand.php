<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImportGitHubEventsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command must import GitHub events.
 * You can add the parameters and code you want in this command to meet the need.
 */
#[AsCommand(name: 'app:import-github-events', description: 'Import GitHub events')]
class ImportGitHubEventsCommand extends Command
{
    protected OutputInterface $output;
    protected InputInterface $input;
    protected SymfonyStyle $io;

    public function __construct(private ImportGitHubEventsService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force import by deleting all previous events')
            ->addOption('date', 'D', InputOption::VALUE_REQUIRED, 'Date to import events from. Format : Y-m-d')
            ->addOption('hour', 'H', InputOption::VALUE_REQUIRED, 'Date to import events from. Format : h')
            ->setDescription('Import GH events');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Importing GH events');

        if ($input->getOption('force')) {
            $this->io->section('Deleting all previous events ...');
            $this->service->deleteAll();
            $this->io->success('All previous events have been deleted.');
        }

        $date = null;
        if (null !== $dateString = $input->getOption('date')) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateString);
            if (false === $date) {
                $this->io->error('Invalid date format. Format : Y-m-d');

                return self::FAILURE;
            }
        }

        if (null !== $hour = $input->getOption('hour')) {
            if (null === $date) {
                $this->io->warning('You have not specified the date. The date will be set to today.');
                $date = new \DateTimeImmutable();
            }
            $date = $date->setTime(
                (int) $hour,
                0,
                0,
            );
            $this->import($date);
        } elseif (null !== $date) {
            $this->io->info('Importing events for the entire day.');
            for ($i = 0; $i < 24; ++$i) {
                $date = $date->setTime($i, 0, 0);
                try {
                    $this->import($date);
                } catch (\Throwable $e) {
                    $this->io->error('Error importing events for ' . $date->format('Y-m-d H'));
                }
            }
        } else {
            $this->import($date);
        }

        $this->io->success('Events imported successfully.');

        return self::SUCCESS;
    }

    protected function import(?\DateTimeImmutable $date): void
    {
        $this->io->section('Starting importing' . (null === $date ? '' : ' for ' . $date->format('Y-m-d H')));
        $progress = new ProgressIndicator($this->output);
        $progress->start('Importing...');
        $count = 0;
        foreach ($this->service->import($date) as $event) {
            ++$count;
            $progress->advance();
        }
        $progress->finish($count . ' events imported');
    }
}
