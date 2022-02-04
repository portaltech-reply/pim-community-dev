<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Product\Infrastructure\Validation;

use Akeneo\Pim\Enrichment\Product\Api\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\Api\Command\UserIntent\ValueUserIntent;
use Akeneo\Pim\Enrichment\Product\back\Domain\Query\IsUserLocaleGranted;
use Akeneo\Pim\Enrichment\Product\Domain\Model\Permission\AccessLevel;
use Akeneo\Pim\Enrichment\Product\Infrastructure\AntiCorruptionLayer\Feature;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class UserLocaleShouldBeGrantedValidator extends ConstraintValidator
{
    public function __construct(
        private IsUserLocaleGranted $isUserLocaleGranted,
        private Feature $feature
    ) {
    }

    public function validate($command, Constraint $constraint): void
    {
        Assert::isInstanceOf($command, UpsertProductCommand::class);
        Assert::isInstanceOf($constraint, UserLocaleShouldBeGranted::class);

        if (!$this->feature->isEnabled(Feature::PERMISSION)) {
            return;
        }

        /** @var ValueUserIntent $valueUserIntent */
        foreach ($command->valuesUserIntent() as $index => $valueUserIntent) {
            $localeCode = $valueUserIntent->localeCode();
            if (null !== $localeCode && !$this->isUserLocaleGranted->forLocaleAndAccessLevel(
                $command->userId(),
                $localeCode,
                AccessLevel::EDIT_ITEMS
            )) {
                $this->context->buildViolation($constraint->message)
                    ->atPath(\sprintf('valuesUserIntent[%s].localeCode', $index))
                    ->addViolation();
            }
        }
    }
}
