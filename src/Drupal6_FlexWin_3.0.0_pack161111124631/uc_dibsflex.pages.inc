<?php
// $Id: uc_dibsflex.pages.inc,dibs

/**
 * @file
 * dibs menu items.
 *
 */

function uc_dibsflex_success($cart_id = 0) {
    $fields = array();
    
    if (isset($_POST['orderid'])) {
        watchdog('dibs', 'Successful transaction. Order: !order_id.', 
                 array('!order_id' => check_plain($_POST['orderid'])));
        $order = uc_order_load($_POST['orderid']);
    }
    
    $mErr = getDibsFlexObj()->dibsflex_api_checkMainFields($order, TRUE);
    if($mErr === FALSE) {
        $fields['successaction'] = '1';
        db_query("UPDATE `uc_dibs_orderdata` 
                  SET `successaction` = 1 
                  WHERE `orderid`='".$_POST['orderid']."';");

        // Empty the cart
        uc_cart_empty($cart_id);

        // Save changes to order
        uc_order_save($order);

        // Add a comment to sales team
        uc_order_comment_save($order->order_id, 0, 
                              t('Order created through website.'), 
                              'admin');

        $output = uc_cart_complete_sale($order, 
                                        variable_get('uc_new_customer_login', FALSE));

        $page = variable_get('uc_cart_checkout_complete_page', '');

        uc_order_update_status($_POST['orderid'], 'processing');
    
        if (!empty($page)) {
            drupal_goto($page);
        }

        return $output;
    }
    else {
        echo getDibsFlexObj()->dibsflex_api_errCodeToMessage($mErr);
        exit();
    }
}

function uc_dibsflex_callback() {
    $fields = array();
    if(isset($_POST['orderid'])) {
        watchdog('dibs', 'Callback performed for order: !order_id.', 
             array('!order_id' => check_plain($_POST['orderid'])));
        $order = uc_order_load($_POST['orderid']);
        getDibsFlexObj()->dibsflex_api_callback($order);
    }
    else exit();
}

function uc_dibsflex_cancel() {
    watchdog('dibs', 'Order canceled: !order_id.', 
             array('!order_id' => check_plain($_POST['orderid'])));
    db_query("UPDATE `uc_dibs_orderdata` 
              SET `ordercancellation` = 1 
              WHERE `orderid`='". db_escape_string($_POST['orderid']) ."';");
    
    uc_order_update_status($_POST['orderid'], 'canceled');
    
    drupal_goto(url('cart', array('absolute' => TRUE)));
}

function uc_dibsflex_redirect() {
    unset($_POST['op'],$_POST['form_build_id'],$_POST['form_token'],$_POST['form_id']);
        
    $sOutput = '';
    foreach($_POST as $key=>$val) {
        $sOutput .= '<input type="hidden" name="'.$key.'" value="'.$val.'" />'."\r\n";
    }
    
    $sPage = '<form id="payment" action="' . getDibsFlexObj()->dibsflex_api_getFormAction() . '" method="POST">
                '.$sOutput.'
              </form>
              <script type="text/javascript">
                setTimeout("document.getElementById(\'payment\').submit();",1000);
              </script>';

    return $sPage;
}
?>