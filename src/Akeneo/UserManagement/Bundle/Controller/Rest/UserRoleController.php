<?php

namespace Akeneo\UserManagement\Bundle\Controller\Rest;

use Akeneo\UserManagement\Bundle\Context\UserContext;
use Akeneo\UserManagement\Component\Repository\RoleRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Role controller
 *
 * @author    Pierre Allard <pierre.allard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UserRoleController
{
    public function __construct(
        protected RoleRepositoryInterface $roleRepository,
        protected NormalizerInterface $normalizer,
        protected UserContext $userContext
    ) {
    }

    /**
     * @return JsonResponse
     */
    public function indexAction()
    {
        $queryBuilder = $this->roleRepository->getAllButAnonymousQB();
        $roles = $queryBuilder->getQuery()->execute();

        return new JsonResponse($this->normalizer->normalize(
            $roles,
            'internal_api',
            $this->userContext->toArray()
        ));
    }
}
