<?php

namespace Carrier\Model;

use Core\Helper\Format;

class CarrierModel extends \Core\Model\CompanyModel {

    /**
     * @return \Core\Entity\People
     */
    public function getCurrentPeopleCompany() {
        if ($this->getErrors()) {
            return;
        }
        return $this->_company_id ? $this->_em->getRepository('\Core\Entity\People')->find($this->_company_id) : null;
    }

    public function getAllCompanies() {
        //return $this->_em->getRepository('\Core\Entity\PeopleCarrier')->findBy(array('company' => $this->getCurrentPeopleCompany()), array('name' => 'ASC'), 100);
    }

    public function addCompanyLink($entity_people, $currentPeopleCompany) {
        $people_employee = new \Core\Entity\CarrierPeople();
        $people_employee->setCompanyId($currentPeopleCompany->getId());
        $people_employee->setCarrier($entity_people);
        $this->_em->persist($people_employee);
        $this->_em->flush($people_employee);
    }

    public function getRegionCities($carrier_id) {
        $sql = 'SELECT delivery_region.id,GROUP_CONCAT(DISTINCT city,\'/\',uf SEPARATOR \', \') AS cities FROM city
          INNER JOIN state ON (state.id = city.state_id)
          INNER JOIN delivery_region_city ON (delivery_region_city.city_id = city.id)
          INNER JOIN delivery_region ON (delivery_region.id = delivery_region_city.delivery_region_id)
          WHERE delivery_region.people_id = ?
          AND city IS NOT NULL
          GROUP BY delivery_region.id';

        $conn = $this->_em->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $carrier_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRegion($carrier_id) {
        $entity = $this->_em->getRepository('\Core\Entity\DeliveryRegion')
                        ->createQueryBuilder('t')
                        ->where('t.people = :carrier')
                        ->setParameters(array('carrier' => $this->_em->getRepository('\Core\Entity\People')->find($carrier_id)))
                        ->orderBy('t.region')
                        ->getQuery()->getResult();
        return $entity;
    }

    public function searchRestrictionMaterial($params, $limit = 30) {
        $entity = $this->_em->getRepository('\Core\Entity\ProductMaterial')
                        ->createQueryBuilder('PM')
                        ->where('PM.material LIKE :material')
                        ->andWhere('PM.revised = :revised')
                        ->setParameters(array('material' => Format::searchNormalize($params['term']) . '%', 'revised' => true))
                        ->orderBy('PM.material')
                        ->setMaxResults($limit)
                        ->getQuery()->getResult();
        return $entity;
    }

    public function getRestrictionMaterial($carrier_id) {
        $entity = $this->_em->getRepository('\Core\Entity\DeliveryRestrictionMaterial')
                        ->createQueryBuilder('DRM')
                        ->innerJoin('\Core\Entity\ProductMaterial', 'PM', 'WITH', 'PM.id = DRM.product_material')
                        ->where('DRM.carrier = :carrier')
                        ->setParameters(array('carrier' => $this->_em->getRepository('\Core\Entity\People')->find($carrier_id)))
                        ->groupBy('PM.id')
                        ->orderBy('PM.material')
                        ->getQuery()->getResult();
        return $entity;
    }

    public function deleteRestrictionMaterial($id) {
        if ($this->getErrors()) {
            return;
        }
        if (!$id) {
            $this->addError('Restriction material id not informed!');
        } else {
            $entity = $this->_em->getRepository('\Core\Entity\DeliveryRestrictionMaterial')->find($id);
            if ($entity) {
                $this->_em->remove($entity);
                $this->_em->flush($entity);
                return true;
            } else {
                $this->addError('Error removing this restriction material!');
                return false;
            }
        }
    }

    public function addProductMaterial($material, $revised = false) {
        $productMaterial = $this->_em->getRepository('\Core\Entity\ProductMaterial')->findOneBy(array(
            'material' => trim($material)
        ));
        if (!$productMaterial) {
            $productMaterial = new \Core\Entity\ProductMaterial();
            $productMaterial->setMaterial(trim($material));
            $productMaterial->setRevised($revised);
            $this->_em->persist($productMaterial);
            $this->_em->flush($productMaterial);
        }
        return $productMaterial;
    }

    public function addRestrictionMaterial($params) {

        $carrier = $this->_em->getRepository('\Core\Entity\People')->find($params['company_id']);
        $materials = array_filter(explode(',', $params['restriction-material']));

        foreach ($materials AS $material) {
            $productMaterial = $this->addProductMaterial($material, true);

            $deliveryRestrictionMaterial = new \Core\Entity\DeliveryRestrictionMaterial();
            $deliveryRestrictionMaterial->setCarrier($carrier);
            $deliveryRestrictionMaterial->setRestrictionType($params['restriction-type']);
            $deliveryRestrictionMaterial->setProductMaterial($productMaterial);

            $this->_em->persist($deliveryRestrictionMaterial);
            $this->_em->flush($deliveryRestrictionMaterial);

            $return[] = $deliveryRestrictionMaterial;
        }
        return $return;
    }

    public function getPercentageTax($carrier_id) {

        $entity = $this->_em->getRepository('\Core\Entity\DeliveryTax')->findBy(array(
            'carrier' => $this->_em->getRepository('\Core\Entity\People')->find($carrier_id),
            'tax_type' => 'percentage',
            'people' => null,
            'final_weight' => null,
            'region_origin' => null,
            'region_destination' => null
        ));
        return $entity;
    }

    public function getFixedTax($carrier_id) {

        $entity = $this->_em->getRepository('\Core\Entity\DeliveryTax')->findBy(array(
            'carrier' => $this->_em->getRepository('\Core\Entity\People')->find($carrier_id),
            'tax_type' => 'fixed',
            'people' => null,
            'final_weight' => null,
            'region_origin' => null,
            'region_destination' => null
        ));
        return $entity;
    }

    public function getFixedTaxByWeight($carrier_id, $search = array(), $limit = NULL) {
        return $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')->where('t.carrier = :carrier')
                        ->andWhere('t.tax_type = :tax_type')
                        ->andWhere('t.people IS NULL')
                        ->andWhere('t.final_weight IS NOT NULL')
                        ->andWhere('t.region_origin IS NULL')
                        ->andWhere('t.region_destination IS NULL')
                        ->andWhere('t.tax_subtype=:kg')
                        ->setParameters(array('carrier' => $this->_em->getRepository('\Core\Entity\People')->find($carrier_id)->getId(), 'tax_type' => 'fixed', 'kg' => 'kg'))
                        ->orderBy('t.final_weight')
                        ->setMaxResults($limit)
                        ->getQuery()->getResult();
    }

    protected function searchOnTax(\Doctrine\ORM\QueryBuilder $query, array $search, array $param, $limit = NULL) {
        if ($search['from-region-origin']) {
            $query->andWhere('t.region_origin =:region_origin_id');
            $param['region_origin_id'] = $this->_em->getRepository('\Core\Entity\DeliveryRegion')->find($search['from-region-origin']);
        }
        if ($search['from-region-destination']) {
            $query->andWhere('t.region_destination =:region_destination_id');
            $param['region_destination_id'] = $this->_em->getRepository('\Core\Entity\DeliveryRegion')->find($search['from-region-destination']);
        }
        $query->setParameters($param)
                ->orderBy('t.region_origin,t.region_destination,t.final_weight')
                ->setMaxResults($limit);

        return $query->getQuery()->getResult();
    }

    public function getWeightTaxByRegion($carrier_id, $search = array(), $limit = NULL) {

        $carrier = $this->_em->getRepository('\Core\Entity\People')->find($carrier_id)->getId();
        $param = array('carrier' => $carrier, 'tax_type' => 'fixed', 'kg' => 'kg');

        $regionDestination = $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')
                        ->where('t.carrier = :carrier')
                        ->andWhere('t.tax_type = :tax_type')
                        ->andWhere('t.people IS NULL')
                        ->andWhere('t.final_weight IS NOT NULL')
                        ->andWhere('t.region_origin IS NOT NULL')
                        ->andWhere('t.region_destination IS NOT NULL')
                        ->andWhere('t.tax_subtype=:kg')
                        ->setParameters($param)->setMaxResults(1)
                        ->getQuery()->getResult();
        $search['from-region-destination'] = $search['from-region-destination']? : ($regionDestination ? $regionDestination[0]->getRegionDestination()->getId() : null);

        $query = $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')
                ->where('t.carrier = :carrier')
                ->andWhere('t.tax_type = :tax_type')
                ->andWhere('t.people IS NULL')
                ->andWhere('t.final_weight IS NOT NULL')
                ->andWhere('t.region_origin IS NOT NULL')
                ->andWhere('t.region_destination IS NOT NULL')
                ->andWhere('t.tax_subtype=:kg');

        return $this->searchOnTax($query, $search, $param, $limit);
    }

    public function getFixedTaxByRegion($carrier_id, $search = array(), $limit = NULL) {

        $carrier = $this->_em->getRepository('\Core\Entity\People')->find($carrier_id)->getId();
        $param = array('carrier' => $carrier, 'tax_type' => 'fixed');
        $regionDestination = $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')
                        ->where('t.carrier = :carrier')
                        ->andWhere('t.tax_type = :tax_type')
                        ->andWhere('t.people IS NULL')
                        ->andWhere('t.region_origin IS NOT NULL')
                        ->andWhere('t.region_destination IS NOT NULL')
                        ->setParameters($param)->setMaxResults(1)
                        ->getQuery()->getResult();
        $search['from-region-destination'] = $search['from-region-destination']? : ($regionDestination ? $regionDestination[0]->getRegionDestination()->getId() : null);



        $query = $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')
                ->where('t.carrier = :carrier')
                ->andWhere('t.tax_type = :tax_type')
                ->andWhere('t.people IS NULL')
                ->andWhere('t.region_origin IS NOT NULL')
                ->andWhere('t.region_destination IS NOT NULL');
        return $this->searchOnTax($query, $search, $param, $limit);
    }

    public function getPercentageTaxByRegion($carrier_id, $search = array(), $limit = NULL) {

        $carrier = $this->_em->getRepository('\Core\Entity\People')->find($carrier_id)->getId();
        $param = array('carrier' => $carrier, 'tax_type' => 'percentage');
        $regionDestination = $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')
                        ->where('t.carrier = :carrier')
                        ->andWhere('t.tax_type = :tax_type')
                        ->andWhere('t.people IS NULL')
                        ->andWhere('t.region_origin IS NOT NULL')
                        ->andWhere('t.region_destination IS NOT NULL')
                        ->setParameters($param)->setMaxResults(1)
                        ->getQuery()->getResult();
        $search['from-region-destination'] = $search['from-region-destination']? : ($regionDestination ? $regionDestination[0]->getRegionDestination()->getId() : null);

        $query = $this->_em->getRepository('\Core\Entity\DeliveryTax')->createQueryBuilder('t')
                ->where('t.carrier = :carrier')
                ->andWhere('t.tax_type = :tax_type')
                ->andWhere('t.people IS NULL')
                ->andWhere('t.region_origin IS NOT NULL')
                ->andWhere('t.region_destination IS NOT NULL');

        return $this->searchOnTax($query, $search, $param, $limit);
    }

}
