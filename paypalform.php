<?php
    $transaction_total = $_POST['finaltotal'];
    $transaction_shipping = $_POST['shipping_total'];

//If Customer submits a payment
if (isset($_POST['payment_paypal_submit'])) {
    //Variables for Transaction
    $transaction_total = $_POST['amount_total'];
    $transaction_shipping = $_POST['amount_shipping'];
    $transaction_discount = 0.00;
    $order_number = strval( date("ymd") . date("his")); //Their Order Number
    $user_id= $session['user_id'];
    $transaction_status = 'P';
    $transaction_type = 'CC';


    //Get Customer Info
    if ($getcustomer = $db->prepare("SELECT user_fname, user_lname, user_email, user_phone, user_phone2, user_street, user_city, user_state, user_zip, user_company, user_shipping_fname, user_shipping_lname, user_shipping_email, user_shipping_phone, user_shipping_phone2, user_shipping_street, user_shipping_city, user_shipping_state, user_shipping_zip, user_shipping_company FROM users WHERE user_id=?")) {
        $getcustomer->bind_param('s', $user_id);
        $getcustomer->execute();
        $getcustomer->bind_result($user_fname, $user_lname, $user_email, $user_phone, $user_phone2, $user_street, $user_city, $user_state, $user_zip, $user_company, $user_shipping_fname, $user_shipping_lname, $user_shipping_email, $user_shipping_phone, $user_shipping_phone2, $user_shipping_street, $user_shipping_city, $user_shipping_state, $user_shipping_zip, $user_shipping_company);
        $getcustomer->fetch();
        $getcustomer->close();
    }

    //Get Company Info
    if ($getsettings = $db->prepare("SELECT setting_company, setting_street, setting_city, setting_state, setting_zip, setting_phone, setting_email FROM settings")) {
        $getsettings->execute();
        $getsettings->bind_result($setting_company, $setting_street, $setting_city, $setting_state, $setting_zip, $setting_phone, $setting_email);
        $getsettings->fetch();
        $getsettings->close();
    }


    //INSERT INTO transactions
    if ($inserttransactions = $db -> prepare("INSERT INTO transactions (user_id, order_number, transaction_amount, transaction_discount, transaction_status, transaction_shipping, transaction_type) VALUES (?, ?, ?, ?, ?, ?, ?)")) {
        $inserttransactions -> bind_param('sssssss', $user_id, $order_number, $transaction_total, $transaction_discount, $transaction_status, $transaction_shipping, $transaction_type);
        $inserttransactions -> execute();
        //close transaction insert
        $inserttransactions->close();
    }
    else
    {
        echo "Error";
        die();
    }

    //Get the transaction id
    $transaction_id = $db->insert_id;



    if ($msg == '') {
        if(!isset($_COOKIE['cart']) || !is_array( $cart = json_decode($_COOKIE['cart'], true) )) {
            $cart = array();
        }
        if(!$cart) {
            die('Products not found.');
        }

        $user_products = [];

        foreach ($cart as $product_id => $product) {
            if ($product['type'] == 'multi') {
                foreach ($product['items'] as $multi_index => $item) {
                    $diameter = $item['diameter'];
                    $hardware = $item['hardware'];
                    $qty = $item['qty'];
                    $addon = [];

                    if ($selectdiameter = $db->prepare("SELECT p.product_name, p.product_webname, p.product_photo, p.product_type, vd.data_id, vd.data_name, vd.data_price, vd.data_sku, vd.data_weight, vt.type_name FROM products AS p INNER JOIN variation_data AS vd ON p.product_id=vd.product_id INNER JOIN variation_pivot AS vp ON vd.data_id=vp.data_id INNER JOIN variation_types AS vt ON vp.type_id=vt.type_id WHERE vp.product_id=? AND vp.data_id=?")) {
                        $selectdiameter->bind_param('ss', $product_id, $diameter);
                        $selectdiameter->execute();
                        $selectdiameter->bind_result($product_name, $product_webname, $product_photo, $product_type, $data_id, $data_name, $data_price, $data_sku, $data_weight, $type_name);
                        $selectdiameter->fetch();
                        $selectdiameter->close();

                    }

                    //INSERT INTO products_ordered
                    if ($insertproductsordered = $db -> prepare("INSERT INTO products_ordered (transaction_id, product_id, data_id, product_name, data_name, type_name, data_sku, data_price, ordered_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                        $insertproductsordered -> bind_param('sssssssss', $transaction_id, $product_id, $data_id, $product_name, $data_name, $type_name, $data_sku, $data_price, $qty);
                        $insertproductsordered -> execute();
                        //close products_ordered insert
                        $insertproductsordered->close();
                    }

                    //Get the ordered id
                    $ordered_id = $db->insert_id;

                    if ($selecthardware = $db->prepare("SELECT vd.data_name, vd.data_webname, vd.data_sku, vd.data_price, vd.data_id FROM variation_data AS vd INNER JOIN products AS p ON p.product_id=vd.product_id INNER JOIN variation_pivot AS vp ON vd.data_id=vp.data_id INNER JOIN variation_types AS vt ON vp.type_id=vt.type_id WHERE vp.product_id=? AND vp.data_id=?")) {
                        $selecthardware->bind_param('ss', $product_id, $hardware);
                        $selecthardware->execute();
                        $selecthardware->bind_result($data_name2, $data_webname, $data_sku2, $data_price2, $data_id2);
                        $selecthardware->fetch();
                        $selecthardware->close();
                        $addon =  [
                            'product_name' => $data_name2,
                            'price' => $data_price2,
                            'description' => $data_sku2,
                            'qty' => $qty
                        ];
                    }

                    //INSERT INTO products_ordered_meta
                    if ($insertproductsorderedmeta = $db -> prepare("INSERT INTO products_ordered_meta (ordered_id, transaction_id, data_id, parent_data_id, data_name, data_sku, data_price, data_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")) {
                        $insertproductsorderedmeta -> bind_param('ssssssss', $ordered_id, $transaction_id, $data_id2, $data_id, $data_name2, $data_sku2, $data_price2, $qty);
                        $insertproductsorderedmeta -> execute();
                        //close products_ordered insert
                        $insertproductsorderedmeta->close();
                    }

                    $user_products[] =  [
                        'product_name' => $product_name,
                        'price' => $data_price,
                        'description' => $data_sku,
                        'qty' => $qty
                    ];

                    if(count($addon)) {
                        $user_products[] = $addon;
                    }

                }


            } else {
                $addon = [];
                $hardware = $product['hardware'];
                $qty = $product['qty'];

                if ($selectdiameter = $db->prepare("SELECT p.product_name, p.product_type, p.product_price, p.product_sku FROM products AS p WHERE p.product_id=?")) {
                    $selectdiameter->bind_param('s', $product_id);
                    $selectdiameter->execute();
                    $selectdiameter->bind_result($product_name2, $product_type2, $product_price2, $product_sku2);
                    $selectdiameter->fetch();
                    $selectdiameter->close();
                }

                //INSERT INTO products_ordered
                if ($insertproductsordered = $db -> prepare("INSERT INTO products_ordered (transaction_id, product_id, product_name, product_sku, product_price, ordered_quantity) VALUES (?, ?, ?, ?, ?, ?)")) {
                    $insertproductsordered -> bind_param('ssssss', $transaction_id, $product_id, $product_name2, $product_sku2, $product_price2, $qty);
                    $insertproductsordered -> execute();
                    //close products_ordered insert
                    $insertproductsordered->close();
                }

                //Get the ordered id
                $ordered_id = $db->insert_id;

                if(!empty($hardware)){

                    //SELECT hardware options
                    if ($selecthardware = $db->prepare("SELECT vd.data_name, vd.data_webname, vd.data_sku, vd.data_price, vd.data_id FROM variation_data AS vd INNER JOIN products AS p ON p.product_id=vd.product_id INNER JOIN variation_pivot AS vp ON vd.data_id=vp.data_id INNER JOIN variation_types AS vt ON vp.type_id=vt.type_id WHERE vp.product_id=? AND vp.data_id=?")) {
                        $selecthardware->bind_param('ss', $product_id, $hardware);
                        $selecthardware->execute();
                        $selecthardware->bind_result($data_name2, $data_webname, $data_sku2, $data_price2, $data_id2);
                        $selecthardware->fetch();
                        $selecthardware->close();
                    }

                    //INSERT INTO products_ordered_meta
                    if ($insertproductsorderedmeta = $db -> prepare("INSERT INTO products_ordered_meta (ordered_id, transaction_id, data_id, parent_data_id, data_name, data_sku, data_price, data_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")) {
                        $insertproductsorderedmeta -> bind_param('ssssssss', $ordered_id, $transaction_id, $data_id2, $data_id, $data_name2, $data_sku2, $data_price2, $qty);
                        $insertproductsorderedmeta -> execute();
                        //close products_ordered insert
                        $insertproductsorderedmeta->close();
                    }
                    $addon =  [
                        'product_name' => $data_name2,
                        'price' => $data_price2,
                        'description' => $data_sku2,
                        'qty' => $qty
                    ];

                }else{
                    $data_id2   = '0';
                    $data_name2 = 'No Hardware';

                    //INSERT INTO products_ordered_meta
                    if ($insertproductsorderedmeta = $db -> prepare("INSERT INTO products_ordered_meta (ordered_id, transaction_id, data_id, parent_data_id, data_name) VALUES (?, ?, ?, ?, ?)")) {
                        $insertproductsorderedmeta -> bind_param('sssss', $ordered_id, $transaction_id, $data_id2, $data_id, $data_name2);
                        $insertproductsorderedmeta -> execute();
                        //close products_ordered insert
                        $insertproductsorderedmeta->close();
                    }

                }

                $user_products[] =  [
                    'product_name' => $product_name2,
                    'price' => $product_price2,
                    'description' => $product_sku2,
                    'qty' => $qty
                ];
                if(count($addon)) {
                    $user_products[] = $addon;
                }
            }
        }

        //Send Credit Card Details to Paypal
        require(__DIR__ . '/PaymentProcess.php');
        $paypal = new PaymentProcess();
        $paypal->cardType = 'visa';
        $paypal->cardNumber = '4618912173232879';
        $paypal->expiryMonth = '01';
        $paypal->expiryYear = '2021';
        $paypal->cvv = '123';
        $paypal->firstName = 'Joe';
        $paypal->lastName = 'Customer';

        if ($paypal->process($user_products, $transaction_shipping)) {

            $id = $paypal->paymentId;
            //Echo the paypal transaction id
            echo $id;
            exit;

        } else {
            $error = $paypal->errors;
            //display error
            $msg = $error;

        }
    }
}

?>

<!-- Begin the page content. -->
<div id="page_title">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1><?php echo $page_title; ?></h1>                
            </div>
        </div>
    </div>
</div>

<div class="content_block">
    <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Paypal Payment Form -->
                    <p>Pay with your credit card via PayPal Website Payments Pro.</p>
                    <form method="post" id="paypal_payment_form" class="form-inline" action="<?php $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="amount_total" value="<?php echo $transaction_total; ?>" />
                        <input type="hidden" name="amount_shipping" value="<?php echo $transaction_shipping; ?>" />
                    <div class="form-group">
                        <label for="user_fname">First Name</label>
                        <input class="form-control" type="text" name="user_fname" id="user_fname" value="<?php echo ucwords($_POST['user_fname']); ?>" />
                    </div>
                    <div class="form-group">
                        <label for="user_lname">Last Name</label>
                        <input class="form-control" type="text" name="user_lname" id="user_lname" value="<?php echo ucwords($_POST['user_lname']); ?>" />
                    </div>
                        
                    <div class="form-group">
                        <label for="credit_card_number">Card Number</label>
                        <input class="form-control" type="text" name="credit_card_number" autocomplete="off" maxlength="20" class="input-text wc-credit-card-form-card-number">
                    </div>
                        
                    <div class="form-group">
                        <label for="expire_date">Expiration Date</label>
                        <script>
                            $('#paypal_payment_form').bind('submit', function(){
                                var month = $('[name=exp_month]').val();
                                var year = $('[name=exp_year]').val();
                                $('[name=expire_date]').val(month+year);
                            });
                        </script>
                        <input type="hidden" name="expire_date"/>
                        <select class="form-control" style="width: 30%" type="text" name="exp_month" id="exp_month">
                            <option disabled selected>- Month -</option>
                            <option value="01">01 - January</option>
                            <option value="02">02 - February</option>
                            <option value="03">03 - March</option>
                            <option value="04">04 - April</option>
                            <option value="05">05 - May</option>
                            <option value="06">06 - June</option>
                            <option value="07">07 - July</option>
                            <option value="08">08 - August</option>
                            <option value="09">09 - September</option>
                            <option value="10">10 - October</option>
                            <option value="11">11 - November</option>
                            <option value="12">12 - December</option>
                        </select>
                        
                        <select class="form-control" style="width: 30%" type="text" name="exp_year" id="exp_year">
                            <option disabled selected>- Year -</option>
                            <option value="16">2016</option>
                            <option value="17">2017</option>
                            <option value="18">2018</option>
                            <option value="19">2019</option>
                            <option value="20">2020</option>
                            <option value="21">2021</option>
                            <option value="22">2022</option>
                            <option value="23">2023</option>
                            <option value="24">2024</option>
                            <option value="25">2025</option>
                        </select>
                    </div> 
                        
                    <div class="form-group">
                        <label for="cvv2_code">Card Code <span class="required">*</span></label>
                        <input type="text" class="form-control" name="cvv2_code" placeholder="CVC" autocomplete="off" class="input-text wc-credit-card-form-card-cvc">
                    </div>    

                    <div class="form-group text-right">
                        <input class="btn btn-primary" type="submit" name="payment_paypal_submit" value="Complete Order" />
                    </div>
                </form>

            </div>
            <div class="col-lg-4">
                <aside id="checkout-sidebar">
                    <h4>Is My Data Safe?</h4>
                    <p>Absolutely! Poly Tech America uses SSL certification to encrypt all sensitive information before it's submitted and processed. All of your personal information is kept strictly confidential and is not sold to third-parties.</p>
                    <p><img src="<?php echo $domain; ?>/assets/img/sslbasic.png" alt="secure checout" ></p>                    
                    <h4>Payment Methods</h4>
                    <p>
                        <img src="<?php echo $domain; ?>/assets/img/ico-visa.png" class="method" alt="Visa">
                        <img src="<?php echo $domain; ?>/assets/img/ico-discover.png" class="method" alt="Discover">
                        <img src="<?php echo $domain; ?>/assets/img/ico-mastercard.png" class="method" alt="Mastercard">
                    </p>
                </aside>
            </div>
        </div>
    </div>
</div>
