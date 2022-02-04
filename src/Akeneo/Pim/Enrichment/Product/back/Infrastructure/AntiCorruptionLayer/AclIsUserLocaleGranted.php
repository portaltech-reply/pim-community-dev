<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Product\Infrastructure\AntiCorruptionLayer;

use Akeneo\Pim\Enrichment\Product\back\Domain\Query\IsUserLocaleGranted;
use Akeneo\Pim\Permission\Component\Query\GetAccessGroupIdsForLocaleCode;
use Akeneo\UserManagement\Component\Model\UserInterface;
use Akeneo\UserManagement\Component\Repository\UserRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class AclIsUserLocaleGranted implements IsUserLocaleGranted
{
    public function __construct(
        /* @phpstan-ignore-next-line */
        private ?GetAccessGroupIdsForLocaleCode $getAccessGroupIdsForLocaleCode,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function forLocaleAndAccessLevel(int $userId, string $localeCode, string $accessLevel): bool
    {
        Assert::notNull($this->getAccessGroupIdsForLocaleCode);

        /** @var UserInterface $user */
        $user = $this->userRepository->findOneBy(['id' => $userId]);
        Assert::notNull($user);

        $userGroupIds = $user->getGroupsIds();
        if ([] === $userGroupIds) {
            return false;
        }

        /* @phpstan-ignore-next-line */
        $grantedUserGroupIds = $this->getAccessGroupIdsForLocaleCode->getGrantedUserGroupIdsForLocaleCode(
            $localeCode,
            $accessLevel
        );

        return [] !== \array_intersect($userGroupIds, $grantedUserGroupIds);
    }
}
