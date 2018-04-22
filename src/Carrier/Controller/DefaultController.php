<?php

namespace Carrier\Controller;

use Carrier\Model\CarrierModel;
use Core\Model\ErrorModel;


class DefaultController extends \Sales\Controller\DefaultController {
    /*
     * @todo Arrumar essa permissão
     */

    public function checkPermission() {
        
    }

    public function indexAction() {

        if (ErrorModel::getErrors()) {
            return $this->_view;
        }
        $id = $this->params()->fromQuery($this->module_name) ? : $this->params()->fromPost('company');
        if ($id) {
            $carrierModel = new CarrierModel();
            $carrierModel->initialize($this->serviceLocator);
            $this->_view->delivery_region = $carrierModel->getRegion($id);
            $this->_view->delivery_region_cities = $carrierModel->getRegionCities($id);
            $this->_view->fixedTax = $carrierModel->getFixedTax($id);
            $this->_view->fixedTaxByWeight = $carrierModel->getFixedTaxByWeight($id);
            $this->_view->fixedTaxByRegion = $carrierModel->getFixedTaxByRegion($id);
            $this->_view->percentageTax = $carrierModel->getPercentageTax($id);
            $this->_view->percentageTaxByRegion = $carrierModel->getPercentageTaxByRegion($id);
            $this->_view->weightTaxByRegion = $carrierModel->getWeightTaxByRegion($id);
            $this->_view->restriction_material = $carrierModel->getRestrictionMaterial($id);
        }
        return parent::indexAction();
    }

}
