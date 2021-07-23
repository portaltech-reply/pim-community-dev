<?php

declare(strict_types=1);

namespace AkeneoMeasureBundle\PublicApi\Onboarder;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;

/**
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class FindAllMeasurementFamiliesIntegration extends TestCase
{
    private FindAllMeasurementFamilies $findAllMeasurementFamilies;

    public function setUp(): void
    {
        parent::setUp();
        $this->findAllMeasurementFamilies = $this->get(
            'akeneo_measurement.public_api.onboarder.find_all_measurement_families'
        );
    }

    /** @test */
    public function it_finds_all_measurements_families_for_onboarder()
    {
        $result = $this->findAllMeasurementFamilies->execute();
        $actualMeasurementFamily = current($result);

        self::assertNotEmpty($result, 'Expect to find at least the standard measurement families');
        self::assertEquals('Angle', $actualMeasurementFamily->code);
        self::assertEquals($this->expectedAngleLabels(), $actualMeasurementFamily->labels);
        self::assertEquals('RADIAN', $actualMeasurementFamily->standardUnitCode);
        self::assertEqualsCanonicalizing($this->expectedAngleUnits(), $actualMeasurementFamily->units);
    }

    protected function getConfiguration(): Configuration
    {
        return $this->catalog->useMinimalCatalog();
    }

    private function expectedAngleUnits(): array
    {
        return [
            [
                'code' => 'RADIAN',
                'labels' => [
                    'en_US' => 'Radian',
                    'fr_FR' => 'Radian',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '1',
                    ],
                ],
                'symbol' => 'rad',
            ],
            [
                'code' => 'MILLIRADIAN',
                'labels' => [
                    'en_US' => 'Milliradian',
                    'fr_FR' => 'Milliradian',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.001',
                    ],
                ],
                'symbol' => 'mrad',
            ],
            [
                'code' => 'MICRORADIAN',
                'labels' => [
                    'en_US' => 'Microradian',
                    'fr_FR' => 'Microradian',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.000001',
                    ],
                ],
                'symbol' => 'µrad',
            ],
            [
                'code' => 'DEGREE',
                'labels' => [
                    'en_US' => 'Degree',
                    'fr_FR' => 'Degré',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.01745329',
                    ],
                ],
                'symbol' => '°',
            ],
            [
                'code' => 'MINUTE',
                'labels' => [
                    'en_US' => 'Minute',
                    'fr_FR' => 'Minute',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.0002908882',
                    ],
                ],
                'symbol' => '\'',
            ],
            [
                'code' => 'SECOND',
                'labels' => [
                    'en_US' => 'Second',
                    'fr_FR' => 'Seconde',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.000004848137',
                    ],
                ],
                'symbol' => '"',
            ],
            [
                'code' => 'GON',
                'labels' => [
                    'en_US' => 'Gon',
                    'fr_FR' => 'Gon',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.01570796',
                    ],
                ],
                'symbol' => 'gon',
            ],
            [
                'code' => 'MIL',
                'labels' => [
                    'en_US' => 'Mil',
                    'fr_FR' => 'Mil',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '0.0009817477',
                    ],
                ],
                'symbol' => 'mil',
            ],
            [
                'code' => 'REVOLUTION',
                'labels' => [
                    'en_US' => 'Revolution',
                    'fr_FR' => 'Révolution',
                ],
                'convert_from_standard' => [
                    [
                        'operator' => 'mul',
                        'value' => '6.283185',
                    ],
                ],
                'symbol' => 'rev',
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function expectedAngleLabels(): array
    {
        return [
            'de_DE' => 'Winkel',
            'en_US' => 'Angle',
            'fr_FR' => 'Angle',
        ];
    }
}
