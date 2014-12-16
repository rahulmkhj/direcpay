<?php
namespace RAHULMKHJ\PaymentGateways\Direcpay;

class DirecpayResponse {

	private $response = NULL;
	private $details = NULL;

	public function __construct( $response_params )
	{
		$arr = explode('|', $response_params);
		$values = array_filter($arr);
		$keys = ['direcpayreferenceid','status','country','currency','otherdetails','merchantordernumber','amount'];
		$result = array_combine($keys, $values);
		parse_str(urldecode($result['otherdetails']), $this->details);
		unset($result['otherdetails']);
		$this->response = $result;
	}

	public function txnStatus()
	{
		return $this->response['status'];
	}

	public function txnSucceed()
	{
		return $this->response['status'] == 'SUCCESS' ? TRUE : FALSE;
	}

	public function txtFailed()
	{
		return $this->response['status'] == 'FAIL' ? TRUE : FALSE;
	}

	public function getResponse()
	{
		return $this->response;
	}

	public function getDetails()
	{
		return $this->details;
	}
}
//end of file DirecpayResponse.php