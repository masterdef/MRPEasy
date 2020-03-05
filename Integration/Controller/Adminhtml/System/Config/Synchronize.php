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
 * This class starts syncronization when it is requested by the user.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Synchronize extends Action
{
    /**
     * Synchronize
     *
     * @return void
     */
    public function execute()
    {
		$model = $this->_objectManager->create('\MRPEasy\Integration\Model\Sync');
		$model->syncronize();
		
        $this->_redirect('adminhtml/system_config/edit/section/mrpeasy');
    }
    
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MRPEasy_Integration::config');
    }
}