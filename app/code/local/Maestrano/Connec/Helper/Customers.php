<?php
class Maestrano_Connec_Helper_Customers extends Maestrano_Connec_Helper_BaseMappers {

    public function __construct() {
        parent::__construct();
        $this->connec_entity_name = 'Customer';
        $this->local_entity_name = 'Customers';
        $this->connec_resource_name = 'people';
        $this->connec_resource_endpoint = 'people';
    }

    // Return a local Model by id
    public function loadModelById($localId)
    {
        $localModel = Mage::getModel('customer/customer')->load($localId);
        return $localModel;
    }

    protected function getNewModel()
    {
        return Mage::getModel('customer/customer');
    }

    public function afterSaveConnecResource($resource_hash, $model, $oberverLock) {
        Mage::log("Maestrano_Connec_Helper_Customers::afterSaveConnecResource - mapped customer id: " . $model->getId());

        // There is at least an address
        if ($this->isSetArray('address_home', $resource_hash)
            && ($this->isSetArray('billing', $resource_hash['address_home']) || $this->isSetArray('shipping', $resource_hash['address_home']))) {
            // There is 2 addresses coming from connec
            if ($this->isSetArray('billing', $resource_hash['address_home']) && $this->isSetArray('shipping', $resource_hash['address_home'])) {
                // It the same address
                if ($resource_hash['address_home']['billing'] === $resource_hash['address_home']['shipping']) {
                    // If the same, add one address as default for both
                    $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_home']['billing'], true, true);
                } else {
                    // Add billing address
                    $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_home']['billing'], true);

                    // Add shipping address
                    $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_home']['shipping'], false, true);
                }
            } elseif ($this->isSetArray('billing', $resource_hash['address_home'])) {
                // Add connec billing address
                $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_home']['billing'], true, true);
            } elseif ($this->isSetArray('shipping', $resource_hash['address_home'])) {
                // Add connec shipping address
                $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_home']['shipping'], true, true);
            }
        } elseif ($this->isSetArray('address_work', $resource_hash)
            && ($this->isSetArray('billing', $resource_hash['address_work']) || $this->isSetArray('shipping', $resource_hash['address_work']))) {
            // There is 2 addresses coming from connec
            if ($this->isSetArray('billing', $resource_hash['address_work']) && $this->isSetArray('shipping', $resource_hash['address_work'])) {
                // It the same address
                if ($resource_hash['address_work']['billing'] === $resource_hash['address_work']['shipping']) {
                    // If the same, add one address as default for both
                    $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_work']['billing'], true, true);
                } else {
                    // Add billing address
                    $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_work']['billing'], true);

                    // Add shipping address
                    $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_work']['shipping'], false, true);
                }
            } elseif ($this->isSetArray('billing', $resource_hash['address_work'])) {
                // Add connec billing address
                $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_work']['billing'], true, true);
            } elseif ($this->isSetArray('shipping', $resource_hash['address_work'])) {
                // Add connec shipping address
                $this->addConnecAddressToModel($model->getId(), $resource_hash, $resource_hash['address_work']['shipping'], true, true);
            }
        }
    }

