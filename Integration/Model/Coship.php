<?php
/**
 * MRPEasy
 * 
 * PHP version 7.0
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */

/**
 * This class sends shipments from Magento to MRPEasy.
 * Shipped items are dubtracted from the inventory.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;

class Coship
{
    /**
     * @var \MRPEasy\Integration\Helper\Rest
     */
	protected $_rest;
	
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
	protected $_objectManager;
	
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
	protected $_resource;
	
	public function __construct(
			\MRPEasy\Integration\Helper\Rest $rest,
			\Magento\Framework\ObjectManagerInterface $objectManager,
			\Magento\Framework\App\ResourceConnection $resource
	) {
		$this->_rest = $rest;
		$this->_objectManager = $objectManager;
		$this->_resource = $resource;
	}
	
    /**
     * Run syncronization of shipments.
     *
     * @return void
     */
    public function syncronize() 
    {
        if (!$this->_rest->isConfigured()) {
            return;
        }
        
        $this->_shipItems();
    }
    
    /**
     * Ship items in MRPEasy.
     *
     * @return array
     */
    protected function _shipItems() 
    {
        $items = $this->_getNotShippedItems();
        if (count($items)) {
            $this->_postShipments($items);
        }
    }
    
    /**
     * Get items that have been posted to MRPEasy.
     *
     * @return array
     */
    protected function _getNotShippedItems() 
    {
        $coreResource = $this->_resource;
        $conn = $coreResource->getConnection();


        // Quickfix for MRP shipping issue
        // Thu Mar  5 10:31:32 2020
        $sql = "update sales_order_item oi1,sales_order_item oi2
            set oi1.qty_shipped=oi2.qty_shipped
            where oi1.parent_item_id=oi2.item_id and oi1.qty_shipped<oi2.qty_shipped
            and oi1.order_id>95000";
        $conn->query($sql);
        // *

        $sql = $conn->select()->from(
            array('o' => $coreResource->getTableName('sales_order')),
            array(
                'o.entity_id',
                'o.mrpeasy_cust_ord_id'
            )
        )->where('o.state = \'complete\'')
        ->where('o.mrpeasy_cust_ord_id IS NOT NULL');
        $sql->join(
            array('oi' => $coreResource->getTableName('sales_order_item')),
            'oi.order_id = o.entity_id AND oi.mrpeasy_cust_line_id IS NOT NULL AND oi.mrpeasy_shipment_id IS NULL AND oi.qty_shipped >= (oi.qty_ordered - oi.qty_refunded)',
            array('oi.item_id', 'mrpeasy_cust_line_id', 'oi.qty_shipped')
        );
        $items = $conn->fetchAll($sql);
        return $items;
    }
    
    /**
     * Post shipped items to MRPEasy.
     *
     * @param array $items - list of items that have been shipped
     *
     * @return void
     */
    protected function _postShipments(array $items) 
    {
        $lines = array();
        $itemIds = array();
        foreach ($items AS $item) {
            if (!isset($lines[$item['mrpeasy_cust_line_id']])) {
                $lines[$item['mrpeasy_cust_line_id']] = 0;
            }

            $lines[$item['mrpeasy_cust_line_id']] += $item['qty_shipped'];
            $itemIds[$item['item_id']] = $item['item_id'];
        }
        
        $data = array('cust_ord_lines' => $lines, 'verbose' => true);
        $url = 'shipments';
        $result = $this->_rest->post($url, $data);
        if (isset($result->success) && $result->success) {
            if (is_numeric($result->shipment_id)) {
                $coreResource = $this->_resource;
                $itemsTable = $coreResource->getTableName('sales_order_item');
                $conn = $this->_resource->getConnection();
                $conn->update(
                    $itemsTable,
                    array(
                        'mrpeasy_shipment_id' => $result->shipment_id
                    ),
                    array('item_id IN (?)' => $itemIds)
                );
            }
        } else {
            $code = $this->_rest->getCode();
            if ($code != 201) {
                $this->_rest->logApiError($code, $url);
            }
        }
    }
}
