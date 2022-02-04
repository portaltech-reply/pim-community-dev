<?php

declare(strict_types=1);

/*
 * This file is part of the Akeneo PIM Enterprise Edition.
 *
 * (c) 2022 Akeneo SAS (https://www.akeneo.com)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Specification\Akeneo\Pim\Enrichment\Product\Infrastructure\AntiCorruptionLayer;

use Akeneo\Pim\Enrichment\Product\back\Domain\Query\IsUserLocaleGranted;
use Akeneo\Pim\Enrichment\Product\Domain\Model\Permission\AccessLevel;
use Akeneo\Pim\Enrichment\Product\Infrastructure\AntiCorruptionLayer\AclIsUserLocaleGranted;
use Akeneo\Pim\Permission\Component\Query\GetAccessGroupIdsForLocaleCode;
use Akeneo\Test\Pim\Enrichment\Product\Helper\FeatureHelper;
use Akeneo\UserManagement\Component\Model\UserInterface;
use Akeneo\UserManagement\Component\Repository\UserRepositoryInterface;
use PhpSpec\ObjectBehavior;

final class AclIsUserLocaleGrantedSpec extends ObjectBehavior
{
    function let($getAccessGroupIdsForLocaleCode, UserRepositoryInterface $userRepository)
    {
        FeatureHelper::skipSpecTestWhenPermissionFeatureIsNotActivated();

        $getAccessGroupIdsForLocaleCode->beADoubleOf(GetAccessGroupIdsForLocaleCode::class);
        $this->beConstructedWith($getAccessGroupIdsForLocaleCode, $userRepository);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(AclIsUserLocaleGranted::class);
        $this->shouldImplement(IsUserLocaleGranted::class);
    }

    function it_throws_an_exception_when_service_is_not_correctly_injected(UserRepositoryInterface $userRepository)
    {
        $this->beConstructedWith(null, $userRepository);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('forLocaleAndAccessLevel', [1, 'en_US', AccessLevel::EDIT_ITEMS]);
    }

    function it_throws_an_exception_when_user_is_unknown(UserRepositoryInterface $userRepository)
    {
        $userRepository->findOneBy(['id' => 1])->willReturn(null);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('forLocaleAndAccessLevel', [1, 'en_US', AccessLevel::EDIT_ITEMS]);
    }

    function it_returns_true_when_the_user_is_granted(
        $getAccessGroupIdsForLocaleCode,
        UserRepositoryInterface $userRepository,
        UserInterface $user
    ) {
        $user->getGroupsIds()->willReturn([10, 11]);
        $userRepository->findOneBy(['id' => 1])->willReturn($user);

        $getAccessGroupIdsForLocaleCode->getGrantedUserGroupIdsForLocaleCode('en_US', AccessLevel::EDIT_ITEMS)
            ->shouldBeCalledOnce()->willReturn([12, 11]);

        $this->forLocaleAndAccessLevel(1, 'en_US', AccessLevel::EDIT_ITEMS)->shouldReturn(true);
    }

    function it_returns_false_when_the_user_is_not_granted(
        $getAccessGroupIdsForLocaleCode,
        UserRepositoryInterface $userRepository,
        UserInterface $user
    ) {
        $user->getGroupsIds()->willReturn([10, 11]);
        $userRepository->findOneBy(['id' => 1])->willReturn($user);

        $getAccessGroupIdsForLocaleCode->getGrantedUserGroupIdsForLocaleCode('en_US', AccessLevel::EDIT_ITEMS)
            ->shouldBeCalledOnce()->willReturn([12, 13]);

        $this->forLocaleAndAccessLevel(1, 'en_US', AccessLevel::EDIT_ITEMS)->shouldReturn(false);
    }
}
