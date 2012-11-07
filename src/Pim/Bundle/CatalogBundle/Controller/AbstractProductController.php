<?php
namespace Pim\Bundle\CatalogBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pim\Bundle\CatalogBundle\Entity\ProductField;
use Pim\Bundle\CatalogBundle\Document\ProductFieldMongo;
use Pim\Bundle\CatalogBundle\Form\Type\ProductFieldType;
use APY\DataGridBundle\Grid\Source\Entity as GridEntity;
use APY\DataGridBundle\Grid\Source\Document as GridDocument;
use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Column\TextColumn;

/**
 * Controller independent of real product storage
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright Copyright (c) 2012 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
abstract class AbstractProductController extends Controller
{

    /**
     * Get used object manager
     */
    public function getObjectManagerService()
    {
        return $this->get('pim.catalog.product_manager')->getPersistenceManager();
    }

    /**
     * Return grid source for APY grid
     * @return APY\DataGridBundle\Grid\Source\Entity
     */
    public function getGridSource()
    {
        // source to create simple grid based on entity or document (ORM or ODM)
        if ($this->getObjectManagerService() instanceof \Doctrine\ODM\MongoDB\DocumentManager) {
            return new GridDocument($this->getObjectShortName());
        } else if ($this->getObjectManagerService() instanceof \Doctrine\ORM\EntityManager) {
            return new GridEntity($this->getObjectShortName());
        } else {
            throw new \Exception('Unknow object manager');
        }
    }

    /**
     * Get object class used by controller
     */
    public abstract function getObjectShortName();

    /**
     * Return full name of object class
     * @return unknown
     */
    public function getObjectClassFullName()
    {
        $om = $this->getObjectManagerService();
        $metadata = $om->getClassMetadata($this->getObjectShortName());
        $classFullName = $metadata->getName();
        return $classFullName;
    }

    /**
     * Return new instance of object
     * @return unknown
     */
    public function getNewObject()
    {
        $classFullName = $this->getObjectClassFullName();
        $entity = new $classFullName();
        return $entity;
    }
}
