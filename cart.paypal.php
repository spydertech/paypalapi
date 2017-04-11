<?php

    $page_title     = 'Credit Card Payment';
    $page_class     = 'checkout payment_method';
    $page_keywords  = '';
    $page_desc      = '';

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

            echo $id;
            exit;

            //Get Shipping and Total from transactions
            if ($selecttransaction = $db->prepare("SELECT t.transaction_amount, t.transaction_discount, t.transaction_shipping, t.transaction_date, t.transaction_notes FROM transactions AS t WHERE t.transaction_id=?")) {
                $selecttransaction->bind_param('s', $transaction_id);
                $selecttransaction->execute();
                $selecttransaction->bind_result($transaction_amount, $transaction_discount, $transaction_shipping, $transaction_date, $transaction_notes);
                $selecttransaction -> fetch();
                $selecttransaction->close();
            }

            // Send email to the customer.
            $to = $_SESSION['user_email'];
            $subject = "Poly-Tech New Customer Order";
            $email = new PHPMailer();
            $email->From = 'order@polytechamerica.com';
            $email->FromName = 'Poly Tech America';
            $email->Subject = $subject;
            $email->AddAddress($to);


            $message = '<style>
        #emailcontainer{
            width: 100%; 
            height: 100%; 
            border: 0;
            background: url(http://lukefilewalker.xyz/polytech/assets/img/wet_snow.png);
        }
        
        #emailbody{
            margin: auto; 
            width: 600px;
            background-color: #fff;
            border: 1px solid #ccc;
            font-family: Open Sans, Segoe UI, Arial, Helvetica; 
            line-height: 1.75; 
        }
        
        #emailbody2{
            margin: auto; 
            width: 600px;
            background-color: #fff;
            font-family: Open Sans, Segoe UI, Arial, Helvetica; 
            line-height: 1.75; 
        }
    
        .emailtitle{
            padding: 10px 40px; 
            background: #557da1;
            text-align: center;
        }
    
        .emailtitle h1{
            color: #fff; 
            font-size: 30px; 
            margin:0; 
            padding:10px 0; 
            text-transform: uppercase;
        }
    
        .producttable{
            margin: auto; 
            width: 600px; 
            border: 1px solid #ccc;
            font-family: Open Sans, Segoe UI, Arial, Helvetica; 
            line-height: 1.75;
        }
    
        .shippingtable{
            margin: auto; 
            width: 600px; 
            font-family: Open Sans, Segoe UI, Arial, Helvetica; 
            line-height: 1.75;
            padding: 2%;
        }
    
        .producttable td{
            border: 1px solid #ccc;
        }
    
        a {color: #c51b1e;}
    
        </style>
    
        <table id="emailcontainer">      
        <tr><td style="height: 60px;">&nbsp;</td></tr>
        <tr>
        <td>
        <table cellpadding="0" cellspacing="0" id="emailbody">
        <br/>
        <tr>
        <td>
            <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
            <td>
               <p align="center" style="padding: 3% 0 0 0"><img id="logo" src="http://lukefilewalker.xyz/polytech/assets/img/logo.png"><br/>
               128 Blacks Road Cheshire, CT 06410<br/>
               <a style="color: #000000;" href="tel:+2032500900">(203) 250-0900</a></p>
            </td>
            </tr>
            </table>
        </td>        
        </tr>  
        <tr>
        <td style="height: 20px;">&nbsp;</td>
        </tr>
        <tr>
        <td class="emailtitle">
        <h1>PURCHASE ORDER QUOTE</h1>
        </td>
        </tr>
        <tr>
        <td style="padding: 30px 40px 40px; color: #666 !important; font-size: 16px;">'. $user_fname .' '. $user_lname .',<br/>
        <p>Thank you for generating a quote with us. Your quote items are below. Please see attached files for your required documents.</p>
        <p>Once you receive your approved Purchase Order, please visit this link to <a href="http://lukefilewalker.xyz/polytech/?page=cart&mode=ponumber&transaction_id='. $transaction_id .'&token='. $order_number .'">place your order</a>.</p>
        <p><strong>Order# '. $order_number .'</strong></p>
        </td>
        </tr>
        <tr>   
        <td>
    
        <table cellpadding="0" cellspacing="0" class="producttable">
        <tr>
        <th>Image</th><th>Product Name</th><th>Part #</th><th>Quantity</th><th>Price</th>
        </tr>';

            //Select Product Info for Email
            if ($select = $db->prepare("SELECT po.product_name, po.data_name, po.type_name, po.product_sku, po.data_sku, po.product_price, po.data_price, po.ordered_quantity, pom.data_id, pom.data_name, pom.data_sku, pom.data_price, pom.data_quantity, t.transaction_amount, t.transaction_shipping, p.product_photo FROM products_ordered AS po INNER JOIN products_ordered_meta AS pom ON po.ordered_id=pom.ordered_id INNER JOIN transactions AS t ON t.transaction_id=po.transaction_id INNER JOIN products AS p ON po.product_id=p.product_id WHERE po.transaction_id=?")) {
                $select->bind_param('s', $transaction_id);
                $select->execute();
                $select->bind_result($product_name, $data_name, $type_name, $product_sku, $data_sku, $product_price, $data_price, $ordered_quantity, $meta_data_id, $meta_data_name, $meta_data_sku, $meta_data_price, $meta_data_quantity, $transaction_amount, $transaction_shipping, $product_photo);
                while ($select->fetch()) {

                    $message.= '<tr>
                            <td><img src="http://lukefilewalker.xyz/polytech/assets/uploads/product_photos/'. $product_photo .'" width="100" /></td>
                            <td>'. $product_name .''. ((!empty($data_name)) ? '<br/>
                            ('.$type_name.')<br/>
                            <sub>'.$data_name.'</sub>':"").'</td>
                            <td>'.$product_sku.''.$data_sku.'</td>
                            <td style="text-align:center;">'. $ordered_quantity .'</td>
                            <td style="text-align:center;">$'. (($product_price + $data_price) * $ordered_quantity) .'</td>
                        </tr>
    
                        <tr>
                            <td>&nbsp;</td>
                            <td>'. $meta_data_name .'</td>';
                    if ($meta_data_id == '0'){
                        $message.= '<td>&nbsp;</td>';
                    }else{
                        $message.= '<td>'. $product_sku.''.$data_sku.''.$meta_data_sku .'</td>';
                    }
                    $message.=  '<td style="text-align:center;">'. $meta_ordered_quantity .'</td>
                            <td style="text-align:center;">$'.($meta_data_price * $ordered_quantity).'</td>
                            </tr>';
                }
                $select->close();
            }

            $message.= '</table>
        <table cellpadding="0" cellspacing="0" class="shippingtable">
        <tr>
        <td width="80%" align="right"><strong>Subtotal:</strong> </td>
        <td width="20%" align="right"> <strong>$'. money_format('%(#2n',($transaction_amount-$transaction_shipping)) .'</strong></td>
        </tr>
        <tr>
        <td width="80%" align="right"><strong>Shipping:</strong> </td>
        <td width="20%" align="right"> <strong>$'. money_format('%(#2n',$transaction_shipping) .'</strong></td>
        </tr>
        <tr>
        <td width="80%" align="right"><strong>Total:</strong> </td>
        <td width="20%" align="right"> <strong>$'. money_format('%(#2n',$transaction_amount) .'</strong></td>
        </tr>
        </table>
    
        <br/>
    
        <table cellpadding="0" cellspacing="0" id="emailbody2">
        <tr>
        <td style="padding: 30px 40px 40px; color: #666 !important; font-size: 16px;">
        <p style="color: #666;"><strong>Billing Address</strong><br/>
        '. $user_company .'<br/> 
        '. $user_fname .' '. $user_lname .'<br/> 
        '. $user_email .'<br/> 
        '. $user_phone .' (cell)<br/>
        '. $user_phone2 .' (landline)<br/> 
        '. $user_street .'<br/> 
        '. $user_city .', '. $user_state .' '. $user_zip .'
        </p>
        </td>
        <td style="padding: 30px 40px 40px; color: #666 !important; font-size: 16px;">
        <p style="color: #666;"><strong>Shipping Address</strong><br/>
        '. $user_shipping_company .'<br/> 
        '. $user_shipping_fname .' '. $user_shipping_lname .'<br/> 
        '. $user_shipping_email .'<br/> 
        '. $user_shipping_phone .' (cell)<br/>
        '. $user_shipping_phone2 .'(landline)<br/> 
        '. $user_shipping_street .'<br/> 
        '. $user_shipping_city .', '. $user_shipping_state .' '. $user_shipping_zip .'
        </p>
        </td>
        </tr>
        </table>
    
        <table cellpadding="0" cellspacing="0" id="emailbody2">
        <tr>
        <td style="padding: 30px 40px 40px; color: #666 !important; font-size: 16px;">
        <p>Thank you,<br />
        <strong>Poly Tech America</strong><br />
        128 Blacks Road Cheshire, CT 06410<br/>
        <a style="color: #000000;" href="tel:+2032500900">(203) 250-0900</a></p>
        </td>
        </tr>
        </table>        
            </tr>
            </table>
        </td>
        </tr>
        </table>';

            $email->Body = $message;
            $email->IsHTML(true);
            $resp = $email->Send();
            $msg = 'Order completed!';
            setcookie('cart', null, time() - 1000, '/');
            //header('Location: ./?page=cart&mode=quote-thanks');
            //die();

        } else {
            $error = $paypal->errors;
            //display error
            $msg = $error;

        }
    }
}

// Include the template files.
include_once ('./assets/template/website/main.php');
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