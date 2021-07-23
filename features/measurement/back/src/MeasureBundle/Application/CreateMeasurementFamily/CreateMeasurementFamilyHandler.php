<?php

declare(strict_types=1);

namespace AkeneoMeasureBundle\Application\CreateMeasurementFamily;

use AkeneoMeasureBundle\Event\MeasurementFamilyCreated;
use AkeneoMeasureBundle\Model\LabelCollection;
use AkeneoMeasureBundle\Model\MeasurementFamily;
use AkeneoMeasureBundle\Model\MeasurementFamilyCode;
use AkeneoMeasureBundle\Model\Operation;
use AkeneoMeasureBundle\Model\Unit;
use AkeneoMeasureBundle\Model\UnitCode;
use AkeneoMeasureBundle\Persistence\MeasurementFamilyRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @copyright 2020 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CreateMeasurementFamilyHandler
{
    private MeasurementFamilyRepositoryInterface $measurementFamilyRepository;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        MeasurementFamilyRepositoryInterface $measurementFamilyRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->measurementFamilyRepository = $measurementFamilyRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(CreateMeasurementFamilyCommand $saveMeasurementFamilyCommand): void
    {
        $units = $this->units($saveMeasurementFamilyCommand);
        $measurementFamilyCode = MeasurementFamilyCode::fromString($saveMeasurementFamilyCommand->code);
        $measurementFamily = MeasurementFamily::create(
            $measurementFamilyCode,
            LabelCollection::fromArray($saveMeasurementFamilyCommand->labels),
            UnitCode::fromString($saveMeasurementFamilyCommand->standardUnitCode),
            $units
        );

        $this->measurementFamilyRepository->save($measurementFamily);
        $this->eventDispatcher->dispatch(new MeasurementFamilyCreated($measurementFamilyCode));
    }

    private function units(CreateMeasurementFamilyCommand $saveMeasurementFamilyCommand): array
    {
        return array_map(
            function (array $unit) {
                $operations = array_map(
                    function (array $operation) {
                        return Operation::create(
                            $operation['operator'],
                            $operation['value']
                        );
                    },
                    $unit['convert_from_standard']
                );

                return Unit::create(
                    UnitCode::fromString($unit['code']),
                    LabelCollection::fromArray($unit['labels']),
                    $operations,
                    $unit['symbol']
                );
            },
            $saveMeasurementFamilyCommand->units
        );
    }
}
