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
 * This class gives Yes/No options with No as default.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Model;
class NoYes implements \Magento\Framework\Option\ArrayInterface
{
    /**
    * Get options.
    *
    * @return array
    */
    public function toOptionArray() 
    {
        return array(
            array('value' => 0, 'label' => 'No'),
            array('value' => 1, 'label' => 'Yes')
        );
    }
}
