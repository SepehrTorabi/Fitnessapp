<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\FoodDiaryData;
use App\Import\FoodDiaryImporter;
use App\Import\ManualDiaryData;
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
    description: 'Import diary days for Sepehr and Cosima - the Essenstagebuch PDF, or days entered by hand since.',
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
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Which set of days: "pdf" or "manual"', 'pdf')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only check the data; write nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $source = (string) $input->getOption('source');

        $dataset = match ($source) {
            'pdf' => ['Essenstagebuch PDF (17.08. - 14.09.2026)', FoodDiaryData::DAYS, FoodDiaryData::UNIT_WEIGHTS],
            'manual' => ['Days entered by hand', ManualDiaryData::DAYS, ManualDiaryData::UNIT_WEIGHTS],
            default => null,
        };

        if (null === $dataset) {
            $io->error(\sprintf('Unknown source "%s". Use "pdf" or "manual".', $source));

            return Command::FAILURE;
        }

        [$label, $days, $weights] = $dataset;

        $io->title(\sprintf('Food diary import - %s', $label));

        $this->importer->using($days, $weights);

        // Nothing is written until the items agree with each day's own stated
        // total - a mistyped figure should fail the import, not land in
        // somebody's diary.
        $problems = $this->importer->validate();

        if ([] !== $problems) {
            $io->error(\sprintf('The data does not match its stated totals (%d problems):', \count($problems)));
            $io->listing(\array_slice($problems, 0, 25));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Checks out against every stated daily total (%d days).', \count($days)));

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
            $io->warning(\sprintf('%d day totals differ by more than the tolerance:', \count($result['deviations'])));
            $io->listing($result['deviations']);

            return Command::FAILURE;
        }

        $io->success('Every imported day matches its stated total.');

        return Command::SUCCESS;
    }
}
