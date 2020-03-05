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
 * This class sends sales orders in status Processing from Magento to MRPEasy.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;

class Copost
{
    /**
     * @var \MRPEasy\Integration\Helper\Rest
     */
    protected $_rest;
    
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    
    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $_configWriter;
    
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    
    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $_cacheTypeList;
    
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resource;
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    
    public function __construct(
            \MRPEasy\Integration\Helper\Rest $rest,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
            \Magento\Framework\ObjectManagerInterface $objectManager,
            \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
            \Magento\Framework\App\ResourceConnection $resource,
            \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_rest = $rest;
        $this->_scopeConfig = $scopeConfig;
        $this->_configWriter = $configWriter;
        $this->_objectManager = $objectManager;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_resource = $resource;
        $this->_storeManager = $storeManager;
    }
    
    /**
     * Run syncronization of sales orders.
     *
     * @param \MRPEasy\Integration\Helper\Rest $rest
     * @return void
     */
    public function syncronize() 
    {
        if (!$this->_rest->isConfigured()) {
            return;
        }
        
        $this->_syncOrders();
    }
    
    /**
     * Post sales orders to MRPEasy.
     *
     * @return void
     */
    protected function _syncOrders() 
    {
        $items = $this->_fetchSoldItems();
        
        if (count($items)) {
            $orders = $this->_groupOrders($items);
            
            $customerId = $this->_getMRPEasyCustomerId();
            
            if (!empty($customerId)) {
                $this->_postOrders($orders, $customerId);
            }
        }
    }
    
    /**
     * Fetch items from new sales orders.
     *
     * @return array
     */
    protected function _fetchSoldItems() 
    {
        $coreResource = $this->_resource;
        $conn = $coreResource->getConnection();
        $sql = $conn->select()->from(
            array('o' => $coreResource->getTableName('sales_order')),
            array(
                'o.entity_id',
                'o.increment_id'
            )
        )->where('o.status = \'' . \Magento\Sales\Model\Order::STATE_PROCESSING . '\'');
        $sql->join(
            array('oi' => $coreResource->getTableName('sales_order_item')),
            'oi.order_id = o.entity_id AND oi.product_type = \'simple\''
        );
        $sql->joinLeft(
            array('op' => $coreResource->getTableName('sales_order_item')),
            'op.item_id = oi.parent_item_id',
            array('parent_row_total' => 'op.row_total')
        );
        $items = $conn->fetchAll($sql);
        foreach ($items AS &$item) {
            if ($item['row_total'] === null || $item['row_total'] === "0.0000") {
                $item['row_total'] = $item['parent_row_total'];
            }
        }

        unset($item);

        return $items;
    }
    
    /**
     * Group sales orders into MRPEasy customer orders.
     *
     * @param &array $items - items that are sold and should be posted to MRPEasy
     * 
     * @return array
     */
    protected function _groupOrders(array &$items) 
    {
        $groupType = $this->_scopeConfig->getValue('mrpeasy/integration/group_orders');
        
        $orders = array();
        
        if ($groupType == \MRPEasy\Integration\Model\SyncTypes::ORDER_SYNC_GROUP) {
            $orders[0] = array();
            foreach ($items AS $i) {
                $orders[0][$i['item_id']] = array(
                    'order_id' => $i['order_id'],
                    'item_id' => $i['item_id'],
                    'sku' => $i['sku'],
                    'order_code' => $i['increment_id'],
                    'quantity' => $i['qty_ordered'],
                    'row_total' => $i['row_total']
                );
            }
        } else if ($groupType == \MRPEasy\Integration\Model\SyncTypes::ORDER_SYNC_CUMULATIVELY) {
            $orders[0] = array();
            foreach ($items AS $i) {
                if (!isset($orders[0][$i['product_id']])) {
                    $orders[0][$i['product_id']] = array(
                        'sku' => $i['sku'],
                        'quantity' => 0,
                        'row_total' => 0,
                        'orders' => array()
                    );
                }

                $orders[0][$i['product_id']]['quantity'] += $i['qty_ordered'];
                $orders[0][$i['product_id']]['row_total'] += $i['row_total'];
                $orders[0][$i['product_id']]['orders'][] = array(
                    'order_id' => $i['order_id'],
                    'order_code' => $i['increment_id'],
                    'item_id' => $i['item_id']
                );
            }
        } else { // $groupType == \MRPEasy\Integration\Model\SyncTypes::ORDER_SYNC_SEPARATELY
            foreach ($items AS $i) {
                if (!isset($orders[$i['order_id']])) {
                    $orders[$i['order_id']] = array();
                }

                $orders[$i['order_id']][$i['item_id']] = array(
                    'order_id' => $i['order_id'],
                    'item_id' => $i['item_id'],
                    'sku' => $i['sku'],
                    'order_code' => $i['increment_id'],
                    'quantity' => $i['qty_ordered'],
                    'row_total' => $i['row_total']
                );
            }
        }

        return $orders;
    }
    
