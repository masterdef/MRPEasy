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
 * This class gets inventory levels from MRPEasy and updates items in Magento.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;

class Inventory
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
     * Run syncronization of inventory levels.
     *
     * @return void
     */
    public function syncronize() 
    {
        if (!$this->_rest->isConfigured()) {
            return;
        }
        
        $this->_importInventory();
    }
    
    /**
     * Fetch inventory from MRPEasy.
     *
     * @return void
     */
    protected function _importInventory() 
    {
        $items = $this->_getInventoryItems();
        
        if (count($items)) {
            $skus = array();
            foreach ($items AS $i) {
                $skus[$i['entity_id']] = $i['sku'];
            }

            $quantities = $this->_fetchQuantities($skus);
            if (!empty($quantities)) {
                $this->_updateQuantities($quantities, $items);
            }
        }
    }
    
    /**
     * Get inventory items from MRPEasy.
     *
     * @return array (ID => SKU)
     */
    protected function _getInventoryItems() 
    {
        $coreResource = $this->_resource;
        $conn = $coreResource->getConnection();
        $sql = $conn->select()->from(
            array('p' => $coreResource->getTableName('catalog_product_entity')),
            array(
                'p.entity_id',
                'p.sku'
            )
        )->where('p.type_id = \'simple\'')
        ->group('p.entity_id');
        $sql->joinLeft(
            array('s' => $coreResource->getTableName('cataloginventory_stock_item')),
            's.product_id = p.entity_id',
            array('qty' => 'SUM(s.qty)')
        );
        $items = $conn->fetchAssoc($sql);
        return $items;
    }
    
    /**
     * Get available quantities from MRPEasy.
     *
     * @param array $skus - list of SKUs (product_id => sku)
     *
     * @return array (
     *    stdClass(product_id, article_id, sku, quantity, shop_item_id) )
     */
    protected function _fetchQuantities($skus) 
    {
        $url = 'stock';
        $data = array('get_available_by_sku' => $skus);
        $result = $this->_rest->post($url, $data);
        if (isset($result->success) && $result->success && !empty($result->inventory)) {
            return $result->inventory;
        } else {
            $code = $this->_rest->getCode();
            $this->_rest->logApiError($code, $url);
        }
    }
    
    /**
     * Update quantities of items.
     *
     * @param array   $items    - items with quantities
     * @param [array] $oldItems - old item quantities
     *
     * @return void
     */
    protected function _updateQuantities($items, array $oldItems = array()) 
    {
        $isReindexNeeded = false;

        $stockId = 1;
        $itemModel = $this->_objectManager->create('Magento\CatalogInventory\Model\ResourceModel\Stock\Item');
        $stockItemFactory = $this->_objectManager->get('Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory');
        foreach ($items AS $item) {
            $qty = floatval($item->quantity);
            if ($qty < 0) {
                $qty = 0;
            }

            if (isset($oldItems[$item->shop_item_id]) && $oldItems[$item->shop_item_id]['qty'] == $qty) {
                continue;
            }
            
            $stockItem = $stockItemFactory->create();
            $itemModel->loadByProductId($stockItem, $item->shop_item_id, $stockId);
            $stockItemId = $stockItem->getId();
            if (!$stockItemId) {
                $stockItem->setData('product_id', $item->shop_item_id);
                $stockItem->setData('stock_id', $stockId);
            }

            if ($stockItem->getQty('qty') != $qty) {
                if (!$isReindexNeeded) {
                    $indexer = $this->_objectManager->create('\MRPEasy\Integration\Helper\Indexer');
                    $indexer->disable();
                    $isReindexNeeded = true;
                }

                $stockItem->setData('qty', $qty);
                $stockItem->setData('is_in_stock', ($qty > 0));
                $stockItem->save();
            }

            unset($stockItem);
        }
        
        if ($isReindexNeeded) {
            $indexer->enable($isReindexNeeded);
        }
    }
}
