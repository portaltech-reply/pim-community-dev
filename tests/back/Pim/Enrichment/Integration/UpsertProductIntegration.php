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

use Akeneo\Pim\Enrichment\Product\Api\Command\SetTextValue;
use Akeneo\Pim\Enrichment\Product\Api\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\Api\Command\ViolationsException;
use Akeneo\Pim\Enrichment\Product\Application\UpsertProductHandler;
use Akeneo\Test\Integration\TestCase;

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
        ]);

        try {
            ($handler)($command);
        } catch (ViolationsException) {
            // TODO: property path in violations?
            throw new \Exception('Validation error');
        }

//        $product = $this->get('pim_catalog.repository.product')->findOneByIdentifier();
    }
}
