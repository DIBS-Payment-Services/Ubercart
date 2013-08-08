<?php
// $Id: uc_dibspw.pages.inc, dibs

/**
 * @file
 * dibspw menu items.
 *
 */

function uc_dibspw_success($cart_id = 0) {
    if (isset($_POST['orderid'])) {
        watchdog('dibspw', 'Successful transaction. Order: !order_id.', 
                array('!order_id' => check_plain($_POST['orderid'])));
        $order = uc_order_load($_POST['orderid']);
    
        getDibspwObj()->api_dibs_action_success($order);
        // Empty the cart
        uc_cart_empty($cart_id);
        // Save changes to order
        uc_order_save($order);
        // Add a comment to sales team
        uc_order_comment_save($order->order_id, 0, t('Order created through website.'), 'admin');
        uc_order_comment_save($order->order_id, 0, t('Payment completed. Order processing.'), 'admin');
        $output = uc_cart_complete_sale($order, variable_get('uc_new_customer_login', FALSE));
        $page = variable_get('uc_cart_checkout_complete_page', '');
        uc_order_update_status($_POST['orderid'], 'processing');
        if (!empty($page)) drupal_goto($page);
    
        return $output;
    }
    else exit(getDibspwObj()->api_dibs_errCodeToMessage(11));
}

function uc_dibspw_callback() {
    if(isset($_POST['orderid'])) {
        watchdog('dibspw', 'Callback performed for order: !order_id.', 
                array('!order_id' => check_plain($_POST['orderid'])));
        $order = uc_order_load($_POST['orderid']);
        getDibspwObj()->api_dibs_action_callback($order);
    }
    else exit((string)11);
}

function uc_dibspw_cancel() {
    watchdog('dibspw', 'Order canceled: !order_id.', 
            array('!order_id' => check_plain($_POST['orderid'])));
    getDibspwObj()->api_dibs_action_cancel();
    uc_order_update_status($_POST['orderid'], 'canceled');
    drupal_goto(url('cart', array('absolute' => TRUE)));
}

function uc_dibspw_redirect() {
    unset($_POST['op'],$_POST['form_build_id'],$_POST['form_token'],$_POST['form_id']);
        
    $sOutput = '';
    foreach($_POST as $sKey => $sVal) {
        $sOutput .= '<input type="hidden" name="' . $sKey . '" value="' . $sVal . '" />'."\r\n";
    }
    
    $sPage = '<form id="payment" accept-charset="UTF-8"  action="' . getDibspwObj()->api_dibs_get_formAction() . '" method="POST">
                '.$sOutput.'
              </form>
              <script type="text/javascript">
                setTimeout("document.getElementById(\'payment\').submit();",1000);
              </script>';

    return $sPage;
}
?>