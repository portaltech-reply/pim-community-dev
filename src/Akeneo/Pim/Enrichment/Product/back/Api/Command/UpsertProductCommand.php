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

namespace Akeneo\Pim\Enrichment\Product\Api\Command;

final class UpsertProductCommand
{
    public function __construct(
        private int $userId,
        private string $productIdentifier,
        private $identifierUserIntent = null,
        private $familyUserIntent = null,
        private $categoryUserIntent = null,
        private $parentUserIntent = null,
        private $groupsUserIntent = null,
        private $enabledUserIntent = null,
        private $associationsUserIntent = null,
        private array $valuesUserIntent = []
    ) {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function productIdentifier(): string
    {
        return $this->productIdentifier;
    }

    public function familyUserIntent()
    {
        return $this->familyUserIntent;
    }
}
