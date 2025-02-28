<?php

declare(strict_types=1);

namespace Specification\Akeneo\Pim\Enrichment\Product\API\Command;

use Akeneo\Pim\Enrichment\Product\API\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\AddMultiSelectValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\ClearValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetBooleanValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetDateValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetFamily;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetMetricValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetNumberValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetTextareaValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetTextValue;
use PhpSpec\ObjectBehavior;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UpsertProductCommandSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(
            1,
            'identifier1',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            []
        );
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(UpsertProductCommand::class);
    }

    function it_can_be_constructed_with_value_intents()
    {
        $valueUserIntents = [
            new SetTextValue('name', null, null, 'foo'),
            new SetNumberValue('name', null, null, 10),
            new SetMetricValue('power', null, null, '100', 'KILOWATT'),
            new SetTextareaValue('name', null, null, "<p><span style=\"font-weight: bold;\">title</span></p><p>text</p>"),
            new ClearValue('name', null, null),
            new SetBooleanValue('name', null, null, true),
            new SetDateValue('name', null, null, new \DateTime("2022-03-04T09:35:24+00:00")),
            new AddMultiSelectValue('name', null, null, ['optionA']),
        ];
        $this->beConstructedWith(
            1,
            'identifier1',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $valueUserIntents
        );
        $this->userId()->shouldReturn(1);
        $this->productIdentifier()->shouldReturn('identifier1');
        $this->valueUserIntents()->shouldReturn($valueUserIntents);
    }

    function it_cannot_be_constructed_with_bad_value_user_intent()
    {
        $this->beConstructedWith(
            1,
            '',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            [new \stdClass]
        );
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }

    function it_can_be_constructed_with_field_user_intents()
    {
        $familyUserIntent = new SetFamily('accessories');
        $this->beConstructedWith(
            1,
            'identifier1',
            null,
            $familyUserIntent,
            null,
            null,
            null,
            null,
            null,
            []
        );
        $this->userId()->shouldReturn(1);
        $this->productIdentifier()->shouldReturn('identifier1');
        $this->familyUserIntent()->shouldReturn($familyUserIntent);
        $this->valueUserIntents()->shouldReturn([]);
    }

    function it_can_be_constructed_from_a_collection_of_user_intents()
    {
        $familyUserIntent = new SetFamily('accessories');
        $setTextValue = new SetTextValue('name', null, null, 'foo');
        $setNumberValue = new SetNumberValue('name', null, null, 10);
        $setDateValue = new SetDateValue('name', null, null, new \DateTime("2022-03-04T09:35:24+00:00"));
        $addMultiSelectValue = new AddMultiSelectValue('name', null, null, ['optionA']);

        $this->beConstructedThrough('createFromCollection', [
            10,
            'identifier1',
            [$familyUserIntent, $setTextValue, $setNumberValue, $setDateValue, $addMultiSelectValue]
        ]);

        $this->userId()->shouldReturn(10);
        $this->productIdentifier()->shouldReturn('identifier1');
        $this->familyUserIntent()->shouldReturn($familyUserIntent);
        $this->valueUserIntents()->shouldReturn([$setTextValue, $setNumberValue, $setDateValue, $addMultiSelectValue]);
    }
}