    /**
     * @param $customerId
     * @param $resource_hash
     * @param $address_hash
     * @param bool|false $isBilling
     * @param bool|false $isShipping
     * @throws Exception
     */
    protected function addConnecAddressToModel($customerId, $resource_hash, $address_hash, $isBilling = false, $isShipping = false)
    {
        /** @var Mage_Customer_Model_Address $address */
        $address = Mage::getModel('customer/address');
        $address->setCustomerId($customerId);

        if ($isBilling) {
            if (array_key_exists('phone_home', $resource_hash) && array_key_exists('landline', $resource_hash['phone_home'])) {
                $address->setTelephone($resource_hash['phone_home']['landline']);
            }
            if (array_key_exists('phone_home', $resource_hash) && array_key_exists('fax', $resource_hash['phone_home'])) {
                $address->setFax($resource_hash['phone_home']['fax']);
            }
        }

        // Mapped values
        if (array_key_exists('attention_first_name', $address_hash)) { $address->setFirstname($address_hash['attention_first_name']); }
        if (array_key_exists('attention_last_name', $address_hash)) { $address->setLastname($address_hash['attention_last_name']); }
        if (array_key_exists('line1', $address_hash) && array_key_exists('line2', $address_hash)) {
            $address->setStreetFull($address_hash['line1'] . "\n" . $address_hash['line2']);
        }
        if (array_key_exists('city', $address_hash)) { $address->setCity($address_hash['city']); }
        if (array_key_exists('postal_code', $address_hash)) { $address->setPostcode($address_hash['postal_code']); }
        // Connec return the country id (ISO2)
        if (array_key_exists('country', $address_hash)) { $address->setCountryId($address_hash['country']); }
        // Set by default the returned region (free input) and look if it's possible to find and id for lists
        if (array_key_exists('region', $address_hash)) {
            $address->setRegion($address_hash['region']);
            $region = $this->findRegionByName($address_hash['region'], $address_hash['country']);
            if (!empty($region)) {
                $address->setRegionId($region->getId());
                $address->setRegion($region->getName());
            }
        }

        $address->setIsDefaultBilling($isBilling);
        $address->setIsDefaultShipping($isShipping);

        Mage::log("Maestrano_Connec_Helper_Customers::addConnecAddressToModel - mapped address: " . print_r($address->getData(), 1));

        // Lock the observer
        $address->setOberverLock(true);
        $address->save();
    }

    private function findRegionByName($name, $countryId) {
        $regionCollection = Mage::getModel('directory/region')->getResourceCollection()
            ->addCountryFilter($countryId)
            ->load();
        foreach ($regionCollection as $region) {
            if ($name == $region->getName()) {
                return $region;
            }
        }
        return null;
    }

    // Map the Connec resource attributes onto the Magento model
    /**
     * @param array $customer_hash
     * @param Mage_Customer_Model_Customer $customer
     * @throws Mage_Core_Exception
     */
    protected function mapConnecResourceToModel($customer_hash, &$customer)
    {
        // Mapped values
        if (array_key_exists('title', $customer_hash)) { $customer->setPrefix($customer_hash['title']); }
        if (array_key_exists('first_name', $customer_hash)) { $customer->setFirstname($customer_hash['first_name']); }
        if (array_key_exists('last_name', $customer_hash)) { $customer->setLastname($customer_hash['last_name']); }
        if (array_key_exists('birth_date', $customer_hash)) { $customer->setDob($customer_hash['birth_date']); }
        if (array_key_exists('email', $customer_hash) && array_key_exists('address', $customer_hash['email'])) { $customer->setEmail($customer_hash['email']['address']); }

        // Default magento values
        if($this->isNewByConnecId($customer_hash['id'])) {
            $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
            $customer->setGroupId(1); // 1: General Group
            $customer->setStore(Mage::app()->getStore());
        }

        Mage::log("Maestrano_Connec_Helper_Customers::mapConnecResourceToModel - mapped customer: " . print_r($customer->getData(), 1));
    }

    /**
     * Map the Magento model to a Connec resource hash
     * @param Mage_Customer_Model_Customer $customer
     * @return array
     */
    protected function mapModelToConnecResource($customer)
    {
        $customer_hash = array();

        // Mapped values
        $customer_hash['title'] = $customer->getPrefix();
        $customer_hash['first_name'] = $customer->getFirstname();
        $customer_hash['last_name'] = $customer->getLastname();
        $customer_hash['birth_date'] = $customer->getDob();
        $customer_hash['email'] = array();
        $customer_hash['email']['address'] = $customer->getEmail();

        // Default connec values
        if($this->isNewByLocalId($customer->getId())) {
            $customer_hash['is_customer'] = true;
            // Create default organization
            $customer_hash['opts'] = array('create_default_organization' => true);
        }

        Mage::log("Maestrano_Connec_Helper_Customers::mapModelToConnecResource - mapped customer_hash: " . print_r($customer_hash, 1));

        return $customer_hash;
    }

}