<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Tests\CatalogBuilder;

use Akeneo\Connectivity\Connection\Application\Apps\Service\CreateConnectedAppInterface;
use Akeneo\Connectivity\Connection\Application\Apps\Service\CreateConnectionInterface;
use Akeneo\Connectivity\Connection\Application\Settings\Service\CreateUserInterface;
use Akeneo\Connectivity\Connection\Application\User\CreateUserGroupInterface;
use Akeneo\Connectivity\Connection\Domain\Marketplace\Model\App;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Akeneo\Connectivity\Connection\Infrastructure\Apps\OAuth\ClientProviderInterface;
use Akeneo\Connectivity\Connection\Tests\CatalogBuilder\Enrichment\UserRoleLoader;
use Akeneo\Tool\Bundle\ConnectorBundle\Doctrine\UnitOfWorkAndRepositoriesClearer;
use Akeneo\UserManagement\Component\Repository\UserRepositoryInterface;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use FOS\OAuthServerBundle\Storage\OAuthStorage;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ConnectedAppLoader
{
    private DbalConnection $dbalConnection;
    private UserRoleLoader $userRoleLoader;
    private OAuthStorage $OAuthStorage;
    private ClientProviderInterface $clientProvider;
    private CreateUserGroupInterface $createUserGroup;
    private CreateUserInterface $createUser;
    private CreateConnectionInterface $createConnection;
    private CreateConnectedAppInterface $createApp;
    private UserRepositoryInterface $userRepository;
    private UnitOfWorkAndRepositoriesClearer $unitOfWorkAndRepositoriesClearer;

    public function __construct(
        DbalConnection $dbalConnection,
        UserRoleLoader $userRoleLoader,
        OAuthStorage $OAuthStorage,
        ClientProviderInterface $clientProvider,
        CreateUserGroupInterface $createUserGroup,
        CreateUserInterface $createUser,
        CreateConnectionInterface $createConnection,
        CreateConnectedAppInterface $createApp,
        UserRepositoryInterface $userRepository,
        UnitOfWorkAndRepositoriesClearer $unitOfWorkAndRepositoriesClearer
    ) {
        $this->dbalConnection = $dbalConnection;
        $this->userRoleLoader = $userRoleLoader;
        $this->OAuthStorage = $OAuthStorage;
        $this->clientProvider = $clientProvider;
        $this->createUserGroup = $createUserGroup;
        $this->createUser = $createUser;
        $this->createConnection = $createConnection;
        $this->createApp = $createApp;
        $this->userRepository = $userRepository;
        $this->unitOfWorkAndRepositoriesClearer = $unitOfWorkAndRepositoriesClearer;
    }

    /**
     * @param string[] $categories
     * @param string[] $scopes
     */
    public function createConnectedApp(
        string $id,
        string $name,
        array $scopes,
        string $connectionCode,
        string $logo,
        string $author,
        string $userGroupName,
        array $categories,
        bool $certified,
        ?string $partner
    ): int {
        $query = <<<SQL
INSERT INTO akeneo_connectivity_connected_app(id, name, logo, author, partner, categories, certified, connection_code, scopes, user_group_name)
VALUES (:id, :name, :logo, :author, :partner, :categories, :certified, :connection_code, :scopes, :user_group_name)
SQL;

        return $this->dbalConnection->executeUpdate(
            $query,
            [
                'id' => $id,
                'name' => $name,
                'logo' => $logo,
                'author' => $author,
                'partner' => $partner,
                'categories' => $categories,
                'certified' => $certified,
                'connection_code' => $connectionCode,
                'scopes' => $scopes,
                'user_group_name' => $userGroupName,
            ],
            [
                'certified' => Types::BOOLEAN,
                'categories' => Types::JSON,
                'scopes' => Types::JSON,
            ]
        );
    }

    public function createConnectedAppWithUserAndTokens(string $id, string $code, array $scopes = ['read_products']): void
    {
        $marketplaceApp = App::fromWebMarketplaceValues([
            'id' => $id,
            'name' => $code,
            'logo' => 'http://example.com/logo.png',
            'author' => 'Akeneo',
            'url' => 'http://marketplace.akeneo.com/foo',
            'categories' => ['ecommerce'],
            'activate_url' => 'http://example.com/activate',
            'callback_url' => 'http://example.com/callback',
        ]);
        $client = $this->clientProvider->findOrCreateClient($marketplaceApp);
        $group = $this->createUserGroup->execute(\sprintf('app_%s', $code));
        $role = $this->userRoleLoader->create([
            'role' => \sprintf('ROLE_%s', \strtoupper($code)),
            'label' => $code,
            'type' => 'app',
        ]);
        $user = $this->createUser->execute(
            $code,
            $marketplaceApp->getName(),
            ' ',
            [$group->getName()],
            [$role->getRole()]
        );
        $connection = $this->createConnection->execute(
            $code,
            $marketplaceApp->getName(),
            FlowType::OTHER,
            $client->getId(),
            $user->id(),
        );
        $this->createApp->execute(
            $marketplaceApp,
            $scopes,
            $connection->code(),
            $group->getName()
        );

        $user = $this->findConnectionUser($code);

        $this->OAuthStorage->createAuthCode($code, $client, $user, '', null);
        $this->OAuthStorage->createAccessToken($code, $client, $user, null);
        $this->OAuthStorage->createRefreshToken($code, $client, $user, null);

        $this->unitOfWorkAndRepositoriesClearer->clear();
    }

    private function findConnectionUser(string $code): UserInterface
    {
        $query = <<<SQL
SELECT user_id
FROM akeneo_connectivity_connection
WHERE code = :code
SQL;

        $id = $this->dbalConnection->fetchOne($query, [
            'code' => $code,
        ]);

        return $this->userRepository->findOneById($id);
    }
}
