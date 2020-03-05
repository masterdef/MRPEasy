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
 * This class returns syncronization options of sales orders.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;

class SyncTypes implements \Magento\Framework\Option\ArrayInterface
{
    const ORDER_SYNC_SEPARATELY = 10;
    const ORDER_SYNC_GROUP = 20;
    const ORDER_SYNC_CUMULATIVELY = 30;
    
    /**
    * Get options.
    *
    * @return array
    */
    public function toOptionArray() 
    {
        return array(
            array(
        'value' => self::ORDER_SYNC_SEPARATELY,
        'label' => 'separately'
        ),
            array(
        'value' => self::ORDER_SYNC_GROUP, 
        'label' => 'group orders'
        ),
            array(
        'value' => self::ORDER_SYNC_CUMULATIVELY, 
        'label' => 'group products'
        )
        );
    }
}
