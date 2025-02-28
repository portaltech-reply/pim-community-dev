<?php

declare(strict_types=1);

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Akeneo\Pim\Enrichment\Product\API\Command\UserIntent;

use Webmozart\Assert\Assert;

class SetMultiSelectValue implements ValueUserIntent
{
    /**
     * @param array<string> $values
     */
    public function __construct(
        private string $attributeCode,
        private ?string $channelCode,
        private ?string $localeCode,
        private array $values
    ) {
        Assert::notEmpty($values);
        Assert::allString($values);
        Assert::allStringNotEmpty($values);
    }

    public function attributeCode(): string
    {
        return $this->attributeCode;
    }

    public function localeCode(): ?string
    {
        return $this->localeCode;
    }

    public function channelCode(): ?string
    {
        return $this->channelCode;
    }

    /**
     * @return array<string>
     */
    public function values(): array
    {
        return $this->values;
    }
}
