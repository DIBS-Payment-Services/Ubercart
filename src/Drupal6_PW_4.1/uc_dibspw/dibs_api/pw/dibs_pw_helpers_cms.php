<?php
class dibs_pw_helpers_cms {   
    
    function cms_dibs_get_textArray() {
        return array(
            'txt_err_fatal'  => t('A fatal error has occured.'), 
            'txt_msg_toshop' => t('Return to shop'), 
            'txt_err_11'     => t('Unknown orderid was returned from DIBS payment gateway!'), 
            'txt_err_12'     => t('No orderid was returned from DIBS payment gateway!'), 
            'txt_err_21'     => t('The amount received from DIBS payment gateway 
                                   differs from original order amount!'), 
            'txt_err_22'     => t('No amount was returned from DIBS payment gateway!'), 
            'txt_err_31'     => t('The currency type received from DIBS payment gateway 
                                   differs from original order currency type!'),
            'txt_err_32'     => t('No currency type was returned from DIBS payment 
                                   gateway!'), 
            'txt_err_41'     => t('The fingerprint key does not match!'), 
            'txt_err_def'    => t('Unknown error appeared. Please contact to shop 
                               administration to check transaction.')
        );
    }
    
    /**
    * Calculates tax for each product
    * and FlexWin params
    * 
    * @param array $aTaxes
    * @return int 
    */
    function cms_dibs_get_tax_item($aTaxes) {
        $iSumm = 0;
        foreach($aTaxes as $oTax) {
            $iSumm += $oTax->taxed;
        }
        return $iSumm;
    }

    /**
    * Calculates shipping tax
    * 
    * @param object $oOrder
    * @return int 
    */
    function cms_dibs_get_tax_shipping($oOrder) {
        $iSumm = 0;
        $aLines = $oOrder->line_items;
        foreach($aLines as $aLine) {
            if($aLine['type'] == 'shipping' && $aLine['amount'] == $oOrder->quote['rate']) {
                if(isset($aLine['tax'])) {
                    $iSumm = $aLine['tax']->taxed;
                    break;
                }
                else $iSumm = 0;
            }
        }
        return $iSumm;
    }

/**
 * Hack: separate tax for each product in $order object
 * HACK START
 */
function cms_dibs_recalculate_tax($order){
    global $user;
    
    if (is_numeric($order)) {
        $order = uc_order_load($order);
        $account = user_load(array('uid' => $order->uid));
    }
    elseif ((int)$order->uid) {
        $account = user_load(array('uid' => intval($order->uid)));
    }
    else {
        $account = $user;
    }
  
    if (!is_object($order)) {
        return array();
    }
  
    if (empty($order->delivery_postal_code)) {
        $order->delivery_postal_code = $order->billing_postal_code;
    }
    
    if (empty($order->delivery_zone)) {
        $order->delivery_zone = $order->billing_zone;
    }
 
    if (empty($order->delivery_country)) {
        $order->delivery_country = $order->billing_country;
    }

    $order->taxes = array();

    if (isset($order->order_status)) {
        $state = uc_order_status_data($order->order_status, 'state');
        $use_same_rates = in_array($state, array('payment_received', 'completed'));
    }
    else {
        $use_same_rates = FALSE;
    }

    $arguments = array(
        'order' => array(
            '#entity' => 'uc_order',
            '#title' => t('Order'),
            '#data' => $order,
        ),
        'tax' => array(
            '#entity' => 'tax',
            '#title' => t('Tax rule'),
            // #data => each $tax in the following foreach() loop;
        ),
        'account' => array(
            '#entity' => 'user',
            '#title' => t('User'),
            '#data' => $account,
        ),
    );

    $predicates = ca_load_trigger_predicates('calculate_taxes');
    foreach (uc_taxes_rate_load() as $tax) {
        if ($use_same_rates) {
            foreach ((array)$order->line_items as $old_line) {
                if ($old_line['type'] == 'tax' && $old_line['data']['tax_id'] == $tax->id) {
                    $tax->rate = $old_line['data']['tax_rate'];
                    break;
                }
            }
        }

        $arguments['tax']['#data'] = $tax;
        if (ca_evaluate_conditions($predicates['uc_taxes_'. $tax->id], $arguments)) {
            $line_item = $this->cms_dibs_action_apply_tax($order, $tax);
            if ($line_item) {
                $order->taxes[$line_item->id] = $line_item;
            }
        }
    }

    return $order->taxes;
}

function cms_dibs_action_apply_tax($order, $tax) {
    $amount = 0;
    $taxable_amount = 0;
    
    if (is_array($order->products)) {
        foreach ($order->products as $key => $item) {
            $iAmountBuffer = uc_taxes_apply_item_tax($item, $tax);
            $taxable_amount += $iAmountBuffer;
            if($iAmountBuffer !== null && $iAmountBuffer !== FALSE) {
                $oTmpData = (object)array(
                    'name' => $tax->name,
                    'taxed' => $tax->rate
                );
            }
            $order->products[$key]->tax[$tax->id] = $oTmpData;
            unset($iAmountBuffer, $oTmpData);
        }
    }
    
    $taxed_line_items = $tax->taxed_line_items;
    
    if (is_array($order->line_items) && is_array($taxed_line_items)) {
        foreach ($order->line_items as $key => $line_item) {
            if ($line_item['type'] == 'tax') {
                // Don't tax old taxes.
                continue;
            }
            if (in_array($line_item['type'], $taxed_line_items)) {
                $oTmpData = (object)array(
                    'name' => $tax->name,
                    'taxed' => $tax->rate
                );
                $order->line_items[$key]['tax'] = $oTmpData;
                $taxable_amount += $line_item['amount'];
                unset($oTmpData);
            }
        }
    }
    
    if (isset($taxed_line_items['tax'])) {
        // Tax taxes that were just calculated.
        foreach ($order->taxes as $other_tax) {
            $taxable_amount += $other_tax->amount;
        }
    }
    
    $amount = $taxable_amount * $tax->rate;
    if ($amount) {
        $line_item = (object)array(
            'id' => $tax->id,
            'name' => $tax->name,
            'amount' => $amount,
            'weight' => $tax->weight,
            'summed' => 1,
        );
        
        $line_item->data = array(
            'tax_id' => $tax->id,
            'tax_rate' => $tax->rate,
            'taxable_amount' => $taxable_amount,
            'tax_jurisdiction' => $tax->name,
        );
        return $line_item;
    }
}
/**
 * Hack: separate tax for each product in $order object
 * HACK END
 */
}
?>