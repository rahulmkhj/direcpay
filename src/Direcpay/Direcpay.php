<?php

namespace RAHULMKHJ\PaymentGateways\Direcpay;
use RAHULMKHJ\PaymentGateways\Helpers\CryptAES;
use Exception;

class Direcpay {

	private $enc_key = 'qcAHa6tt8s0l5NN7UWPVAQ==';
	private $sandbox = FALSE;
	private $autoSubmit = FALSE;
	private $autoSubmitMessage = NULL;
	private $sandboxUrl = "https://test.timesofmoney.com/direcpay/secure/dpMerchantPayment.jsp";
	private $productionUrl = "https://www.timesofmoney.com/direcpay/secure/dpMerchantPayment.jsp";

	private $requestString;
	private $billingString;
	private $shippingString;

	private $otherDetails = NULL;
	private $requestParameters = [];
	private $billingDetails = [];
	private $shippingDetails = [];

	private $mandatory_params = [
		'MID','amount','orderNo','successUrl','failureUrl',
	];
	private $optional_params = [];
	private $mandatory_billing_details = [
		'name','address','city','state','pinCode','country','mobileNo','emailId'
	];
	private $optional_billing_details = ['phoneNo1','phoneNo2','phoneNo3','notes'];
	private $mandatory_shipping_details = [
		'name', 'address','city','state','pinCode','country','mobileNo'
	];
	private $optional_shipping_details = ['phoneNo1','phoneNo2','phoneNo3'];

//================================//================================//================================//

	public function setMerchant($mid, $enc_key)
	{
		$this->requestParameters['MID'] = $mid;
		$this->enc_key = $enc_key;
	}

	public function enableSandbox()
	{
		$this->sandbox = TRUE;
		$this->requestParameters['MID'] = 200904281000001;
		$this->enc_key = 'qcAHa6tt8s0l5NN7UWPVAQ==';
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

	public function setOtherDetails( Array $details )
	{
		$this->otherDetails = http_build_query($details);
	}

	public function buildEncryptedStrings($shipping_details_same = FALSE)
	{
		$aes = new CryptAES;
		$aes->set_key(base64_decode($this->enc_key));
		$aes->require_pkcs5();

		$this->requestString = $aes->encrypt( $this->_buildRequestString() );
		$this->billingString = $aes->encrypt( $this->_buildBillingString() );
		$shipping_details = $shipping_details_same ? $this->billingDetails : $this->shippingDetails;
		$this->shippingString = $aes->encrypt( $this->_buildShippingString($shipping_details) );
	}

	public function autoSubmit($message = "If you're not redirected automatically, please press following button")
	{
		$this->autoSubmit = TRUE;
		$this->autoSubmitMessage = $message;
	}

	public function generateForm()
	{
		$action = $this->sandbox ? $this->sandboxUrl : $this->productionUrl;
		if($this->autoSubmit)
			echo $this->autoSubmitMessage;
		echo <<<form
		<form name='ecom' action='$action' method='POST' id='ecom_form'>
			<input type='hidden' name='requestparameter' value="$this->requestString" />
			<input type='hidden' name='billingDtls' value='$this->billingString' />
			<input type='hidden' name='shippingDtls' value='$this->shippingString' />
			<input type='hidden' name='merchantId' value={$this->requestParameters['MID']} />
			<input type='submit' name='ecom_form' value='Submit' />
		</form>
form;
		if($this->autoSubmit) :
		echo <<<autosubmit
		<script type='text/javascript'>
		document.getElementById('ecom_form').submit();
		</script>
autosubmit;
		endif;

	}

	public static function parseSuccess($responseparams)
	{
		$arr = explode('|', $responseparams);
		$values = array_filter($arr);
		$keys = ['direcpayreferenceid','flag','country','currency','otherdetails','merchantordernumber','amount'];
		$result = array_combine($keys, $values);
		return $result;
	}

	private function _buildRequestString()
	{
		$string = "{$this->requestParameters['MID']}|DOM|IND|INR|{$this->requestParameters['amount']}|".
					"{$this->requestParameters['orderNo']}|";
		$string .= is_null($this->otherDetails) ? 'NULL' : $this->otherDetails;
		$string .= "|{$this->requestParameters['successUrl']}|{$this->requestParameters['failureUrl']}|".
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