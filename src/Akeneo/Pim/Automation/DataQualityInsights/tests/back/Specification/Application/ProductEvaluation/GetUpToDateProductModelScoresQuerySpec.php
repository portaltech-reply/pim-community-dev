<?php

declare(strict_types=1);

namespace Specification\Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEvaluation;

use Akeneo\Pim\Automation\DataQualityInsights\Domain\Model\ChannelLocaleRateCollection;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Query\ProductEvaluation\GetProductModelScoresQueryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Query\ProductEvaluation\HasUpToDateEvaluationQueryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ChannelCode;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\LocaleCode;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductId;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\Rate;
use PhpSpec\ObjectBehavior;

/**
 * @copyright 2022 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GetUpToDateProductModelScoresQuerySpec extends ObjectBehavior
{
    public function let(
        HasUpToDateEvaluationQueryInterface $hasUpToDateEvaluationQuery,
        GetProductModelScoresQueryInterface $getProductModelScoresQuery
    )
    {
        $this->beConstructedWith($hasUpToDateEvaluationQuery, $getProductModelScoresQuery);
    }

    public function it_returns_the_product_model_scores_if_evaluation_for_product_id_is_up_to_date(
        $hasUpToDateEvaluationQuery,
        $getProductModelScoresQuery
    )
    {
        $productModelId = new ProductId(42);

        $scores = (new ChannelLocaleRateCollection())
            ->addRate(new ChannelCode('ecommerce'), new LocaleCode('en_US'), new Rate(100))
            ->addRate(new ChannelCode('ecommerce'), new LocaleCode('fr_FR'), new Rate(80));

        $hasUpToDateEvaluationQuery->forProductId($productModelId)->willReturn(true);
        $getProductModelScoresQuery->byProductModelId($productModelId)->willReturn($scores);

        $this->byProductModelId($productModelId)->shouldReturn($scores);
    }

    public function it_returns_empty_scores_if_evaluation_for_product_id_is_outdated(
        $hasUpToDateEvaluationQuery,
        $getProductModelScoresQuery
    )
    {
        $productModelId = new ProductId(42);

        $hasUpToDateEvaluationQuery->forProductId($productModelId)->willReturn(false);
        $getProductModelScoresQuery->byProductModelId($productModelId)->shouldNotBeCalled();

        $this->byProductModelId($productModelId)->shouldBeLike(new ChannelLocaleRateCollection());
    }
}
