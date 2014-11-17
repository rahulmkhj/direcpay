<?php

namespace RAHULMKHJ\PaymentGateways\Direcpay;
use RAHULMKHJ\PaymentGateways\Helpers\CryptAES;
use Exception;

class Direcpay {

	private $enc_key = 'qcAHa6tt8s0l5NN7UWPVAQ==';
	private $sandbox = FALSE;
	private $sandboxUrl = "https://test.timesofmoney.com/direcpay/secure/dpMerchantPayment.jsp";
	private $productionUrl = "https://www.timesofmoney.com/direcpay/secure/dpMerchantPayment.jsp";

	private $requestString;
	private $billingString;
	private $shippingString;

	private $requestParameters = [];
	private $billingDetails = [];
	private $shippingDetails = [];

	private $mandatory_params = [
		'MID','amount','orderNo','successUrl','failureUrl',
	];
	private $optional_params = ['otherDetails'];
	private $mandatory_billing_details = [
		'name','address','city','state','pinCode','country','mobileNo','emailId'
	];
	private $optional_billing_details = ['phoneNo1','phoneNo2','phoneNo3','notes'];
	private $mandatory_shipping_details = [
		'name', 'address','city','state','pinCode','country','mobileNo'
	];
	private $optional_shipping_details = ['phoneNo1','phoneNo2','phoneNo3'];

//================================//================================//================================//

	public function setEncryptionKey($key)
	{
		$this->enc_key = $key;
	}

	public function enableSandbox()
	{
		$this->sandbox = TRUE;
		$this->requestParameters['MID'] = 200904281000001;
	}

	public function setRequestParameters(Array $params )
	{
		$this->_checkParams($params);
		foreach($params as $key => $value ) {
			$this->requestParameters[$key] = $value;
		}
	}

	public function setBillingDetails(Array $details)
	{
		$this->_checkAllowedBillingDetails($details);
		$this->billingDetails = $details;
	}

	public function setShippingDetails( Array $details = NULL )
	{
		if( $details == NULL ) {
			$details = $this->billingDetails;
		}
		$this->_checkAllowedShippingDetails($details);
		$this->shippingDetails = $details;
	}

	public function buildEncryptedStrings($shipping_details_same = FALSE)
	{
		$aes = new CryptAES;
		$aes->set_key(base64_decode($this->enc_key));
		$aes->require_pkcs5();


		echo $this->_buildRequestString(), "<br />", $this->_buildBillingString(),
		"<br />", $this->_buildShippingString($this->billingDetails);

		$this->requestString = $aes->encrypt( $this->_buildRequestString() );
		$this->billingString = $aes->encrypt( $this->_buildBillingString() );
		$shipping_details = $shipping_details_same ? $this->billingDetails : $this->shippingDetails;
		$this->shippingString = $aes->encrypt( $this->_buildShippingString($shipping_details) );
	}

	public function initTransaction()
	{
		$direcpay_url = $this->sandbox ? $this->sandboxUrl : $this->productionUrl;
		
		$ch = curl_init();
		curl_setopt_array($ch, [
				CURLOPT_URL				=>		$direcpay_url,
				CURLOPT_HTTP_VERSION	=>		CURL_HTTP_VERSION_1_1,
				CURLOPT_POST			=>		1,
				CURLOPT_POSTFIELDS		=>		http_build_query([
																	'requestparameter'	=> $this->requestString,
																	'billingDtls'		=>	$this->billingString,
																	'shippingDtls'		=>	$this->shippingString,
																	]),
				CURLOPT_FOLLOWLOCATION	=>		TRUE,
				CURLOPT_RETURNTRANSFER	=>		TRUE,
				CURLOPT_HEADER 			=>		TRUE,
				CURLOPT_USERAGENT		=>		'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
				CURLOPT_REFERER			=>		$direcpay_url,	//"http://localhost:8888/direcpay/",
				CURLOPT_SSL_VERIFYPEER 	=>		TRUE,
				CURLOPT_SSL_VERIFYHOST	=>		2,
				CURLOPT_HTTPHEADER		=>		[$direcpay_url,],
				// CURLOPT_VERBOSE			=>		TRUE,
			]);
		$output = curl_exec($ch);
		// $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// pr($status);
		// pr($output);
		print_r($output);
	}

