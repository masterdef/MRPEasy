<?php

/**
 * MRPEasy REST API sample client.
 * 
 * Example:
$url = 'https://app.mrpeasy.com/rest/v1/';
$rest = new MRPEasyRestClient();
$rest->setApiKey(YOUR_API_KEY);
$rest->setAccessKey(YOUR_ACCESS_KEY);

//$rest->setOffset(100);
$items = $rest->get($url . 'items');
if ($items === false) {
	echo 'cUrl error: ' . $rest->getCurlError();
} else {
	$code = $rest->getCode();
	echo 'Response code: ' . $code. '<br />';
	echo 'Total articles: ' . $rest->getTotal() . '<br />';
	echo 'Showing articles from ' . $rest->getRangeStart() . ' to ' . $rest->getRangeEnd() . '<br /><br />';
	print_r($items);
}

$report = $rest->get($url . 'report/production/products');
if ($report === false) {
	echo 'cUrl error: ' . $rest->getCurlError();
} else {
	$code = $rest->getCode();
	echo 'Response code: ' . $code. '<br />';
	print_r($report);
}
 * 
 * @copyright MRPEasy.com 2016
 * @version 1.7
 */
namespace MRPEasy\Integration\lib;

class MRPEasyRestClient {
	/**
	 * Your API key in MRPEasy. You can get it in Settings - API access.
	 * @var int
	 */
	protected $_apiKey;
	
	/**
	 * Your API access key. You can define it in Settings - API access.
	 * @var string
	 */
	protected $_accessKey;
	
	/**
	 * HTTP code of the last response.
	 * The following options are possible:
	 *   200 - OK
	 *   201 - Created - returned if object was created successfully.
	 *   202 - Accepted - returned if object was updated successfully.
	 *   204 - No Content - returned if object was deleted successfully.
	 *   206 - Partial Content - returned if a list of objects was requested and only a part of it (up to 100 objects) was returned.
	 *   400 - Bad Request - most probably, it is caused by not valid data.
	 *   401 - Unauthorized - please check API credentials.
	 *   404 - Not Found - the object requested is not found.
	 *   429 - Too Many Requests - another request is running at the same time.
	 *   503 - Service Unavailable - Maintenance. Please try again later.
	 * @var int
	 */
	protected $_responseCode;
	
	/**
	 * If an array of objects is requested, it will contain not more that 100 objects at one time.
	 * This variable stores the index of the first returned object in the whole list.
	 * The first object in the list has index 0.
	 * You should check it if HTTP code of response is 206.
	 * @var int
	 */
	protected $_rangeStart;
	
	/**
	 * If an array of objects is requested, it will contain not more that 100 objects at one time.
	 * This variable stores the index of the last returned object in the whole list.
	 * You should check it if HTTP code of response is 206.
	 * @var int
	 */
	protected $_rangeEnd;
	
	/**
	 * If an array of objects is requested, it will contain not more that 100 objects at one time.
	 * This variable stores the total number of objects in the whole list.
	 * You should check it if HTTP code of response is 206.
	 * @var int
	 */
	protected $_total;
	
	/**
	 * If you want to receive next objects from the list, set the desired offset.
	 * It should be the index of the first object that should be returned.
	 * @var int
	 */
	protected $_offset;
	
	/**
	 * Store cUrl error if any.
	 */
	protected $_curlError;
	
	/**
	 * Set your API key.
	 * @param string $apiKey
	 * @return void
	 */
	public function setApiKey($apiKey) {
		$this->_apiKey = $apiKey;
	}
	
	/**
	 * Set your access key.
	 * @param string $accessKey
	 * @return void
	 */
	public function setAccessKey($accessKey) {
		$this->_accessKey = $accessKey;
	}
	
	/**
	 * Get response HTTP code of the last response.
	 * @return int
	 */
	public function getCode() {
		return $this->_responseCode;
	}
	
	/**
	 * Get index of the first returned object in the whole list.
	 * @return int
	 */
	public function getRangeStart() {
		return $this->_rangeStart;
	}
	
