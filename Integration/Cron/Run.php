<?php
namespace MRPEasy\Integration\Cron;
 
class Run 
{
    /**
     * @var boolean
     */
	protected static $_isRunning = false;
	
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
	
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    	\Magento\Framework\ObjectManagerInterface $objectManager
    ) {
    	$this->_scopeConfig = $scopeConfig;
    	$this->_objectManager = $objectManager;
    }
    
    public function execute()
    {
    	if (self::$_isRunning) {
            return $this;
        }
    	self::$_isRunning = true;
    	
    	$lastRun = $this->_scopeConfig->getValue('mrpeasy/integration/last_sync');
    	$now = time();
    	
    	if (empty($lastRun) || ($now - $lastRun) >= 300) {
    		$model = $this->_objectManager->create('\MRPEasy\Integration\Model\Sync');
			$model->syncronize();
    	}
    	
    	return $this;
    }
}
