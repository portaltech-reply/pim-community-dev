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
use Akeneo\Pim\Enrichment\Product\Api\Command\SetTextValue;
use Akeneo\Pim\Enrichment\Product\Api\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\Api\Command\ViolationsException;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpsertProductHandler
{
    public function __construct(
        private ValidatorInterface $validator,
        private ProductRepositoryInterface $productRepository,
        private ProductBuilderInterface $productBuilder,
        private SaverInterface $productSaver,
        private ObjectUpdaterInterface $productUpdater
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
            throw new ViolationsException($violations);
        }

        $product = $this->productRepository->findOneByIdentifier($command->productIdentifier());
        if (null === $product) {
            $product = $this->productBuilder->createProduct($command->productIdentifier());
        }

        if ($command->familyUserIntent()) {
            $this->productUpdater->update($product, ['family' => $command->familyUserIntent()]);
        }

        foreach ($command->valuesUserIntent() as $valueUserIntent) {
            if ($valueUserIntent instanceof SetTextValue) {
                $this->productUpdater->update($product, [
                    'values' => [
                        $valueUserIntent->attributeCode() => [
                            [
                                'locale' => $valueUserIntent->localeCode(),
                                'scope' => $valueUserIntent->channelCode(),
                                'data' => $valueUserIntent->value(),
                            ],
                        ],
                    ],
                ]);
            }
        }

        // Remove when possible
        $violations = $this->validator->validate($product);
        if (0 < $violations->count()) {
            throw new ViolationsException($violations);
        }

        $this->productSaver->save($product);
    }
}
