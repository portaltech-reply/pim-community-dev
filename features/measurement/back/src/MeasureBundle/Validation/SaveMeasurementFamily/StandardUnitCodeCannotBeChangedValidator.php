<?php

declare(strict_types=1);

namespace AkeneoMeasureBundle\Validation\SaveMeasurementFamily;

use AkeneoMeasureBundle\Application\SaveMeasurementFamily\SaveMeasurementFamilyCommand;
use AkeneoMeasureBundle\Exception\MeasurementFamilyNotFoundException;
use AkeneoMeasureBundle\Model\MeasurementFamily;
use AkeneoMeasureBundle\Model\MeasurementFamilyCode;
use AkeneoMeasureBundle\Persistence\MeasurementFamilyRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class StandardUnitCodeCannotBeChangedValidator extends ConstraintValidator
{
    /** @var MeasurementFamilyRepositoryInterface */
    private $measurementFamilyRepository;

    public function __construct(MeasurementFamilyRepositoryInterface $measurementFamilyRepository)
    {
        $this->measurementFamilyRepository = $measurementFamilyRepository;
    }

    /**
     * @param SaveMeasurementFamilyCommand $saveMeasurementFamilyCommand
     * @inheritDoc
     */
    public function validate($saveMeasurementFamilyCommand, Constraint $constraint)
    {
        try {
            $measurementFamily = $this->measurementFamilyRepository
                ->getByCode(MeasurementFamilyCode::fromString($saveMeasurementFamilyCommand->code));
        } catch (MeasurementFamilyNotFoundException $e) {
            return;
        }

        if ($saveMeasurementFamilyCommand->standardUnitCode !== $this->standardUnitCode($measurementFamily)) {
            $this->context->buildViolation(StandardUnitCodeCannotBeChanged::ERROR_MESSAGE)
                ->setParameter('%measurement_family_code%', $saveMeasurementFamilyCommand->code)
                ->atPath('standard_unit_code')
                ->setInvalidValue($saveMeasurementFamilyCommand->standardUnitCode)
                ->addViolation();
        }
    }

    private function standardUnitCode(MeasurementFamily $measurementFamily): string
    {
        return $measurementFamily->normalize()['standard_unit_code'];
    }
}
