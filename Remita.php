<?php
/**
 * Related methods and properties
 * for Remita Direct Debit operations
 *
 * @author Temidayo Oluwabusola
 */
class Remita {

    private $merchantId = '2547916';
    private $serviceTypeId = '4430731';
    private $apiKey = '1946';
    private $serviceCharge = 105;//Transaction fee for each debit request
    private $responseURL = 'http://localhost/remita/response.php';
    private $baseRemitaURL = 'http://www.remitademo.net/remita/ecomm/';
    private $debitDirectProcessURL = 'http://www.remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/echannel/mandate/payment/send';
    private $mandateSetupURL = 'http://www.remitademo.net/remita/ecomm/mandate/setup.reg';
    private $baseMandateViewURL = 'http://www.remitademo.net/remita/ecomm/mandate/form/';
    private $cancelDebitDirectURL = 'http://www.remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/echannel/mandate/payment/stop';
    private $terminateMandateURL = 'http://www.remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/echannel/mandate/stop';

    public function getTransactionStatus($loanId){
          //$url = 'http://www.remitademo.net/remita/ecomm/merchantId/requestId/hash/orderstatus.reg';
        $hash = hash('sha512', $loanId . $this->apiKey . $this->merchantId);
        $url = $this->baseRemitaURL . $this->merchantId .'/' . $loanId . '/' . $hash . '/'. 'orderstatus.reg'; 
        echo $url;
        $response = file($url);          
        return $response[0];
 
    }

public function getTransactionStatusByRRR($RRR){
          
  //http://www.remitademo.net/remita/ecomm/merchantId/RRR/hash/status.reg
//HASH: SHA512(RRR + apiKey + merchantId)
        $hash = hash('sha512', $RRR . $this->apiKey . $this->merchantId);
        $url = $this->baseRemitaURL . $this->merchantId .'/' . $RRR . '/' . $hash . '/'. 'status.reg'; 
        $response = file($url);
        return json_decode($response[0],true);
 
    }    
    
