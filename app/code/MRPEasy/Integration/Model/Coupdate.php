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
 * This class updates statuses of sales orders in Magento.
 * When customer order line's status in MRPEasy is "Ready for shipment",
 * sales order's status in Magento is updated.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;

class Coupdate
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
     * Run syncronization of sales order statuses.
     *
     * @return void
     */
    public function syncronize() 
    {
        if (!$this->_rest->isConfigured()) {
            return;
        }
        
        $this->_updateOrderStatuses();
    }
    
    /**
     * Update statuses of orders that have been posted to MRPEasy.
     *
     * @return void
     */
    protected function _updateOrderStatuses() 
    {
        $items = $this->_getPostedItems();
        
        if (count($items)) {
            $salesOrdIds = array();
            $custOrdIds = array();
            $readyOrders = array();
            foreach ($items AS $item) {
                $salesOrdIds[ $item['entity_id'] ] = $item['entity_id'];
                $readyOrders[ $item['entity_id'] ] = true;
                if (!empty($item['mrpeasy_cust_ord_id'])) {
                    $custOrdIds[ $item['mrpeasy_cust_ord_id'] ] = $item['mrpeasy_cust_ord_id'];
                }
            }
            
            if (count($custOrdIds) > 0) {
                $custOrdLineStatuses = $this->_getCustomerOrderLineStatuses($custOrdIds);
            }
            
            $salesOrders = $this->_getSalesOrderLines($salesOrdIds);
            foreach ($salesOrders AS $s) {
                if (array_key_exists($s['mrpeasy_cust_line_id'], $custOrdLineStatuses)) {
                    if ($custOrdLineStatuses[ $s['mrpeasy_cust_line_id'] ] < 40) {
                        $readyOrders[ $s['order_id'] ] = false;
                    }
                }
            }
            
            foreach ($readyOrders AS $orderId => $isReady) {
                if ($isReady) {
                    $salesOrder = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);
                    $txt = 'MRPEasy customer order #' . $salesOrder->getMrpeasyCustOrdCode() . ' is ready.';
                    $salesOrder->addStatusHistoryComment($txt, 'mrpeasy_ready');
                    $salesOrder->save();
                }
            }
        }
    }
    
    /**
     * Get items that have been posted to MRPEasy.
     *
     * @return array
     */
    protected function _getPostedItems() 
    {
    	$coreResource = $this->_resource;
        $conn = $coreResource->getConnection();
        $sql = $conn->select()->from(
            array('o' => $coreResource->getTableName('sales_order')),
            array(
                'o.entity_id',
                'o.mrpeasy_cust_ord_id'
            )
        )->where('o.status = \'mrpeasy_posted\'');
        $sql->joinLeft(
            array('oi' => $coreResource->getTableName('sales_order_item')),
            'oi.order_id = o.entity_id AND oi.mrpeasy_cust_line_id IS NOT NULL',
            array('oi.item_id', 'mrpeasy_cust_line_id')
        );
        $items = $conn->fetchAll($sql);
        return $items;
    }
    
    /**
     * Get all items of sales orders.
     *
     * @param array $salesOrdIds - list of sales order IDs
     *
     * @return array
     */
    protected function _getSalesOrderLines(array $salesOrdIds) 
    {
    	$coreResource = $this->_resource;
        $conn = $coreResource->getConnection();
        $sql = $conn->select()->from(
            array('oi' => $coreResource->getTableName('sales_order_item')),
            array(
                'oi.item_id',
                'oi.order_id',
                'oi.mrpeasy_cust_line_id'
            )
        )->where('oi.order_id IN (?)', $salesOrdIds)
        ->where('oi.mrpeasy_cust_line_id IS NOT NULL');
        $items = $conn->fetchAll($sql);
        return $items;
    }
    
    /**
     * Get part statuses of customer orders from MRPEasy.
     *
     * @param array $custOrdIds - list of MRPEasy customer order IDs
     *
     * @return array
     */
    protected function _getCustomerOrderLineStatuses(array $custOrdIds) 
    {
        $url = 'customer-orders/?' . http_build_query(array('cust_ord_id' => $custOrdIds));
        $start = 0;
        $range = 100;
        $orders = array();
        while (1) {
            $this->_rest->setOffset($start);
            $custOrders = $this->_rest->get($url);
            $code = $this->_rest->getCode();
            if ($code == 200 || $code == 206) {
                $orders = array_merge($orders, $custOrders);
            }

            if ($code != 206) {
                break;
            }
        }
        
        $custOrdLineStatuses = array();
        foreach ($orders AS $c) {
            foreach ($c->products AS $p) {
                $custOrdLineStatuses[ $p->line_id ] = $p->part_status;
            }
        }
        
        return $custOrdLineStatuses;
    }
}
