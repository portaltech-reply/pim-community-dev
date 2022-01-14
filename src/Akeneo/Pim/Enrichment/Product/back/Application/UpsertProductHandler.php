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
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Product\Api\Command\AddMultiSelectOptions;
use Akeneo\Pim\Enrichment\Product\Api\Command\ClearValue;
use Akeneo\Pim\Enrichment\Product\Api\Command\LegacyViolationsException;
use Akeneo\Pim\Enrichment\Product\Api\Command\SetTextValue;
use Akeneo\Pim\Enrichment\Product\Api\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\Api\Command\ViolationsException;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\PropertyAdderInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpsertProductHandler
{
    public function __construct(
        private ValidatorInterface $validator,
        private ProductRepositoryInterface $productRepository,
        private ProductBuilderInterface $productBuilder,
        private SaverInterface $productSaver,
        private ObjectUpdaterInterface $productUpdater,
        private PropertyAdderInterface $propertyAdder
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

        $this->updateProduct($product, $command);

        // Remove when possible
        $violations = $this->validator->validate($product);
        if (0 < $violations->count()) {
            throw new LegacyViolationsException($violations);
        }

        $this->productSaver->save($product);
    }

    private function updateProduct(ProductInterface $product, UpsertProductCommand $command)
    {
        if ($command->familyUserIntent()) {
            $this->productUpdater->update($product, ['family' => $command->familyUserIntent()]);
        }

        foreach ($command->valuesUserIntent() as $index => $valueUserIntent) {
            try {
                if ($valueUserIntent instanceof ClearValue) {
                    $this->productUpdater->update($product, [
                        'values' => [
                            $valueUserIntent->attributeCode() => [
                                [
                                    'locale' => $valueUserIntent->localeCode(),
                                    'scope' => $valueUserIntent->channelCode(),
                                    'data' => null, // Validate that all attribute can be set to null to empty the value (multiselect)
                                ],
                            ],
                        ],
                    ]);
                } elseif ($valueUserIntent instanceof SetTextValue) {
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
                } elseif ($valueUserIntent instanceof AddMultiSelectOptions) {
                    $this->propertyAdder->addData(
                        $product,
                        $valueUserIntent->attributeCode(),
                        $valueUserIntent->value(),
                        [
                            'locale' => $valueUserIntent->localeCode(),
                            'scope' => $valueUserIntent->channelCode(),
                        ]
                    );
                }
            } catch (\Exception $e) {
                $violation = new ConstraintViolationList([
                    new ConstraintViolation(
                        $e->getMessage(),
                        $e->getMessage(),
                        [],
                        $command,
                        "valueUserIntent[$index]",
                        $valueUserIntent
                    ),
                ]);

                /** It's really a legacy violations ???? */
                throw new LegacyViolationsException($violation);
            }
        }
    }
}
