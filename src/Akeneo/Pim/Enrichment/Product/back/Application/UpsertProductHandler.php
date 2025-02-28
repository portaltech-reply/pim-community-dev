<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Product\Application;

use Akeneo\Pim\Enrichment\Component\Product\Builder\ProductBuilderInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Product\API\Command\Exception\LegacyViolationsException;
use Akeneo\Pim\Enrichment\Product\API\Command\Exception\ViolationsException;
use Akeneo\Pim\Enrichment\Product\API\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\AddMultiSelectValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\ClearValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\RemoveFamily;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetBooleanValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetDateValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetFamily;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetMetricValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetMultiSelectValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetNumberValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetSimpleSelectValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetTextareaValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetTextValue;
use Akeneo\Pim\Enrichment\Product\Domain\Event\ProductWasCreated;
use Akeneo\Pim\Enrichment\Product\Domain\Event\ProductWasUpdated;
use Akeneo\Tool\Component\StorageUtils\Exception\PropertyException;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @experimental
 *
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class UpsertProductHandler
{
    public function __construct(
        private ValidatorInterface $validator,
        private ProductRepositoryInterface $productRepository,
        private ProductBuilderInterface $productBuilder,
        private SaverInterface $productSaver,
        private ObjectUpdaterInterface $productUpdater,
        private ValidatorInterface $productValidator,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(UpsertProductCommand $command): void
    {
        $violations = $this->validator->validate($command);
        if (0 < $violations->count()) {
            throw new ViolationsException($violations);
        }

        $product = $this->productRepository->findOneByIdentifier($command->productIdentifier());
        $isCreation = false;
        if (null === $product) {
            $isCreation = true;
            $product = $this->productBuilder->createProduct($command->productIdentifier());
        }

        $this->updateProduct($product, $command);

        // This product validation is legacy. We should remove it as soon all validations will
        // be migrated on the command validation.
        $violations = $this->productValidator->validate($product);
        if (0 < $violations->count()) {
            throw new LegacyViolationsException($violations);
        }

        $isUpdate = $product->isDirty();
        $this->productSaver->save($product);

        if ($isCreation) {
            $this->eventDispatcher->dispatch(new ProductWasCreated($product->getIdentifier()));
        } elseif ($isUpdate) {
            $this->eventDispatcher->dispatch(new ProductWasUpdated($product->getIdentifier()));
        }
    }

    private function updateProduct(ProductInterface $product, UpsertProductCommand $command): void
    {
        foreach ($command->valueUserIntents() as $index => $valueUserIntent) {
            $found = false;
            try {
                if ($valueUserIntent instanceof SetTextValue
                    || $valueUserIntent instanceof SetNumberValue
                    || $valueUserIntent instanceof SetTextareaValue
                    || $valueUserIntent instanceof SetBooleanValue
                    || $valueUserIntent instanceof SetSimpleSelectValue
                ) {
                    $found = true;
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
                } elseif ($valueUserIntent instanceof SetMetricValue) {
                    $found = true;
                    $this->productUpdater->update($product, [
                        'values' => [
                            $valueUserIntent->attributeCode() => [
                                [
                                    'locale' => $valueUserIntent->localeCode(),
                                    'scope' => $valueUserIntent->channelCode(),
                                    'data' => [
                                        'amount' => $valueUserIntent->amount(),
                                        'unit' => $valueUserIntent->unit(),
                                    ],
                                ],
                            ],
                        ],
                    ]);
                } elseif ($valueUserIntent instanceof SetMultiSelectValue) {
                    $found = true;
                    $this->productUpdater->update($product, [
                        'values' => [
                            $valueUserIntent->attributeCode() => [
                                [
                                    'locale' => $valueUserIntent->localeCode(),
                                    'scope' => $valueUserIntent->channelCode(),
                                    'data' => $valueUserIntent->values(),
                                ],
                            ],
                        ],
                    ]);
                } elseif ($valueUserIntent instanceof ClearValue) {
                    $found = true;
                    $this->productUpdater->update($product, [
                        'values' => [
                            $valueUserIntent->attributeCode() => [
                                [
                                    'locale' => $valueUserIntent->localeCode(),
                                    'scope' => $valueUserIntent->channelCode(),
                                    'data' => null,
                                ],
                            ],
                        ],
                    ]);
                } elseif ($valueUserIntent instanceof SetDateValue) {
                    $found = true;
                    $this->productUpdater->update($product, [
                        'values' => [
                            $valueUserIntent->attributeCode() => [
                                [
                                    'locale' => $valueUserIntent->localeCode(),
                                    'scope' => $valueUserIntent->channelCode(),
                                    'data' => $valueUserIntent->value()->format('Y-m-d'),
                                ],
                            ],
                        ],
                    ]);
                } elseif ($valueUserIntent instanceof AddMultiSelectValue) {
                    $found = true;
                    $formerValue = $product->getValue(
                        $valueUserIntent->attributeCode(),
                        $valueUserIntent->localeCode(),
                        $valueUserIntent->channelCode()
                    );

                    $values = null !== $formerValue ?
                        \array_unique(\array_merge($formerValue->getData(), $valueUserIntent->optionCodes())) :
                        $valueUserIntent->optionCodes();

                    $this->productUpdater->update($product, [
                        'values' => [
                            $valueUserIntent->attributeCode() => [
                                [
                                    'locale' => $valueUserIntent->localeCode(),
                                    'scope' => $valueUserIntent->channelCode(),
                                    'data' => $values,
                                ],
                            ],
                        ],
                    ]);
                }
            } catch (PropertyException $e) {
                $violations = new ConstraintViolationList([
                    new ConstraintViolation(
                        $e->getMessage(),
                        $e->getMessage(),
                        [],
                        $command,
                        "valueUserIntents[$index]",
                        $valueUserIntent
                    ),
                ]);

                throw new ViolationsException($violations);
            }

            if (!$found) {
                throw new \InvalidArgumentException(\sprintf('The "%s" intent cannot be handled.', get_class($valueUserIntent)));
            }
        }

        if (null !== $command->enabledUserIntent()) {
            $this->productUpdater->update($product, [
                'enabled' => $command->enabledUserIntent()->enabled(),
            ]);
        }

        $familyUserIntent = $command->familyUserIntent();
        if (null !== $familyUserIntent) {
            if ($familyUserIntent instanceof SetFamily || $familyUserIntent instanceof RemoveFamily) {
                try {
                    $familyData = $familyUserIntent instanceof SetFamily ? $familyUserIntent->familyCode() : null;
                    $this->productUpdater->update($product, ['family' => $familyData]);
                } catch (PropertyException $e) {
                    $violations = new ConstraintViolationList([
                        new ConstraintViolation(
                            $e->getMessage(),
                            $e->getMessage(),
                            [],
                            $command,
                            "familyUserIntent",
                            $familyUserIntent
                        ),
                    ]);

                    throw new ViolationsException($violations);
                }
            } else {
                throw new \InvalidArgumentException(\sprintf('The "%s" intent cannot be handled.', get_class($familyUserIntent)));
            }
        }
    }
}
