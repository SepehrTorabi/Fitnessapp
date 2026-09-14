<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\FoodDiaryImporter;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off import of the "Essenstagebuch" PDF for the two accounts in it.
 *
 * Kept as a command rather than a migration or a fixture: it is historical data
 * for two specific people, not schema and not seed data every install wants.
 */
#[AsCommand(
    name: 'app:import:food-diary',
    description: 'Import the Essenstagebuch PDF (17.08.2026 - 14.09.2026) for Sepehr and Cosima.',
)]
final class ImportFoodDiaryCommand extends Command
{
    public function __construct(
        private readonly FoodDiaryImporter $importer,
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sepehr', null, InputOption::VALUE_REQUIRED, 'E-mail of the account for Sepehr', 'sepehrt74@gmail.com')
            ->addOption('cosima', null, InputOption::VALUE_REQUIRED, 'E-mail of the account for Cosima', 'chuesken1@gmail.com')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only check the transcription; write nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Food diary import');

        // Nothing is written until the transcription agrees with the PDF's own
        // per-day totals - a mistyped figure should fail the import, not land in
        // somebody's diary.
        $problems = $this->importer->validate();

        if ([] !== $problems) {
            $io->error(\sprintf('The data does not match the PDF totals (%d problems):', \count($problems)));
            $io->listing(\array_slice($problems, 0, 25));

            return Command::FAILURE;
        }

        $io->success('Transcription checks out against every "Summe" line in the PDF.');

        if ($input->getOption('dry-run')) {
            $io->note('Dry run - nothing written.');

            return Command::SUCCESS;
        }

        $users = [];

        foreach (['sepehr' => $input->getOption('sepehr'), 'cosima' => $input->getOption('cosima')] as $key => $email) {
            $user = $this->users->findOneByEmail((string) $email);

            if (null === $user) {
                $io->error(\sprintf('No account found for %s (%s).', $key, $email));

                return Command::FAILURE;
            }

            $users[$key] = $user;
            $io->text(\sprintf('  %s → %s (#%d)', $key, $user->getEmail(), $user->getId()));
        }

        $result = $this->importer->import($users);

        $io->newLine();
        $io->definitionList(
            ['Foods in the catalogue' => $result['foods']],
            ['Diary entries written' => $result['entries']],
            ['Activity entries written' => $result['activities']],
            ['Rows replaced from a previous run' => $result['replacedDays']],
        );

        if ([] !== $result['deviations']) {
            $io->warning(\sprintf('%d day totals differ from the PDF by more than the tolerance:', \count($result['deviations'])));
            $io->listing($result['deviations']);

            return Command::FAILURE;
        }

        $io->success('Every imported day matches the PDF.');

        return Command::SUCCESS;
    }
}
