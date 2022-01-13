<?php

namespace Akeneo\UserManagement\Bundle\Controller\Rest;

use Akeneo\UserManagement\Component\Model\Group;
use Akeneo\UserManagement\Component\Model\GroupInterface;
use Akeneo\UserManagement\Component\Repository\GroupRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * User group rest controller
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UserGroupController
{
    public function __construct(private GroupRepositoryInterface $groupRepository)
    {
    }

    /**
     * Get the list of all user groups
     *
     * @return JsonResponse all user groups
     */
    public function indexAction()
    {
        $userGroups = array_map(function (GroupInterface $group) {
            return [
                'name' => $group->getName(),
                'meta' => [
                    'id'      => $group->getId(),
                    'default' => 'All' === $group->getName()
                ]
            ];
        }, $this->groupRepository->findBy(['type' => Group::TYPE_DEFAULT]));

        return new JsonResponse($userGroups);
    }
}
