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
 * This class is helper for API calls.
 * 
 * @category MRPEasy
 * @package  MRPEasy_Integration
 * @author   MRPEasy <apps@mrpeasy.com>
 * @link     https://www.mrpeasy.com
 */

namespace MRPEasy\Integration\Helper;

class Rest
{
    protected static $_rest;
    const MRPEASY_URL = 'https://app.mrpeasy.com/rest/v1/';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    
    /**
     * @var \Magento\AdminNotification\Model\InboxFactory
     */
    protected $_inboxFactory;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    	\Magento\AdminNotification\Model\InboxFactory $inboxFactory
    ) {
    	$this->_scopeConfig = $scopeConfig;
    	$this->_inboxFactory = $inboxFactory;
    }
    
    /**
     * Get REST client.
     *
     * @return MRPEasyRestClient | false
     */
    protected function _getRest() 
    {
        if (self::$_rest === null) {
            $apiKey = $this->_scopeConfig->getValue('mrpeasy/integration/api_key');
			$accessKey = $this->_scopeConfig->getValue('mrpeasy/integration/access_key');
            if (empty($apiKey) || empty($accessKey)) {
                $severity = \Magento\Framework\Notification\MessageInterface::SEVERITY_CRITICAL;
                $date = date('Y-m-d H:i:s');
                $title = 'MRPEasy API credentials missing';
                $description = 'Please go to ';
                $description .= '"Stores -> Configuration -> Services -> MRPEasy Integration -> Integration Settings" ';
                $description .= 'and set API key and Access key.';
                
                $this->_inboxFactory->create()->parse(
                    array(
                    array(
                        'severity' => $severity,
                        'date_added' => $date,
                        'title' => $title,
                        'description' => $description,
                        'url' => '',
                        'internal' => true
                    )
                    )
                );
                self::$_rest = false;
            } else {
                require_once realpath(dirname(__FILE__) . '/../lib/MRPEasyRestClient.php');
                self::$_rest = new \MRPEasy\Integration\lib\MRPEasyRestClient();
                self::$_rest->setApiKey($apiKey);
                self::$_rest->setAccessKey($accessKey);
            }
        }

        return self::$_rest;
    }
    
    /**
     * Create admin notification is API access is denied.
     *
     * @return void
     */
    public function logApiDenied() 
    {
        $severity = \Magento\Framework\Notification\MessageInterface::SEVERITY_CRITICAL;
        $date = date('Y-m-d H:i:s');
        $title = 'MRPEasy API access denied';
        $description = 'MRPEasy API cannot be accessed. Please check that credentials match at "MRPEasy -> Settings -> System settings -> Integration -> API access"  and "Magento -> Stores -> Configuration -> Services -> MRPEasy Integration -> Integration Settings".';
        $this->_inboxFactory->create()->parse(
        		array(
                    array(
                        'severity' => $severity,
                        'date_added' => $date,
                        'title' => $title,
                        'description' => $description,
                        'url' => '',
                        'internal' => true
                    )
                )
        );
    }
    
    /**
     * Create admin notification is API on error.
     *
     * @return void
     */
    public function logApiError($code, $url) 
    {
        $severity = \Magento\Framework\Notification\MessageInterface::SEVERITY_MAJOR;
        $date = date('Y-m-d H:i:s');
        $title = 'MRPEasy API returned error code ' . $code;
        $description = 'MRPEasy API responded with code ' . $code . ' on ' . $url . '. If this error persists, please inform MRPEasy support.';
        $this->_inboxFactory->create()->parse(
        		array(
                    array(
                        'severity' => $severity,
                        'date_added' => $date,
                        'title' => $title,
                        'description' => $description,
                        'url' => '',
                        'internal' => true
                    )
                )
        );
    }
    
    /**
     * Get URL with domain.
     *
     * @param string $url - request URL
     *
     * @return string
     */
    protected function _getAbsoluteUrl($url) 
    {
        return self::MRPEASY_URL . trim($url, '/');
    }
    
    /**
     * Check if REST credentials are set.
     *
     * @return boolean
     */
    public function isConfigured() 
    {
        $rest = $this->_getRest();
        return !!$rest;
    }
    
    /**
     * Call REST functions.
     *
     * @param string $method    - name of method that is called
     * @param array  $arguments - arguments that are passed to the method
     *
     * @return mixed
     */
    public function __call($method, $arguments) 
    {
        $rest = $this->_getRest();
        
        if ($method == 'get' || $method == 'post' || $method == 'put' || $method == 'delete') {
            if (isset($arguments[0])) {
                $arguments[0] = $this->_getAbsoluteUrl($arguments[0]);
            }
        }
        
        return call_user_func_array(array($rest, $method), $arguments);
    }
}
