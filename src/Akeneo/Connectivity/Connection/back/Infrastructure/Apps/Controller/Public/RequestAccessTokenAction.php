<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\Apps\Controller\Public;

use Akeneo\Connectivity\Connection\Application\Apps\Service\CreateAccessTokenInterface;
use Akeneo\Connectivity\Connection\Domain\Apps\DTO\AccessTokenRequest;
use Akeneo\Platform\Bundle\FeatureFlagBundle\FeatureFlag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class RequestAccessTokenAction
{
    private FeatureFlag $featureFlag;
    private ValidatorInterface $validator;
    private CreateAccessTokenInterface $createAccessToken;

    public function __construct(
        FeatureFlag $featureFlag,
        ValidatorInterface $validator,
        CreateAccessTokenInterface $createAccessToken
    ) {
        $this->featureFlag = $featureFlag;
        $this->validator = $validator;
        $this->createAccessToken = $createAccessToken;
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->featureFlag->isEnabled()) {
            throw new NotFoundHttpException();
        }
        $accessTokenRequest = new AccessTokenRequest(
            $request->get('client_id', ''),
            $request->get('code', ''),
            $request->get('grant_type', ''),
            $request->get('code_identifier', ''),
            $request->get('code_challenge', '')
        );
        $violations = $this->validator->validate($accessTokenRequest);
        if ($violations->count() > 0) {
            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }

        $token = $this->createAccessToken->create(
            $accessTokenRequest->getClientId(),
            $accessTokenRequest->getAuthorizationCode()
        );

        return new JsonResponse($token, Response::HTTP_OK);
    }
}