	/**
	 * Get index of the last returned object in the whole list.
	 * @return int
	 */
	public function getRangeEnd() {
		return $this->_rangeEnd;
	}
	
	/**
	 * Get total number of objects in the whole list.
	 * @return int
	 */
	public function getTotal() {
		return $this->_total;
	}
	
	/**
	 * Set the offset.
	 * @param int $offset
	 * @return void
	 */
	public function setOffset($offset) {
		$this->_offset = $offset;
	}
	
	/**
	 * Get cUrl error if it has happened.
	 * @return string
	 */
	public function getCurlError() {
		return $this->_curlError;
	}
	
	/**
	 * Make a GET request to receive one object or a list of objects.
	 * If object is not found, returns HTTP code 404.
	 * @param string $url
	 * @return mixed
	 */
	public function get($url) {
		$response = $this->_call('GET', $url);
		if ($response) {
			$response = json_decode($response);
		}
		return $response;
	}
	
	/**
	 * Make a POST request to create a new object.
	 * If data is incorrect, may return HTTP code 400 and, if possible, array of errors.
	 * @param string $url
	 * @param array|stdClass $data
	 * @return int - ID of new object on success, HTTP code 201
	 *   array of errors on fail
	 */
	public function post($url, $data) {
		$response = $this->_call('POST', $url, $data);
		if ($response) {
			$response = json_decode($response);
		}
		return $response;
	}
	
	/**
	 * Make a PUT request to update existing object.
	 * If data is incorrect, may return HTTP code 400 and, if possible, array of errors.
	 * If object is not found, may return HTTP code 404.
	 * @param string $url
	 * @param array|stdClass $data
	 * @return void on success, HTTP code must be 202
	 *   array of errors on fail
	 */
	public function put($url, $data) {
		$response = $this->_call('PUT', $url, $data);
		if ($response) {
			$response = json_decode($response);
		}
		return $response;
	}
	
	/**
	 * Make a DELETE request to delete one object.
	 * It is not allowed to delete all objects by one request.
	 * @param string $url
	 * @return void, HTTP code must be 204
	 */
	public function delete($url) {
		$response = $this->_call('DELETE', $url);
		if ($response) {
			$response = json_decode($response);
		}
		return $response;
	}
	
	/**
	 * Perform REST request.
	 * @param string $method
	 * @param string $url
	 * @param array|stdClass $data
	 */
	protected function _call($method, $url, $data = null) {
		$this->_responseCode = null;
		$this->_rangeStart = null;
		$this->_rangeEnd = null;
		$this->_total = null;
		
		$ch = curl_init();
		
		switch ($method) {
			case 'PUT':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, 1);
				
				if ($data) {
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
				}
				break;
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			default:
				if ($data) {
					$url = $url . '?' . http_build_query($data);
				}
		}
		
		$headers = array(
			'api_key: ' . $this->_apiKey,
			'access_key: ' . $this->_accessKey
		);
		if ($this->_offset) {
			$headers[] = 'Range: ' . $this->_offset . '-';
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this, '_header'));
		
		// Uncomment next lines if your cUrl is not configured to validate SSL cetrificates.
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		$result = curl_exec($ch);
		
		if ($result === false) {
			$error = curl_error($ch);
			$this->_curlError = $error;
			if (strpos($error, 'SSL') !== false) {
				$lineNr = __LINE__ - 9;
				$this->_curlError .= " \nPlease try to uncomment lines " . $lineNr . " and " . ($lineNr + 1) . ".\n";
			}
		} else {
			$this->_curlError = null;
		}
		
		curl_close($ch);
		
		return $result;
	}
	
	/**
	 * Parse response headers.
	 * @param $ch
	 * @param string $header
	 * @return int
	 */
	protected function _header($ch, $header) {
		$params = explode(' ', $header);
		if ($params[0] == 'HTTP/1.1') {
			$this->_responseCode = $params[1];
		} else if ($params[0] == 'Content-Range:') {
			sscanf($params[2], '%d-%d/%d', $this->_rangeStart, $this->_rangeEnd, $this->_total);
		}
		return strlen($header);
	}
}