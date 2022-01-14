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

namespace AkeneoTest\Pim\Enrichment\Integration;

use Akeneo\Pim\Enrichment\Product\Api\Command\AddMultiSelectOptions;
use Akeneo\Pim\Enrichment\Product\Api\Command\ClearValue;
use Akeneo\Pim\Enrichment\Product\Api\Command\LegacyViolationsException;
use Akeneo\Pim\Enrichment\Product\Api\Command\SetTextValue;
use Akeneo\Pim\Enrichment\Product\Api\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\Api\Command\ViolationsException;
use Akeneo\Pim\Enrichment\Product\Application\UpsertProductHandler;
use Akeneo\Test\Integration\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;

final class UpsertProductIntegration extends TestCase
{
    protected function getConfiguration()
    {
        return $this->catalog->useTechnicalCatalog();
    }

    public function test_it_upsert(): void
    {
        $handler = $this->get(UpsertProductHandler::class);

        $command = new UpsertProductCommand(userId: 1, productIdentifier: 'foo', familyUserIntent: 'familyA', valuesUserIntent: [
            new SetTextValue('a_text', null, null, 'toto'),
            new SetTextValue('a_text', null, null, 'tata'),
            new SetTextValue('a_text', null, null, 'titi'),
            new SetTextValue('a_text_area', null, null, 'a textarea'),
            new ClearValue('a_text_area', null, null),
            new AddMultiSelectOptions('a_multi_select', null, null, ['option3', 'optionB']),
        ]);

        try {
            ($handler)($command);
        } catch (ViolationsException $e) {
            var_dump('ViolationsException');
            var_dump(array_map(fn (ConstraintViolationInterface $violation) => [
                'message' => $violation->getMessage(),
                'path' => $violation->getPropertyPath(),
                'invalid_value' => $violation->getInvalidValue()
            ], $e->violations()->getIterator()->getArrayCopy()));
        } catch (LegacyViolationsException $e) {
            var_dump('LegacyViolationsException');
            var_dump(array_map(fn (ConstraintViolationInterface $violation) => [
                'message' => $violation->getMessage(),
                'path' => $violation->getPropertyPath(),
                'invalid_value' => $violation->getInvalidValue()
            ], $e->violations()->getIterator()->getArrayCopy()));
        }
    }
}
