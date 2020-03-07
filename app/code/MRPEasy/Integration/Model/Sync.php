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
 * This class is the main constoller of syncronization.
 * It runs all particular functions.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;

class Sync
{
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
     * @var \MRPEasy\Integration\Helper\Rest
     */
	protected $_rest;
	
	public function __construct(
			\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
			\Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
			\Magento\Framework\ObjectManagerInterface $objectManager,
			\Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
			\MRPEasy\Integration\Helper\Rest $rest
	) {
		$this->_scopeConfig = $scopeConfig;
		$this->_configWriter = $configWriter;
		$this->_objectManager = $objectManager;
		$this->_cacheTypeList = $cacheTypeList;
		$this->_rest = $rest;
	}
	
    /**
     * Run syncronization between Magento and MRPEasy.
     *
     * @return void
     */
    public function syncronize() 
    {
        if (!$this->_rest->isConfigured()) {
            return;
        }
        
        $this->_saveRunTime();
        
        $this->_postNewOrders();
        
        $this->_updateOrderStatuses();
        
        $this->_shipItems();
        
        $isInventory = $this->_scopeConfig->getValue('mrpeasy/integration/inventory');
        if (!empty($isInventory)) {
            $this->_updateInventory();
        }
    }
    
    /**
     * Save time when syncronization was made.
     *
     * @return void
     */
    protected function _saveRunTime() 
    {
        $now = time();
		$this->_configWriter->save('mrpeasy/integration/last_sync', $now);
		$this->_cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }
    
    /**
     * Post sales orders to MRPEasy.
     *
     * @return void
     */
    protected function _postNewOrders() 
    {
    	$model = $this->_objectManager->create('\MRPEasy\Integration\Model\Copost');
        $model->syncronize();
    }
    
    /**
     * Update statuses of orders that have been posted to MRPEasy.
     *
     * @return void
     */
    protected function _updateOrderStatuses() 
    {
        $model = $this->_objectManager->create('\MRPEasy\Integration\Model\Coupdate');
        $model->syncronize($this->_rest);
    }
    
    /**
     * Ship items in MRPEasy.
     *
     * @return void
     */
    protected function _shipItems() 
    {
        $model = $this->_objectManager->create('\MRPEasy\Integration\Model\Coship');
        $model->syncronize($this->_rest);
    }
    
    /**
     * Fetch inventory from MRPEasy.
     *
     * @return void
     */
    protected function _updateInventory() 
    {
        $model = $this->_objectManager->create('\MRPEasy\Integration\Model\Inventory');
        $model->syncronize($this->_rest);
    }
}
