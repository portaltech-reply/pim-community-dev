<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\Apps\Persistence;

use Akeneo\Connectivity\Connection\Domain\Apps\Model\ConnectedApp;

/**
 * @copyright 2022 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
trait DenormalizeConnectedAppTrait
{
    /**
     * @param array{
     *    id: string,
     *    name: string,
     *    scopes: string,
     *    connection_code: string,
     *    logo: string,
     *    author: string,
     *    user_group_name: string,
     *    categories: string,
     *    certified: bool,
     *    partner: ?string,
     * } $dataRow
     */
    private function denormalizeRow(array $dataRow): ConnectedApp
    {
        return new ConnectedApp(
            $dataRow['id'],
            $dataRow['name'],
            \json_decode($dataRow['scopes'], true),
            $dataRow['connection_code'],
            $dataRow['logo'],
            $dataRow['author'],
            $dataRow['user_group_name'],
            \json_decode($dataRow['categories'], true),
            (bool) $dataRow['certified'],
            $dataRow['partner'],
            (bool) ($dataRow['is_test_app'] ?? false),
        );
    }
}
