<?php

declare(strict_types=1);

namespace Akeneo\Test\IntegrationTestsBundle\Loader;

use Akeneo\Connectivity\Connection\Application\Apps\Command\GenerateAsymmetricKeysCommand;
use Akeneo\Connectivity\Connection\Application\Apps\Command\GenerateAsymmetricKeysHandler;
use Akeneo\Pim\Enrichment\Component\Product\Storage\Indexer\ProductIndexerInterface;
use Akeneo\Pim\Enrichment\Component\Product\Storage\Indexer\ProductModelIndexerInterface;
use Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvent;
use Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvents;
use Akeneo\Platform\Bundle\InstallerBundle\FixtureLoader\FixtureJobLoader;
use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\IntegrationTestsBundle\Launcher\JobLauncher;
use Akeneo\Test\IntegrationTestsBundle\Security\SystemUserAuthenticator;
use Akeneo\Tool\Bundle\BatchBundle\Job\DoctrineJobRepository;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Akeneo\Tool\Bundle\ElasticsearchBundle\ClientRegistry;
use Akeneo\Tool\Bundle\MeasureBundle\Installer\MeasurementInstaller;
use Doctrine\DBAL\Connection;
use Elasticsearch\ClientBuilder;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Oro\Bundle\SecurityBundle\Acl\Persistence\AclManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @author    Yohan Blain <yohan.blain@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FixturesLoader implements FixturesLoaderInterface
{
    private DatabaseSchemaHandler $databaseSchemaHandler;
    private SystemUserAuthenticator $systemUserAuthenticator;
    private Application $cli;
    private ReferenceDataLoader $referenceDataLoader;
    private FilesystemOperator $archivistFilesystem;
    private DoctrineJobRepository $doctrineJobRepository;
    private FixtureJobLoader $fixtureJobLoader;
    private AclManager $aclManager;
    private ProductIndexerInterface $productIndexer;
    private ProductModelIndexerInterface $productModelIndexer;
    private ClientRegistry $clientRegistry;
    private Connection $dbConnection;
    private string $databaseHost;
    private string $databaseName;
    private string $databaseUser;
    private string $databasePassword;
    private string $sqlDumpDirectory;
    private \Elasticsearch\Client $nativeElasticsearchClient;
    private MeasurementInstaller $measurementInstaller;
    private TransportInterface $transport;
    private EventDispatcherInterface $eventDispatcher;
    private JobLauncher $jobLauncher;
    private GenerateAsymmetricKeysHandler $generateAsymmetricKeysHandler;

    public function __construct(
        KernelInterface $kernel,
        DatabaseSchemaHandler $databaseSchemaHandler,
        SystemUserAuthenticator $systemUserAuthenticator,
        ReferenceDataLoader $referenceDataLoader,
        FilesystemOperator $archivistFilesystem,
        DoctrineJobRepository $doctrineJobRepository,
        FixtureJobLoader $fixtureJobLoader,
        AclManager $aclManager,
        ProductIndexerInterface $productIndexer,
        ProductModelIndexerInterface $productModelIndexer,
        ClientRegistry $clientRegistry,
        Connection $dbConnection,
        MeasurementInstaller $measurementInstaller,
        TransportInterface $transport,
        EventDispatcherInterface $eventDispatcher,
        JobLauncher $jobLauncher,
        string $databaseHost,
        string $databaseName,
        string $databaseUser,
        string $databasePassword,
        string $sqlDumpDirectory,
        string $elasticsearchHost,
        GenerateAsymmetricKeysHandler $generateAsymmetricKeysHandler
    ) {
        $this->databaseSchemaHandler = $databaseSchemaHandler;
        $this->systemUserAuthenticator = $systemUserAuthenticator;
        $this->referenceDataLoader = $referenceDataLoader;

        $this->cli = new Application($kernel);
        $this->cli->setAutoExit(false);

        $this->archivistFilesystem = $archivistFilesystem;
        $this->doctrineJobRepository = $doctrineJobRepository;
        $this->fixtureJobLoader = $fixtureJobLoader;
        $this->aclManager = $aclManager;
        $this->productIndexer = $productIndexer;
        $this->productModelIndexer = $productModelIndexer;
        $this->clientRegistry = $clientRegistry;
        $this->dbConnection = $dbConnection;
        $this->databaseHost = $databaseHost;
        $this->databaseName = $databaseName;
        $this->databaseUser = $databaseUser;
        $this->databasePassword = $databasePassword;
        $this->sqlDumpDirectory = $sqlDumpDirectory;
        $clientBuilder = new ClientBuilder();
        $clientBuilder->setHosts([$elasticsearchHost]);
        $this->nativeElasticsearchClient = $clientBuilder->build();
        $this->measurementInstaller = $measurementInstaller;
        $this->transport = $transport;
        $this->eventDispatcher = $eventDispatcher;
        $this->jobLauncher = $jobLauncher;
        $this->generateAsymmetricKeysHandler = $generateAsymmetricKeysHandler;
    }

    public function __destruct()
    {
        // close the connection created specifically for this repository
        // TODO: to remove when TIP-385 will be done
        $this->doctrineJobRepository->getJobManager()->getConnection()->close();
    }

    public function load(Configuration $configuration): void
    {
        $this->deleteAllDocumentsInElasticsearch();
        $this->databaseSchemaHandler->reset();

        $this->resetFilesystem();

        $files = $this->getFilesToLoad($configuration->getCatalogDirectories());
        $fixturesHash = $this->getHashForFiles($files);
        $dumpFile = $this->sqlDumpDirectory . $fixturesHash . '.sql';
        if (file_exists($dumpFile)) {
            $this->restoreDatabase($dumpFile);
            $this->indexProductModels();
            $this->indexProducts();
        } else {
            $this->loadData($configuration);
            $this->dumpDatabase($dumpFile);
            $this->purgeMessengerEvents();
        }

        $this->nativeElasticsearchClient->indices()->refresh(['index' => $this->getIndexNames()]);
        $this->clearAclCache();
        $this->jobLauncher->flushJobQueue();

        $this->systemUserAuthenticator->createSystemUser();

    }

    protected function purgeMessengerEvents()
    {
        while (!empty($envelopes = $this->transport->get())) {
            foreach ($envelopes as $envelope) {
                $this->transport->ack($envelope);
            }
        }
    }

    protected function loadData(Configuration $configuration): void
    {
        $files = $this->getFilesToLoad($configuration->getCatalogDirectories());
        $filesByType = $this->getFilesToLoadByType($files);

        $this->generateAsymmetricKeysHandler->handle(new GenerateAsymmetricKeysCommand());
        $this->measurementInstaller->createMeasurementTableAndStandardMeasurementFamilies();
        $this->loadSqlFiles($filesByType['sql']);
        $this->loadImportFiles($filesByType['import']);
        $this->loadReferenceData();
    }

    /**
     * Returns a unique hash for the specified set of files. If one the file changes, the hash changes.
     *
     * @param array $files
     *
     * @return string
     */
    protected function getHashForFiles(array $files): string
    {
        $realFiles = array_filter($files, function ($entry) { return is_file($entry); });
        $hashes = array_map('sha1_file', $realFiles);

        return sha1(implode(':', $hashes));
    }

    /**
     * Load SQL file directly by a regular SQL query
     *
     * @param array $files
     */
    protected function loadSqlFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->dbConnection->exec(file_get_contents($file));
        }
    }

    /**
     * Load import files via akeneo:batch:job
     *
     * @param array $files
     *
     * @throws \RuntimeException
     */
    protected function loadImportFiles(array $files): void
    {
        // prepare replace paths to use catalog paths and not the minimal fixtures path, please note that we can
        // have several files per job in case of Enterprise Catalog, for instance,
        // [
        //     'jobs' => [
        //         "/project/features/Context/catalog/footwear/jobs.yml"
        //         "/project/features/PimEnterprise/Behat/Context/../../../Context/catalog/footwear/jobs.yml"
        // ]
        $replacePaths = [];
        foreach ($files as $file) {
            $tokens = explode(DIRECTORY_SEPARATOR, $file);
            $fileName = array_pop($tokens);
            if (!isset($replacePaths[$fileName])) {
                $replacePaths[$fileName] = [];
            }
            $replacePaths[$fileName][] = $file;
        }

        // configure and load job instances in database
        $this->fixtureJobLoader->loadJobInstances('src/Akeneo/Platform/Bundle/InstallerBundle/Resources/fixtures/minimal', $replacePaths);

        // install the catalog via the job instances
        $jobInstances = $this->fixtureJobLoader->getLoadedJobInstances();
        foreach ($jobInstances as $jobInstance) {
            $input = new ArrayInput([
                'command'  => 'akeneo:batch:job',
                'code'     => $jobInstance->getCode(),
                '--no-log' => true,
                '-v'       => true
            ]);
            $output = new BufferedOutput();
            $exitCode = $this->cli->run($input, $output);

            if (0 !== $exitCode) {
                throw new \RuntimeException(sprintf('Catalog not installable! "%s"', $output->fetch()));
            }

            $this->eventDispatcher->dispatch(
                new InstallerEvent(null, $jobInstance->getCode(), [
                    'job_name' => $jobInstance->getJobName(),
                    'catalog' => 'minimal',
                ]),
                InstallerEvents::POST_LOAD_FIXTURE
            );
        }

        $this->fixtureJobLoader->deleteJobInstances();
    }

    /**
     * Load the reference data into the database.
     */
    protected function loadReferenceData(): void
    {
        $this->referenceDataLoader->load();
    }

    /**
     * Separate files to load by their type. They can be:
     *  - regular files to load from an import.
     *  - SQL files
     *
     * @param array $files
     *
     * @return array
     */
    protected function getFilesToLoadByType(array $files): array
    {
        $filesByType = [
            'sql' => [],
            'import' => [],
        ];

        foreach ($files as $filePath) {
            $realPathParts = pathinfo($filePath);

            // do not try to load files without extension
            if (!isset($realPathParts['extension'])) {
                continue;
            }

            switch ($realPathParts['extension']) {
                case 'sql':
                    $filesByType['sql'][] = $filePath;
                    break;
                case 'csv':
                case 'xls':
                case 'xlsx':
                case 'yml':
                case 'yaml':
                    $filesByType['import'][] = $filePath;
                    break;
                default:
                    break;
            }
        }

        return $filesByType;
    }

    /**
     * Get the list of catalog configuration file paths to load
     *
     * @param array $directories
     *
     * @throws \Exception if no files can be loaded
     *
     * @return array
     */
    protected function getFilesToLoad(array $directories): array
    {
        $rawFiles = [];
        foreach ($directories as $directory) {
            $rawFiles = array_merge($rawFiles, glob($directory.'/*'));
        }

        if (empty($rawFiles)) {
            throw new \Exception(
                sprintf(
                    'No catalog file to load found in "%s"',
                    implode(', ', $directories)
                )
            );
        }

        $files = [];
        foreach ($rawFiles as $rawFilePath) {
            if (false === $realFilePath = realpath($rawFilePath)) {
                continue;
            }
            $files[] = $realFilePath;
        }

        return $files;
    }

    /**
     * @param string $filepath
     */
    protected function dumpDatabase($filepath): void
    {
        $dir = dirname($filepath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->execCommand(
            [
                'mysqldump',
                '-h' . $this->databaseHost,
                '-u' . $this->databaseUser,
                '-p' . $this->databasePassword,
                '--no-create-info',
                '--quick',
                '--skip-add-locks',
                '--skip-disable-keys',
                '--complete-insert',
                $this->databaseName,
                '--result-file=' . $filepath,
            ]
        );
    }

    /**
     * @param string $filepath
     */
    protected function restoreDatabase($filepath): void
    {
        $this->dbConnection->exec(file_get_contents($filepath));
    }

    /**
     * @param string[] $arguments
     * @param int      $timeout
     *
     * @return string
     */
    protected function execCommand(array $arguments, $timeout = 120): string
    {
        $process = new Process($arguments);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Clear Oro cache about Acl.
     * This cache should be cleared when loading the fixtures with mysql dump.
     * It avoids inconsistency between the cache and the new data in the database.
     */
    protected function clearAclCache(): void
    {
        $this->aclManager->clearCache();
    }

    /**
     * We don't wait for the refresh to execute only once per test with `refreshES` method.
     */
    protected function indexProducts(): void
    {
        $query = 'SELECT identifier FROM pim_catalog_product';
        $productIdentifiers = $this->dbConnection->executeQuery($query)->fetchAll(\PDO::FETCH_COLUMN, 0);
        $this->productIndexer->indexFromProductIdentifiers($productIdentifiers);
    }

    /**
     * We don't wait for the refresh to execute only once per test with `refreshES` method.
     */
    protected function indexProductModels(): void
    {
        $query = 'SELECT code FROM pim_catalog_product_model';
        $productModelCodes = $this->dbConnection->executeQuery($query)->fetchAll(\PDO::FETCH_COLUMN, 0);
        $this->productModelIndexer->indexFromProductModelCodes($productModelCodes);
    }

    private function resetFilesystem(): void
    {
        $paths = $this->archivistFilesystem->listContents('/')->filter(
            fn (StorageAttributes $attributes): bool => $attributes instanceof DirectoryAttributes
        )->map(
            fn (DirectoryAttributes $attributes): string => $attributes->path()
        );

        foreach ($paths as $path) {
            $this->archivistFilesystem->deleteDirectory($path);
        }
    }

    private function deleteAllDocumentsInElasticsearch(): void
    {
        $indexNames = $this->getIndexNames();
        $this->nativeElasticsearchClient->indices()->refresh(['index' => $indexNames]);

        $this->nativeElasticsearchClient->deleteByQuery([
            'body' => [
                'query' => [
                    'match_all' => new \stdClass()
                ],
            ],
            'index' => $indexNames,
            'refresh' => true
        ]);
    }

    private function getIndexNames(): array
    {
        return array_map(
            fn (Client $client) => $client->getIndexName(),
            $this->clientRegistry->getClients()
        );
    }
}
