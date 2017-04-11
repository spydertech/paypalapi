<?php
use PayPal\Api\Amount;
use PayPal\Api\CreditCard;
use PayPal\Api\Details;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
class PaymentProcess{    
    private $apiContext;    
    public $errors;    
    public $paymentId;

    public $cardType;
    public $cardNumber;
    public $expiryMonth;
    public $expiryYear;
    public $cvv;
    public $firstName;
    public $lastName;


    public function __construct(){        
        $this->apiContext = new \PayPal\Rest\ApiContext(new \PayPal\Auth\OAuthTokenCredential(
        'ATJkTs-nq44KZan7YtJ5-uYK8b5xfcbCHb4TXVe9qiM8senrCbJvnWw94yaT9D_McuB-Q8l-aO9LfbCS', // ClientID
        'EA2AqqpqEUOR2zWqo091nZ0RoncSi6Oq5FMC9SwTLDJRqODBK-4hmj7VoPS6u5ICUv0qXFLtt7c6jjhE' // ClientSecret
        ));        
        $this->apiContext->setConfig(array('mode' => 'sandbox','log.LogEnabled' => true,'log.FileName' => __DIR__ . '/PayPal.log','log.LogLevel' => 'INFO', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS                
       'cache.enabled' => false,                    
       ));    
    }    
    // @return bool  
    public function process( $products, $shippingCost ) {        
        $card = new CreditCard();        
        $card->setType($this->cardType)->setNumber($this->cardNumber)->setExpireMonth($this->expiryMonth)->setExpireYear($this->expiryYear)->setCvv2($this->cvv)->setFirstName($this->firstName)->setLastName($this->lastName);
        $fi = new FundingInstrument();        
        $fi->setCreditCard($card);        
        $payer = new Payer();        
        $payer->setPaymentMethod("credit_card")->setFundingInstruments(array($fi));        
        $itemList = new ItemList();        
        $total = 0;        
        
        foreach($products as $product) {            
            $item = new Item();            
            $item->setName($product['product_name'])->setDescription($product['description'])->setCurrency('USD')->setQuantity($product['qty'])->setTax(0)->setPrice( $product['price'] );
            $total += $product['price'] * $product['qty'];
            $itemList->addItem($item);        
        }            
        $details = new Details();        
        $details->setShipping($shippingCost)->setTax(0.00)->setSubtotal($total);
        $amount = new Amount();        
        $amount->setCurrency("USD")->setTotal($total + $shippingCost)->setDetails($details);
        $transaction = new Transaction();        
        $transaction->setAmount($amount)->setItemList($itemList)->setDescription("Payment description")->setInvoiceNumber(uniqid());
        $payment = new Payment();        
        $payment->setIntent("sale")->setPayer($payer)->setTransactions(array($transaction));
        $request = clone $payment;        
        try {            
            $payment->create($this->apiContext);        
        } catch (PayPal\Exception\PayPalConnectionException $pce) {
            // Write any errors to log file
            file_put_contents(__DIR__ . '/paypal_error.log', var_export(json_decode($pce->getData()), true), FILE_APPEND);
            $this->errors = 'Failed to charge your card';            
            // error reporting
            $this->errors = $pce->getMessage();
            return false;        
        }        
        if($payment->getFailureReason()) {            
            $this->errors = $payment->getFailureReason();            
            return false;        
        }        
        $this->paymentId = $payment->getId();        
        return true;    
    }}
