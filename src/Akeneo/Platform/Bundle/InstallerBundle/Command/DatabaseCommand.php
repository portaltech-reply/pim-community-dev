<?php

namespace Akeneo\Platform\Bundle\InstallerBundle\Command;

use Akeneo\Platform\Bundle\InstallerBundle\CommandExecutor;
use Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvent;
use Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvents;
use Akeneo\Platform\Bundle\InstallerBundle\FixtureLoader\FixtureJobLoader;
use Akeneo\Tool\Bundle\ElasticsearchBundle\ClientRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Process;

/**
 * Database preparing command
 * - creates database
 * - updates schema
 * - loads fixtures
 * - launches other command for database calculations (completeness calculation)
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DatabaseCommand extends Command
{
    protected static $defaultName = 'pim:installer:db';
    protected static $defaultDescription = 'Prepare database and load fixtures';

    const LOAD_ALL = 'all';
    const LOAD_BASE = 'base';

    private EntityManagerInterface $entityManager;
    private ClientRegistry $clientRegistry;
    protected Connection $connection;
    private FixtureJobLoader $fixtureJobLoader;
    private EventDispatcherInterface $eventDispatcher;

    protected ?CommandExecutor $commandExecutor;

    public function __construct(
        EntityManagerInterface $entityManager,
        ClientRegistry $clientRegistry,
        Connection $connection,
        FixtureJobLoader $fixtureJobLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->clientRegistry = $clientRegistry;
        $this->connection = $connection;
        $this->fixtureJobLoader = $fixtureJobLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addOption(
                'fixtures',
                null,
                InputOption::VALUE_REQUIRED,
                'Determines fixtures to load (can be just OroPlatform or all)',
                self::LOAD_ALL
            )
            ->addOption(
                'withoutIndexes',
                null,
                InputOption::VALUE_OPTIONAL,
                'Should the command setup the elastic search indexes',
                false
            )
            ->addOption(
                'withoutFixtures',
                null,
                InputOption::VALUE_OPTIONAL,
                'Should the command install any fixtures',
                false
            )
            ->addOption(
                'catalog',
                null,
                InputOption::VALUE_OPTIONAL,
                'Directory of the fixtures to install',
                'src/Akeneo/Platform/Bundle/InstallerBundle/Resources/fixtures/minimal'
            )
            ->addOption(
                'doNotDropDatabase',
                null,
                InputOption::VALUE_NONE,
                'Try to use an existing database if it already exists. Beware, the database data will still be deleted'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->commandExecutor = new CommandExecutor(
            $input,
            $output,
            $this->getApplication()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Prepare database schema</info>');

        // Needs to try if database already exists or not
        try {
            if (!$this->connection->isConnected()) {
                $this->connection->connect();
            }
            if ($input->getOption('doNotDropDatabase')) {
                $this->commandExecutor->runCommand('doctrine:schema:drop', ['--force' => true, '--full-database' => true]);
            } else {
                $this->commandExecutor->runCommand('doctrine:database:drop', ['--force' => true]);
            }
        } catch (ConnectionException $e) {
            $output->writeln('<error>Database does not exist yet</error>');
        }

        $this->commandExecutor->runCommand('doctrine:database:create', ['--if-not-exists' => true]);

        // Needs to close connection if always open
        if ($this->connection->isConnected()) {
            $this->connection->close();
        }

        $this->commandExecutor
            ->runCommand('doctrine:schema:create')
            ->runCommand(
                'doctrine:schema:update',
                ['--force' => true, '--no-interaction' => true]
            );

        if (false === $input->getOption('withoutIndexes')) {
            $this->resetElasticsearchIndex($output);
        }

        $entityManager = $this->entityManager;
        $entityManager->clear();

        $this->eventDispatcher->dispatch(
            new InstallerEvent($this->commandExecutor, null, [
                'catalog' => $input->getOption('catalog')
            ]),
            InstallerEvents::POST_DB_CREATE
        );

        // TODO: Should be in an event subscriber
        if (!$input->getOption('doNotDropDatabase')) {
            $this->createNotMappedTables($output);
        }

        $this->setLatestKnownMigration($input);

        if (false === $input->getOption('withoutFixtures')) {
            $this->eventDispatcher->dispatch(
                new InstallerEvent($this->commandExecutor, null, [
                    'catalog' => $input->getOption('catalog')
                ]),
                InstallerEvents::PRE_LOAD_FIXTURES
            );

            $this->loadFixturesStep($input, $output);

            $this->eventDispatcher->dispatch(
                new InstallerEvent($this->commandExecutor, null, [
                    'catalog' => $input->getOption('catalog')
                ]),
                InstallerEvents::POST_LOAD_FIXTURES
            );
        }

        // TODO: Should be in an event subscriber
        $this->launchCommands();

        return Command::SUCCESS;
    }

    /**
     * TODO: TIP-613: This should be done with a command.
     * TODO: TIP-613: This command should be able to drop/create indexes, and/or re-index products.
     */
    protected function resetElasticsearchIndex(OutputInterface $output)
    {
        $output->writeln('<info>Reset elasticsearch indexes</info>');

        $clients = $this->clientRegistry->getClients();

        foreach ($clients as $client) {
            $client->resetIndex();
        }
    }

    protected function createNotMappedTables(OutputInterface $output)
    {
        $output->writeln('<info>Create session table</info>');
        $sessionTableSql = "CREATE TABLE pim_session (
                `sess_id` VARBINARY(128) NOT NULL PRIMARY KEY,
                `sess_data` BLOB NOT NULL,
                `sess_time` INTEGER UNSIGNED NOT NULL,
                `sess_lifetime` INTEGER UNSIGNED NOT NULL
            ) COLLATE utf8mb4_bin, ENGINE = InnoDB;";
        $this->connection->exec($sessionTableSql);

        $output->writeln('<info>Create configuration table</info>');
        $configTableSql = "CREATE TABLE pim_configuration (
                `code` VARCHAR(128) NOT NULL PRIMARY KEY,
                `values` JSON NOT NULL
            ) COLLATE utf8mb4_unicode_ci, ENGINE = InnoDB;";
        $this->connection->exec($configTableSql);

        $output->writeln('<info>Create messenger table</info>');
        $messengerTableSql = "CREATE TABLE IF NOT EXISTS messenger_messages (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                `queue_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime)',
                `available_at` datetime NOT NULL COMMENT '(DC2Type:datetime)',
                `delivered_at` datetime DEFAULT NULL COMMENT '(DC2Type:datetime)',
                PRIMARY KEY (`id`),
                KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
                KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
                KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
        $this->connection->exec($messengerTableSql);

        $output->writeln('<info>Create one time task table</info>');
        $oneTimeTaskTableSql = <<<SQL
            CREATE TABLE IF NOT EXISTS pim_one_time_task (
                `code` VARCHAR(100) PRIMARY KEY,
                `status` VARCHAR(100) NOT NULL,
                `start_time` DATETIME,
                `end_time` DATETIME,
                `values` JSON NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->connection->exec($oneTimeTaskTableSql);
    }

    protected function loadFixturesStep(InputInterface $input, OutputInterface $output): DatabaseCommand
    {
        $catalog = $input->getOption('catalog');
        if ($input->getOption('env') === 'behat') {
            $input->setOption('fixtures', self::LOAD_BASE);
        }

        $output->writeln(
            sprintf(
                '<info>Load jobs for fixtures. (data set: %s)</info>',
                $catalog
            )
        );
        $this->fixtureJobLoader->loadJobInstances($input->getOption('catalog'));

        $jobInstances = $this->fixtureJobLoader->getLoadedJobInstances();
        foreach ($jobInstances as $jobInstance) {
            $params = [
                'code'       => $jobInstance->getCode(),
                '--no-debug' => true,
                '--no-log'   => true,
                '-v'         => true,
            ];

            $this->eventDispatcher->dispatch(
                new InstallerEvent($this->commandExecutor, $jobInstance->getCode(), [
                    'catalog' => $catalog
                ]),
                InstallerEvents::PRE_LOAD_FIXTURE
            );
            if ($input->getOption('verbose')) {
                $output->writeln(
                    sprintf(
                        'Please wait, the <comment>%s</comment> are processing...',
                        $jobInstance->getCode()
                    )
                );
            }
            $this->commandExecutor->runCommand('akeneo:batch:job', $params);
            $this->eventDispatcher->dispatch(
                new InstallerEvent($this->commandExecutor, $jobInstance->getCode(), [
                    'job_name' => $jobInstance->getJobName(),
                    'catalog' => $catalog,
                ]),
                InstallerEvents::POST_LOAD_FIXTURE
            );
        }
        $output->writeln('');


        $output->writeln('<info>Delete jobs for fixtures.</info>');
        $this->fixtureJobLoader->deleteJobInstances();

        return $this;
    }

    private function setLatestKnownMigration(InputInterface $input): void
    {
        $latestMigration = $this->getLatestMigration($input);

        $this->commandExecutor->runCommand('doctrine:migrations:sync-metadata-storage', ['-q' => true]);

        $this->commandExecutor->runCommand(
            'doctrine:migrations:version',
            ['version' => $latestMigration, '--add' => true, '--all' => true, '-q' => true]
        );
    }

    private function getLatestMigration(InputInterface $input): string
    {
        $params = ['bin/console', 'doctrine:migrations:latest'];

        $params[] = '--no-debug';

        if ($input->hasOption('env')) {
            $params[] = '--env';
            $params[] = $input->getOption('env');
        }

        if ($input->hasOption('verbose') && $input->getOption('verbose') === true) {
            $params[] = '--verbose';
        }

        $latestMigrationProcess = new Process($params);
        $latestMigrationProcess->run();

        if ($latestMigrationProcess->getExitCode() !== 0) {
            throw new \RuntimeException("Impossible to get the latest migration {$latestMigrationProcess->getErrorOutput()}");
        }

        return $latestMigrationProcess->getOutput();
    }

    protected function launchCommands(): void
    {
        $this->commandExecutor->runCommand('pim:versioning:refresh');
    }
}
