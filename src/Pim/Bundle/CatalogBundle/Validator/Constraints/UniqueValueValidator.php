<?php

namespace Pim\Bundle\CatalogBundle\Validator\Constraints;

use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for unique value constraint
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UniqueValueValidator extends ConstraintValidator
{
    /** @var ProductRepositoryInterface */
    protected $repository;

    /** @var array allows to keep the state */
    protected $productUniqueValues;

    /**
     * Constructor
     *
     * @param ProductRepositoryInterface $repository
     */
    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->productUniqueValues = [];
    }

    /**
     * Validates if the product value exists in database or if we already tried to validate such value for another
     * product to handle bulk updates
     *
     * It means that we make this validator stateful which is a bad news, the good one is we ensure this validation
     * for any processes (other option was to mess the import as we did with previous implementation)
     *
     * Due to constraint guesser, the constraint is applied on :
     * - ProductValueInterface data when applied through form
     * - ProductValueInterface when applied directly through validator
     *
     * The constraint guesser should be re-worked in a future version to avoid such behavior
     *
     * @param ProductValueInterface|mixed $data
     * @param Constraint                  $constraint
     *
     * @see Pim\Bundle\CatalogBundle\Validator\ConstraintGuesser\UniqueValueGuesser
     */
    public function validate($data, Constraint $constraint)
    {
        if (empty($data)) {
            return;
        }

        if (is_object($data) && $data instanceof ProductValueInterface) {
            $productValue = $data;
        } else {
            $productValue = $this->getProductValueFromForm();
        }

        if ($productValue instanceof ProductValueInterface && $productValue->getAttribute()->isUnique()) {
            $valueAlreadyExists = $this->alreadyExists($productValue);
            $valueAlreadyProcessed = $this->hasAlreadyValidatedTheSameValue($productValue);

            if ($valueAlreadyExists || $valueAlreadyProcessed) {
                $valueData = $this->formatData($productValue->getData());
                $attributeCode = $productValue->getAttribute()->getCode();
                if ($valueData !== null && $valueData !== '') {
                    $this->context->addViolation(
                        $constraint->message,
                        ['%value%' => $valueData, '%attribute%' => $attributeCode]
                    );
                }
            }
        }
    }

    /**
     * @param ProductValueInterface $productValue
     *
     * @return bool
     */
    protected function alreadyExists(ProductValueInterface $productValue)
    {
        return $this->repository->valueExists($productValue);
    }

    /**
     * Checks if the same exact value has already been processed (on a different product)
     *
     * When validates values for a VariantGroup there is not product related to the value
     *
     * @param ProductValueInterface $productValue
     *
     * @return bool
     */
    protected function hasAlreadyValidatedTheSameValue(ProductValueInterface $productValue)
    {
        $product = $productValue->getProduct();
        if ($product) {
            $productIdentifier = (string) $product->getIdentifier()->getData();
            $productValueData = $this->formatData($productValue->getData());
            $attributeCode = $productValue->getAttribute()->getCode();
            $uniqueValueCode = $attributeCode;
            $uniqueValueCode .= (null !== $productValue->getLocale()) ? $productValue->getLocale() : '';
            $uniqueValueCode .= (null !== $productValue->getScope()) ? $productValue->getScope() : '';

            if (isset($this->productUniqueValues[$uniqueValueCode][$productValueData])) {
                if ($this->productUniqueValues[$uniqueValueCode][$productValueData] !== $productIdentifier) {
                    return true;
                }
            }

            if (!isset($this->productUniqueValues[$uniqueValueCode])) {
                $this->productUniqueValues[$uniqueValueCode] = [];
            }

            if (!isset($this->productUniqueValues[$uniqueValueCode][$productValueData])) {
                $this->productUniqueValues[$uniqueValueCode][$productValueData] = $productIdentifier;
            }
        }

        return false;
    }

    /**
     * @param $mixed $data
     *
     * @return string
     */
    protected function formatData($data)
    {
        return ($data instanceof \DateTime) ? $data->format('Y-m-d') : (string) $data;
    }

    /**
     * Get product value from form
     *
     * @return ProductValueInterface|null
     */
    protected function getProductValueFromForm()
    {
        $root = $this->context->getRoot();
        if (!$root instanceof Form) {
            return;
        }

        preg_match(
            '/children\[values\].children\[(\w+)\].children\[\w+\].data/',
            $this->context->getPropertyPath(),
            $matches
        );
        if (!isset($matches[1])) {
            return;
        }

        $product = $this->context->getRoot()->getData();
        if (!$product instanceof ProductInterface) {
            return;
        }

        $value = $product->getValue($matches[1]);

        if (false === $value) {
            return;
        }

        return $value;
    }
}
