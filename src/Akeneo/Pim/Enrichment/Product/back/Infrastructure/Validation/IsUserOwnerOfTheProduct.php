<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Product\Infrastructure\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class IsUserOwnerOfTheProduct extends Constraint
{
    public string $noAccessMessage = 'pim_enrich.product.validation.upsert.category_no_access_to_products';
    public string $shouldKeepOneOwnedCategoryMessage = 'pim_enrich.product.validation.upsert.should_keep_one_owned_category';

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
