<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\Marketplace\Controller\Internal;

use Akeneo\Connectivity\Connection\Application\Marketplace\MarketplaceAnalyticsGenerator;
use Akeneo\Connectivity\Connection\Domain\Marketplace\GetAllExtensionsQueryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class GetAllExtensionsAction
{
    public function __construct(
        private GetAllExtensionsQueryInterface $getAllExtensionsQuery,
        private MarketplaceAnalyticsGenerator $marketplaceAnalyticsGenerator,
        private UserContext $userContext,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        try {
            $result = $this->getAllExtensionsQuery->execute();
        } catch (\Exception $e) {
            $this->logger->error(\sprintf('unable to retrieve the list of extensions, got error "%s"', $e->getMessage()));

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $username = $this->userContext->getUser()->getUsername();
        $analyticsQueryParameters = $this->marketplaceAnalyticsGenerator->getExtensionQueryParameters($username);
        $result = $result->withAnalytics($analyticsQueryParameters);

        return new JsonResponse($result->normalize());
    }
}
