<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Application\ProductEvaluation;

use Akeneo\Pim\Automation\DataQualityInsights\Domain\Model\Write;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Repository\CriterionEvaluationRepositoryInterface;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\CriterionCode;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\CriterionEvaluationStatus;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\ValueObject\ProductIdCollection;

/**
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CreateCriteriaEvaluations
{
    public function __construct(
        private CriteriaEvaluationRegistry $criteriaEvaluationRegistry,
        private CriterionEvaluationRepositoryInterface $criterionEvaluationRepository
    ) {
    }

    public function createAll(ProductIdCollection $productIdCollection): void
    {
        $this->create($this->criteriaEvaluationRegistry->getCriterionCodes(), $productIdCollection);
    }

    /**
     * @param CriterionCode[] $criterionCodes
     * @param ProductIdCollection $productIdCollection
     */
    public function create(array $criterionCodes, ProductIdCollection $productIdCollection): void
    {
        $criteria = new Write\CriterionEvaluationCollection();

        foreach ($productIdCollection as $productId) {
            foreach ($criterionCodes as $criterionCode) {
                $criteria->add(new Write\CriterionEvaluation(
                    $criterionCode,
                    $productId,
                    CriterionEvaluationStatus::pending()
                ));
            }
        }

        $this->criterionEvaluationRepository->create($criteria);
    }
}
