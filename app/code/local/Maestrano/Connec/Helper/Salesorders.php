<?php
class Maestrano_Connec_Helper_Salesorders extends Maestrano_Connec_Helper_BaseMappers {

    public function __construct() {
        parent::__construct();
        $this->connec_entity_name = 'SalesOrder';
        $this->local_entity_name = 'SalesOrders';
        $this->connec_resource_name = 'sales_orders';
        $this->connec_resource_endpoint = 'sales_orders';
    }

    // Return a local Model by id
    public function loadModelById($localId)
    {
        $localModel = Mage::getModel('sales/order')->load($localId);
        return $localModel;
    }

    // Return a new local Model
    protected function getNewModel()
    {
        return Mage::getModel('sales/order');
    }

    // Map the Connec resource attributes onto the Magento model
    protected function mapConnecResourceToModel($order_hash, &$order)
    {
        // Not saved locally, one way to connec!
    }

    // Map the Magento model to a Connec resource hash
    /**
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    protected function mapModelToConnecResource($order)
    {
        $order_hash = array();

        /** @var Maestrano_Connec_Model_Mnoidmap $mnoIdMapModel */
        $mnoIdMapModel = Mage::getModel('connec/mnoidmap');

        // Get customer mno_id_map
        $customerMnoIdMap = $mnoIdMapModel->findMnoIdMapByLocalIdAndEntityName($order->getCustomerId(), Mage::helper('mnomap/customers')->getLocalResourceName());

        $order_hash['due_date'] = $order->getCreatedAtDate()->toString(Zend_Date::ISO_8601);
        $order_hash['transaction_date'] = $order->getCreatedAtDate()->toString(Zend_Date::ISO_8601);
        $order_hash['title'] = 'Magento order #' . $order->getIncrementId() . " (" . $order->getCustomerFirstname() . " " . $order->getCustomerLastname() .  ")";
        $order_hash['person_id'] = $customerMnoIdMap['mno_entity_guid'];

        // State
        $order_hash['status'] = strtoupper($order->getStatus());

        $items = $order->getAllItems();
        if (count($items) > 0) {
            $order_hash['lines'] = array();
            foreach($items as $item) {
                // If a product is a configured, line are doubled (configured and simple)
                // We only keep the configured product and interogate db to get simple product id
                if ($item->getParentItem()) {
                    continue;
                }

                // Configurable and simple product both have the simple product sku
                $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());
                // Get product mno_id_map
                $productMnoIdMap = $mnoIdMapModel->findMnoIdMapByLocalIdAndEntityName($product->getId(), Mage::helper('mnomap/products')->getLocalResourceName());

                $line_hash = array();

                $line_hash['item_id'] = $productMnoIdMap['mno_entity_guid'];
                $line_hash['description'] = $item->getName();
                $line_hash['quantity'] = $item->getQtyOrdered();
                $line_hash['unit_price'] = array();
                $line_hash['unit_price']['total_amount'] = $item->getBasePriceInclTax();
                $line_hash['unit_price']['tax_rate'] = $item->getTaxPercent();
                $line_hash['total_price'] = array();
                $line_hash['total_price']['total_amount'] = $item->getRowTotalInclTax();
                $line_hash['total_price']['tax_rate'] = $item->getTaxPercent();
                $line_hash['total_price']['tax_amount'] = $item->getTaxAmount();

                $order_hash['lines'][] = $line_hash;
            }
        }

        Mage::log("Maestrano_Connec_Helper_Salesorders::mapModelToConnecResource - mapped order_hash: " . print_r($order_hash, 1));

        return $order_hash;
    }
}