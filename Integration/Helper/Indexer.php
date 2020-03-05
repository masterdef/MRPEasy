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
 * This class helps to disable and enable indexing.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */

namespace MRPEasy\Integration\Helper;

class Indexer
{
    /**
     * Store disabled indexes and their statuses for restoring.
     *
     * @var array
     */
    protected static $_disabledIndexes = array();
	
    /**
     * @var \Magento\Indexer\Model\IndexerFactory
     */
    protected $_indexerFactory;
	
    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
	protected $_indexerCollectionFactory;
	
	public function __construct(
			\Magento\Indexer\Model\IndexerFactory $indexerFactory,
			\Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
	) {
		$this->_indexerFactory = $indexerFactory;
		$this->_indexerCollectionFactory = $indexerCollectionFactory;
	}
    
    /**
     * Disable indexes.
     *
     * @return void
     */
    public function disable() 
    {
    	$indexerCollection = $this->_indexerCollectionFactory->create();
    	$ids = $indexerCollection->getAllIds();
    	foreach ($ids as $index) {
    		$indexer = $this->_indexerFactory->create();
    		$indexer->load($index);
    		self::$_disabledIndexes[$index] = $indexer->isScheduled();
    		$indexer->setScheduled(true);
    	}
    }
    
    /**
     * Restore indexing modes.
     *
     * @param [boolean] $isReindexNeeded - if needed, reindexAll() is called
     * 
     * @return void
     */
    public function enable($isReindexNeeded = true) 
    {
    	$indexerCollection = $this->_indexerCollectionFactory->create();
    	$ids = $indexerCollection->getAllIds();
    	foreach ($ids as $index) {
    		if (isset(self::$_disabledIndexes[$index])) {
	    		$indexer = $this->_indexerFactory->create();
	    		$indexer->load($index);
	    		if ($isReindexNeeded) {
	    			if (!self::$_disabledIndexes[$index]) {
	    				$indexer->reindexAll();
	    			}
	    		}
	    		
	    		$indexer->setScheduled(self::$_disabledIndexes[$index]);
	    		unset(self::$_disabledIndexes[$index]);
    		}
    	}
    }
}