    /**
     * Get customer ID in MRPEasy. If there is no customer, try to create a new one.
     *
     * @return int
     */
    protected function _getMRPEasyCustomerId() 
    {
        $customerId = $this->_scopeConfig->getValue('mrpeasy/integration/customer_id');
        if (!empty($customerId)) {
            $url = 'customers/' . intval($customerId);
            $customer = $this->_rest->get($url);
            if (!empty($customer)) {
                return $customer->customer_id;
            }

            $code = $this->_rest->getCode();
            if ($code == 401) {
                $this->_rest->logApiDenied();
                return false;
            }
        }
        
        $store = $this->_storeManager->getStore();
        $customerData = array(
            'title' => 'Magento shop ' . $store->getFrontendName()
        );
        $url = 'customers';
        
        $customerId = $this->_rest->post($url, $customerData);
        if (!is_numeric($customerId)) {
            $code = $this->_rest->getCode();
            if ($code == 401) {
                $this->_rest->logApiDenied();
            } else {
                $this->_rest->logApiError($code, $url);
            }

            return false;
        }
        
        $this->_configWriter->save('mrpeasy/integration/customer_id', $customerId);
        $this->_cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        
        return $customerId;
    }
    
    /**
     * Post order to MRPEasy.
     *
     * @param array $orders     - array of orders to post
     * @param int   $customerId - customer number from MRPEasy
     * 
     * @return void
     */
    protected function _postOrders(array $orders, $customerId) 
    {
        $conn = $this->_resource->getConnection();
        $itemsTable = $this->_resource->getTableName('sales_order_item');
        
        $url = 'customer-orders';
        foreach ($orders AS $order) {
            $references = array();
            $salesOrderIds = array();
            $data = array(
                'customer_id' => $customerId,
                'status' => 30,
                'verbose' => true,
                'products' => array()
            );
            foreach ($order AS $line) {
                if (isset($line['orders'])) {
                    $lineReference = array();
                    $itemId = '';
                    foreach ($line['orders'] AS $o) {
                        $k = md5($o['order_code']);
                        $lineReference[$k] = $o['order_code'];
                        $references[$k] = $o['order_code'];
                        $itemId .= ',' . $o['item_id'];
                        $salesOrderIds[$o['order_id']] = $o['order_id'];
                    }

                    sort($lineReference);
                    $lineReference = implode(', ', $lineReference);
                    $itemId = substr($itemId, 1);
                } else {
                    $references[md5($line['order_code'])] = $line['order_code'];
                    $lineReference = $line['order_code'];
                    $itemId = $line['item_id'];
                    $salesOrderIds[$line['order_id']] = $line['order_id'];
                }
                
                $data['products'][] = array(
                    'sku' => $line['sku'],
                    'quantity' => $line['quantity'],
                    'total_price' => $line['row_total'],
                    'description' => $lineReference,
                    'shop_line_id' => $itemId
                );
            }

            sort($references);

            $data['reference'] = implode(', ', $references);

            $result = $this->_rest->post($url, $data);
            if (isset($result->success) && $result->success) {
                $txt = 'MRPEasy customer order #' . $result->code;
                foreach ($salesOrderIds AS $orderId) {
                    $salesOrder = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);
                    $salesOrder->setMrpeasyCustOrdCode($result->code);
                    $salesOrder->setMrpeasyCustOrdId($result->cust_ord_id);
                    $salesOrder->addStatusHistoryComment($txt, 'mrpeasy_posted');
                    $salesOrder->save();
                }
                
                foreach ($result->line_ids AS $k => $v) {
                    if (strpos($v, ',') !== false) {
                        $v = explode(',', $v);
                    }

                    $conn->update(
                        $itemsTable,
                        array(
                            'mrpeasy_cust_ord_id' => $result->cust_ord_id,
                            'mrpeasy_cust_line_id' => $k
                        ),
                        array('item_id IN (?)' => $v)
                    );
                }
            } else {
                $code = $this->_rest->getCode();
                if ($code == 400) {
                    // Some validation error.
                    // Probably no item with such SKU
                    $txt = 'No items are posted to MRPEasy';
                    foreach ($salesOrderIds AS $orderId) {
                        $salesOrder = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);
                        $salesOrder->addStatusHistoryComment($txt, 'mrpeasy_ready');
                        $salesOrder->save();
                    }
                } else {
                    $this->_rest->logApiError($code, $url);
                }
            }
        }
    }
}