    public function callRemitaApiGet($endpointURL) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $output = curl_exec($ch);
        return $output;
    }

    /**
     * 
     * @param string $endPoint - The URL
     * @param array $postData - data to be sent with POST method, it will be converted to JSON format 
     */
    public function callRemitaApiPost($endPoint, $postData) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endPoint);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($postData)))
        );

        $output = curl_exec($ch);
        return $output;
    }

    public function getViewMandateURL($mandateId, $requestId){
        $hash = hash('sha512', $this->merchantId . $this->apiKey . $requestId);
        $url = $this->baseMandateViewURL . $this->merchantId . '/' . $hash . '/' . $mandateId . '/' . $requestId . '/' . 'rest.reg'; 
        return $url;
        }
    /**
     * 
     * @param type $mandateId - returned ID from Mandate ID
     * @param type $requestId - this is the ID for the request, we use Mambu Loan ID for the purpose of this application
     */
    public function viewMandate($mandateId, $requestId) {
        $hash = hash('sha512', $this->merchantId . $this->apiKey . $requestId);
        $url = $this->baseMandateViewURL . $this->merchantId . '/' . $hash . '/' . $mandateId . '/' . $requestId . '/' . 'rest.reg'; 
        $result = $this->callRemitaApiGet($url);
        return $result;
    }

    public function setUpDirectDebitMandate($post) {
        $mandateData = array('merchantId' => $this->merchantId,
            'serviceTypeId' => $this->serviceTypeId,
            'requestId' => $post['request_id'],
            'hash' => $this->getMandateSetupHash($post['amt'], $post['request_id']),
            'payerName' => $post['payerName'],
            'payerEmail' => $post['payerEmail'],
            'payerPhone' => $post['payerPhone'],
            'payerBankCode' => $post['payerBankCode'],
            'payerAccount' => $post['payerAccount'],
            'narration' => $post['narration'],
            'startDate' => $post['startDate'],
            'endDate' => $post['endDate'],
            'mandateType' => 'DD',
            'maxNoOfDebits' => $post['maxNoOfDebits'],
            'amount' => $post['amt']);
        //echo json_encode($mandateData);
        $result = $this->callRemitaApiPost($this->mandateSetupURL, $mandateData);
        return json_decode($this->removeJSONP($result), TRUE);
    }

    public function getMandateStatus($mambuLoanId) {
        $requestId = $this->getRequestId($mambuLoanId);
        $hash = hash('sha512', $requestId . $this->apiKey . $this->merchantId);
        //http://www.remitademo.net/remita/ecomm/mandate/merchantId/requestId/hash/status.reg
        $url = $this->baseRemitaURL.'mandate/' . $this->merchantId . "/" . $requestId . "/" . $hash . "/status.reg";
        $response = file($url);
        return json_decode($this->removeJSONP($response[0]), TRUE);
    }

    public function getMandateHistory($mambuLoanId) {
        $hash = hash('sha512', $mambuLoanId . $this->apiKey . $this->merchantId);
//    http://www.remitademo.net/remita/ecomm/mandate/merchantId/requestId/hash/history.reg
        $url = $this->baseRemitaURL."mandate/" . $this->merchantId . "/" . $mambuLoanId . "/" . $hash . "/history.reg";
        $response = file($url);
        return $response;
        //return json_decode($this->removeJSONP($response), TRUE);
    }

    /**
     * Remove the jsonp that wraps around almost
     * every json response from REMITA
     * @param type $data
     * @return type
     */
    public function removeJSONP($data) {
        if (substr($data, 0, 5) == 'jsonp') {
            $firstPass = substr($data, 5);
            return trim($firstPass, ') (');
        }
        return $data;
    }

	/**
     * Sends direct debit request
     * @param array $directDebitData
     * @return json
     */
    public function doDirectDebit($directDebitData) {
//HASH: SHA512(merchantId+serviceTypeId+requestId+api_key)
        //merchantId+serviceTypeId+requestId+totalAmount+api_key
        $hash = hash('sha512', $this->merchantId . $this->serviceTypeId . $directDebitData['request_id']. $directDebitData['amount'] . $this->apiKey);
        $postData = array('merchantId' => $this->merchantId,
            'serviceTypeId' => $this->serviceTypeId,
            'hash' => $hash,
            'totalAmount' => $directDebitData['amount'],
            'mandateId' => $directDebitData['remita_mandate_id'],
            'fundingAccount' => $directDebitData['account_number'],
            'fundingBankCode' => $directDebitData['bank_code'],
            'requestId' => $directDebitData['request_id']
        );  
        print_r(json_encode($postData));
        $result = $this->callRemitaApiPost($this->debitDirectProcessURL, $postData);
        return json_decode($this->removeJSONP($result), TRUE);
    }

    public function getRemitaParameters() {
        return array('merchantId' => $this->merchantId,
            'serviceTypeId' => $this->serviceTypeId,
            'apiKey' => $this->apiKey,
            'responseURL' => $this->responseURL,
            'serviceCharge' => $this->serviceCharge
        );
    }

    /*
     * Mandate Activation Hash
     */

    public function getMandateSetupHash($amount, $requestId) {
        //$hash = hash('sha512',$merchantId.$serviceTypeId.$requestId.$amt.$responseurl.$apiKey);
        return hash('sha512', $this->merchantId . $this->serviceTypeId . $requestId . $amount . $this->apiKey);
    }

    public function getMandateForm($post) {
        $postdata = http_build_query(
                array(
                    'merchantId' => $this->merchantId,
                    'serviceTypeId' => $this->serviceTypeId,
                    'requestId' => $post['mambu_loan_id'],
                    'hash' => $this->getHash($post['amt'], $post['mambu_loan_id']),
                    'payerName' => $post['payerName'],
                    'payerEmail' => $post['payerEmail'],
                    'payerPhone' => $post['payerPhone'],
                    'payerBankCode' => $post['payerBankCode'],
                    'payerAccount' => $post['payerAccount'],
                    'narration' => $post['narration'],
                    'startDate' => $post['startDate'],
                    'endDate' => $post['endDate'],
                    'mandatetype' => $post['mandatetype'],
                    'maxNoOfDebits' => $post['maxNoOfDebits'],
                    'amt' => $post['amt'],
                    'responseurl' => $this->responseURL));

        $options = array('http' =>
            array('method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents('http://www.remitademo.net/remita/ecomm/mandate/init.reg', false, $context);
        return $result;
    }

    public function cancelDirectDebit($directDebitData) {
    //HASH: SHA512(transactionRef+merchantId+requestId+api_key)
        $hash = hash('sha512', $directDebitData['transaction_ref'].$this->merchantId .$directDebitData['mambu_loan_id']. $this->apiKey);
        $postData = array('merchantId' => $this->merchantId,
            'serviceTypeId' => $this->serviceTypeId,
            'hash' => $hash,
            'mandateId' => $directDebitData['remita_mandate_id'],
            'transactionRef' => $directDebitData['transaction_ref'],
            'requestId' => $directDebitData['mambu_loan_id']
        );

        $result = $this->callRemitaApiPost($this->cancelDebitDirectURL, $postData);
        return json_decode($this->removeJSONP($result), TRUE);
    }

    /**
     * completely stops or terminates a mandate
     * @param $directDebitData
     * @return mixed
     */
    public function terminateMandate($mandateData) {
        //HASH: SHA512(mandateId+merchantId+requestId+api_key)
        $requestId = $this->getRequestId($mandateData['mambu_loan_id']);
        $hash = hash('sha512', $mandateData['remita_mandate_id'].$this->merchantId .$requestId. $this->apiKey);

        $postData = array('merchantId' => $this->merchantId,
            'mandateId' => $mandateData['remita_mandate_id'],
            'hash' => $hash,
            'requestId' => $requestId
        );

        $result = $this->callRemitaApiPost($this->terminateMandateURL, $postData);
        return json_decode($this->removeJSONP($result), TRUE);
    }

    

    
}
