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

namespace Akeneo\Pim\Enrichment\Product\Application;

use Akeneo\Pim\Enrichment\Component\Product\Builder\ProductBuilderInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Product\Api\Command\UpsertProductCommand;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpsertProductHandler
{
    public function __construct(
        private ValidatorInterface $validator,
        private ProductRepositoryInterface $productRepository,
        private ProductBuilderInterface $productBuilder,
        private SaverInterface $productSaver
    ) {
    }

    public function __invoke(UpsertProductCommand $command): void
    {
        /**
         * TODO: validate permissions is required here.
         * If we can do that, then the permission are ok for the rest of the code (= use "without permissions" services)
         */
        $violations = $this->validator->validate($command);
        if (0 < $violations->count()) {
            throw \Exception('TODO type this exception');
        }

        $product = $this->productRepository->findOneByIdentifier($command->productIdentifier());
        if (null === $product) {
            $product = $this->productBuilder->createProduct($command->productIdentifier());
        }

        if ($command->familyUserIntent()) {
            // TODO: Apply family set
        }

        // Remove when possible
        $violations = $this->validator->validate($product);
        if (0 < $violations->count()) {
            throw \Exception('TODO type this exception');
        }

        $this->productSaver->save($product);
    }
}
