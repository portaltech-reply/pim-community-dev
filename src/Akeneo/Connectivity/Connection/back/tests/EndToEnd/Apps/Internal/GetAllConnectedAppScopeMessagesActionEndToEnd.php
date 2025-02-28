<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Tests\EndToEnd\Apps\Internal;

use Akeneo\Connectivity\Connection\back\tests\EndToEnd\WebTestCase;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\ConnectedAppLoader;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\ConnectionLoader;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\Enrichment\UserGroupLoader;
use Akeneo\Connectivity\Connection\Tests\Integration\Mock\FakeFeatureFlag;
use Akeneo\Test\Integration\Configuration;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class GetAllConnectedAppScopeMessagesActionEndToEnd extends WebTestCase
{
    private FakeFeatureFlag $featureFlagMarketplaceActivate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureFlagMarketplaceActivate = $this->get('akeneo_connectivity.connection.marketplace_activate.feature');
    }

    protected function getConfiguration(): Configuration
    {
        return $this->catalog->useMinimalCatalog();
    }

    public function test_it_gets_all_connected_app_scope_messages(): void
    {
        $this->featureFlagMarketplaceActivate->enable();
        $this->authenticateAsAdmin();
        $this->addAclToRole('ROLE_ADMINISTRATOR', 'akeneo_connectivity_connection_manage_apps');

        $this->getConnectionLoader()->createConnection('connectionCodeA', 'Connector A', FlowType::DATA_DESTINATION, false);
        $this->getUserGroupLoader()->create(['name' => 'app_123456abcdef']);
        $this->getConnectedAppLoader()->createConnectedApp(
            '0dfce574-2238-4b13-b8cc-8d257ce7645b',
            'App A',
            ['write_association_types'],
            'connectionCodeA',
            'http://www.example.com/path/to/logo/a',
            'author A',
            'app_123456abcdef',
            ['category A1', 'category A2'],
            false,
            'partner A'
        );

        $expectedResult = [
            [
                'icon' => 'association_types',
                'type' => 'edit',
                'entities' => 'association_types',
            ]
        ];

        $this->client->request(
            'GET',
            '/rest/apps/connected-apps/connectionCodeA/scope-messages',
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ]
        );
        $response = $this->client->getResponse();
        $result = \json_decode($response->getContent(), true);

        Assert::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        Assert::assertEquals($expectedResult, $result);
    }

    private function getConnectionLoader(): ConnectionLoader
    {
        return $this->get('akeneo_connectivity.connection.fixtures.connection_loader');
    }

    private function getConnectedAppLoader(): ConnectedAppLoader
    {
        return $this->get('akeneo_connectivity.connection.fixtures.connected_app_loader');
    }

    private function getUserGroupLoader(): UserGroupLoader
    {
        return $this->get('akeneo_connectivity.connection.fixtures.enrichment.user_group_loader');
    }
}