	public function generateForm()
	{
		$action = $this->sandbox ? $this->sandboxUrl : $this->productionUrl;

		echo <<<form
		<form name='ecom' action='$action' method='POST'>
			<input type='hidden' name='requestparameter' value="$this->requestString" />
			<input type='hidden' name='billingDtls' value='$this->billingString' />
			<input type='hidden' name='shippingDtls' value='$this->shippingString' />
			<input type='hidden' name='merchantId' value={$this->requestParameters['MID']} />
			<input type='submit' name='submit' value='Submit' />
		</form>
form;
	}

	private function _buildRequestString()
	{
		$string = "{$this->requestParameters['MID']}|DOM|IND|INR|{$this->requestParameters['amount']}|".
					"{$this->requestParameters['orderNo']}|";
		$string .= isset($this->requestParameters['otherDetails']) ? "{$this->requestParameters['otherDetails']}|" : 'NULL';
		$string .= "{$this->requestParameters['successUrl']}|{$this->requestParameters['failureUrl']}|".
				"TOML";
		return $string;
	}

	private function _buildBillingString()
	{
		$string = "{$this->billingDetails['name']}|{$this->billingDetails['address']}|{$this->billingDetails['city']}|".
					"{$this->billingDetails['state']}|{$this->billingDetails['pinCode']}|{$this->billingDetails['country']}";
		$string .= isset($this->billingDetails['phoneNo1']) ? "|{$this->billingDetails['phoneNo1']}" : '|NULL';
		$string .= isset($this->billingDetails['phoneNo2']) ? "|{$this->billingDetails['phoneNo2']}" : '|NULL';
		$string .= isset($this->billingDetails['phoneNo3']) ? "|{$this->billingDetails['phoneNo3']}" : '|NULL';
		$string .= isset($this->billingDetails['mobileNo']) ? "|{$this->billingDetails['mobileNo']}" : '|NULL';
		$string .= isset($this->billingDetails['emailId']) ? "|{$this->billingDetails['emailId']}" : '|NULL';
		$string .= isset($this->billingDetails['notes']) ? "|{$this->billingDetails['notes']}" : '|NULL';

		return $string;
	}

	private function _buildShippingString(Array $details )
	{
		$string = "{$details['name']}|{$details['address']}|{$details['city']}|".
					"{$details['state']}|{$details['pinCode']}|{$details['country']}";
		$string .= isset($details['phoneNo1']) ? "|{$details['phoneNo1']}" : '|NULL';
		$string .= isset($details['phoneNo2']) ? "|{$details['phoneNo2']}" : '|NULL';
		$string .= isset($details['phoneNo3']) ? "|{$details['phoneNo3']}" : '|NULL';
		$string .= isset($details['mobileNo']) ? "|{$details['mobileNo']}" : '|NULL';
		return $string;
	}

	private function _checkParams( Array $params)
	{
		foreach( $params as $key => $value ) {
			if( ! in_array($key, $this->mandatory_params) && ! in_array($key, $this->optional_params) )
				throw new Exception("Invalid Parameter: $key");
		}
		foreach( $this->mandatory_params as $param ) {
			if( ! array_key_exists($param, $params) && ! isset($this->requestParameters[$param]) )
				throw new Exception("Missing required parameter: $param");
		}
	}

	private function _checkAllowedBillingDetails( Array $details )
	{
		foreach( $details as $key => $value )
		{
			if( ! in_array($key, $this->mandatory_billing_details) && ! in_array($key, $this->optional_billing_details) )
				throw new Exception("Invalid Key: $key");
		}
		foreach( $this->mandatory_billing_details as $detail ) {
			if( ! array_key_exists($detail, $details) )
				throw new Exception("Missing required paramenter: $detail");
		}
	}

	private function _checkAllowedShippingDetails( Array $details )
	{
		foreach( $details as $key => $value ) {
			if( ! in_array($key, $this->mandatory_shipping_details) && ! in_array($key, $this->optional_shipping_details) )
				throw new Exception("Invalid key in shipping details: $key");
		}
		foreach( $this->mandatory_shipping_details as $detail ) {
			if( ! array_key_exists($detail, $details) )
				throw new Exception("Missing required parameter from shipping details: $detail");
		}
	}

}

//end of file Direcpay.php