<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\DataQualityInsights\Infrastructure\Persistence\Repository;

use Akeneo\Pim\Automation\DataQualityInsights\Domain\Model\Write;
use Akeneo\Pim\Automation\DataQualityInsights\Domain\Repository\ProductModelScoreRepositoryInterface;
use Doctrine\DBAL\Connection;
use Webmozart\Assert\Assert;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductModelScoreRepository implements ProductModelScoreRepositoryInterface
{
    public function __construct(
        private Connection $dbConnection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function saveAll(array $productsScores): void
    {
        if (empty($productsScores)) {
            return;
        }

        Assert::allIsInstanceOf($productsScores, Write\ProductScores::class);

        $queries = '';
        $queriesParameters = [];
        $queriesParametersTypes = [];

        foreach ($productsScores as $index => $productModelScore) {
            $productModelId = sprintf('productModelId_%d', $index);
            $evaluatedAt = sprintf('evaluatedAt_%d', $index);
            $scores = sprintf('scores_%d', $index);

            $queries .= <<<SQL
INSERT INTO pim_data_quality_insights_product_model_score (product_model_id, evaluated_at, scores)
VALUES (:$productModelId, :$evaluatedAt, :$scores)
ON DUPLICATE KEY UPDATE evaluated_at = :$evaluatedAt, scores = :$scores;
SQL;
            $queriesParameters[$productModelId] = $productModelScore->getProductId()->toInt();
            $queriesParametersTypes[$productModelId] = \PDO::PARAM_INT;
            $queriesParameters[$evaluatedAt] = $productModelScore->getEvaluatedAt()->format('Y-m-d');
            $queriesParameters[$scores] = \json_encode($productModelScore->getScores()->toNormalizedRates());
        }

        $this->dbConnection->executeQuery($queries, $queriesParameters, $queriesParametersTypes);
    }
}
