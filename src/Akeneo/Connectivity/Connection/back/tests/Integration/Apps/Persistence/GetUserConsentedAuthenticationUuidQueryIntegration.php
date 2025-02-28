<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Tests\Integration\Apps\Persistence;

use Akeneo\Connectivity\Connection\back\tests\EndToEnd\WebTestCase;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Akeneo\Connectivity\Connection\Infrastructure\Apps\Persistence\GetUserConsentedAuthenticationUuidQuery;
use Akeneo\Connectivity\Connection\Infrastructure\Service\Clock\FakeClock;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\ConnectedAppLoader;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\ConnectionLoader;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\Enrichment\UserGroupLoader;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\UserConsentLoader;
use Ramsey\Uuid\Uuid;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GetUserConsentedAuthenticationUuidQueryIntegration extends WebTestCase
{
    private GetUserConsentedAuthenticationUuidQuery $getUserConsentedAuthenticationUuidQuery;
    private UserConsentLoader $userConsentLoader;
    private FakeClock $clock;
    private ConnectionLoader $connectionLoader;
    private ConnectedAppLoader $connectedAppLoader;
    private UserGroupLoader $groupLoader;


    protected function getConfiguration()
    {
        return $this->catalog->useMinimalCatalog();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->getUserConsentedAuthenticationUuidQuery = $this->get(GetUserConsentedAuthenticationUuidQuery::class);
        $this->userConsentLoader = $this->get(UserConsentLoader::class);
        $this->connectionLoader = $this->get('akeneo_connectivity.connection.fixtures.connection_loader');
        $this->connectedAppLoader = $this->get('akeneo_connectivity.connection.fixtures.connected_app_loader');
        $this->groupLoader = $this->get('akeneo_connectivity.connection.fixtures.enrichment.user_group_loader');
        $this->clock = $this->get('akeneo_connectivity.connection.clock');

        $this->clock->setNow(new \DateTimeImmutable('2021-03-02T04:30:11'));
    }

    public function test_it_gets_user_uuid_from_the_database(): void
    {
        $uuid = Uuid::uuid4();
        $user = $this->authenticateAsAdmin();
        $this->connectionLoader->createConnection(
            'connectionCode',
            'Connector',
            FlowType::DATA_DESTINATION,
            false
        );
        $this->groupLoader->create(['name' => 'app_group']);
        $this->connectedAppLoader->createConnectedApp(
            'random_app_id',
            'Random App',
            ['scope B1'],
            'connectionCode',
            'http://www.example.com/path/to/logo/b',
            'autho',
            'app_group',
            ['category B1'],
            true,
            null
        );
        $this->userConsentLoader->addUserConsent(
            $user->getId(),
            "random_app_id",
            ["a_scope"],
            $uuid,
            $this->clock->now()
        );

        $result = $this->getUserConsentedAuthenticationUuidQuery->execute($user->getId(), 'random_app_id');

        $this->assertEquals($uuid, $result);
    }

    public function test_it_throw_an_exception_if_there_is_no_uuid_into_the_database(): void
    {
        $user = $this->authenticateAsAdmin();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            \sprintf('Consent doesn\'t exist for user %s on app %s', $user->getId(), 'random_app_id')
        );

        $this->getUserConsentedAuthenticationUuidQuery->execute($user->getId(), 'random_app_id');
    }
}
