<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEvaluation;

use Akeneo\Pim\Automation\DataQualityInsights\Application\Consolidation\ConsolidateProductModelScores;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Event\ProductModelsEvaluated;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductIdCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class EvaluateProductModels
{
    public function __construct(
        private EvaluatePendingCriteria       $evaluatePendingProductModelCriteria,
        private ConsolidateProductModelScores $consolidateProductModelScores,
        private EventDispatcherInterface      $eventDispatcher
    ) {
    }

    public function __invoke(ProductIdCollection $productModelIdCollection): void
    {
        $this->evaluatePendingProductModelCriteria->evaluateAllCriteria($productModelIdCollection);
        $this->consolidateProductModelScores->consolidate($productModelIdCollection);
        $this->eventDispatcher->dispatch(new ProductModelsEvaluated($productModelIdCollection));
    }
}
