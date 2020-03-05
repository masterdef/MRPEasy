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
 * This class shows 'Syncronize now' in Configuration.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */
namespace MRPEasy\Integration\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\ObjectManagerInterface;

class BtnSync extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string
     */
    protected $_template = 'MRPEasy_Integration::system/config/btnSync.phtml';
    
    /**
     * Set template to itself
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::BUTTON_TEMPLATE);
        }
        return $this;
    }
    
    /**
     * Render button
     *
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    
    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_toHtml();
    }
    
    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('mrpeasy_integration/system_config/synchronize');
    }
    
    /**
     * Generate collect button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
	$apiKey = $this->_scopeConfig->getValue('mrpeasy/integration/api_key');
	$accessKey = $this->_scopeConfig->getValue('mrpeasy/integration/access_key');
	if (empty($apiKey) || empty($accessKey)) {
            $isDisabled = true;
        } else {
            $isDisabled = false;
        }
	
	$url = $this->getAjaxUrl();
	
	$html = '<br /><br />';
	
	$lastRun = $this->_scopeConfig->getValue('mrpeasy/integration/last_sync');
        if (!empty($lastRun)) {
            $html .= '<p>Last syncronization: ' . date('Y-m-d H:i:s', $lastRun) . '</p>';
        }
	
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'btn_sync',
                'label' => __('Syncronize now'),
                'disabled' => $isDisabled,
		'onclick' => 'setLocation(\'' . $url . '\')'
            ]
        );
	
        $html .= $button->toHtml();
	
	return $html;
    }
}