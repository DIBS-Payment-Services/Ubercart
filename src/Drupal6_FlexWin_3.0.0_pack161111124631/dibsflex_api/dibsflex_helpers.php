<?php
interface dibsflex_helpers_iface {
    function dibsflex_helper_dbquery_write($sQuery);
    function dibsflex_helper_dbquery_read($sQuery);
    function dibsflex_helper_dbquery_read_single($mResult, $sName);
    function dibsflex_helper_cmsurl($sLink);
    function dibsflex_helper_getconfig($sVar, $sPrefix);
    function dibsflex_helper_getdbprefix();
    function dibsflex_helper_getReturnURLs($sURL);
    function dibsflex_helper_getOrderObj($mOrderInfo, $bResponse = FALSE);
    function dibsflex_helper_getAddressObj($mOrderInfo);
    function dibsflex_helper_getShippingObj($mOrderInfo);
    function dibsflex_helper_getItemsObj($mOrderInfo);
    function dibsflex_helper_redirect($sURL);
    function dibsflex_helper_afterCallback($oOrder);
    function dibsflex_helper_getlang($sKey);
    function dibsflex_helper_cgiButtonsClass();
    function dibsflex_helper_modVersion();
}

class dibsflex_helpers extends dibsflex_helpers_cms implements dibsflex_helpers_iface {

    /** START OF DIBS HELPERS AREA **/

    function dibsflex_helper_dbquery_write($sQuery) {
        return db_query($sQuery);
    }
    
    function dibsflex_helper_dbquery_read($sQuery) {
        return db_query($sQuery);
    }
    
    function dibsflex_helper_dbquery_read_single($mResult, $sName) {
        $mRes = db_result($mResult);
        if($mRes !== FALSE) return $mRes;
        else return null;
    }
    
    function dibsflex_helper_cmsurl($sLink) {
        return url($sLink, array('absolute' => TRUE));
    }
    
    function dibsflex_helper_getconfig($sVar, $sPrefix = 'uc_dibsflex_') {
        return variable_get($sPrefix . $sVar, '');
    }
    
    function dibsflex_helper_getdbprefix() {
        return "uc_";
    }
    
    function dibsflex_helper_getReturnURLs($sURL) {
        switch ($sURL) {
            case 'success':
                return $this->dibsflex_helper_cmsurl('cart/dibsflex/success/');
            break;
            case 'callback':
                return $this->dibsflex_helper_cmsurl('cart/dibsflex/callback/');
            break;
            case 'callbackfix':
                return $this->dibsflex_helper_cmsurl('cart/dibsflex/callback/');
            break;
            case 'cancel':
                return $this->dibsflex_helper_cmsurl('cart/dibsflex/cancel/');
            break;
            case 'cgi':
                return $this->dibsflex_helper_cmsurl('cart/dibsflex/cgiapi/');
            break;
            default:
                return $this->dibsflex_helper_cmsurl('cart');
            break;
        }
    }
    
    function dibsflex_helper_getOrderObj($mOrderInfo, $bResponse = FALSE) {
        return (object)array(
                    'order_id'  => $mOrderInfo->order_id,
                    'total'     => round($mOrderInfo->order_total, 2)*100,
                    'currency'  => $this->dibsflex_helper_getconfig("curr")
               );
    }
    
    function dibsflex_helper_getAddressObj($mOrderInfo) {
        $country_b = uc_get_country_data(array('country_id' => $mOrderInfo->billing_country));
        if ($country_b === FALSE) {
            $country_b = array(0 => array('country_iso_code_3' => 'USA'));
        }
 
        $country_d = uc_get_country_data(array('country_id'=> $mOrderInfo->delivery_country));
        if ($country_d === FALSE) {
            $country_d = array(0 => array('country_iso_code_3' => 'USA'));
        }
    
        return (object)array(
                'billing'   => (object)array(
                    'firstname' => $mOrderInfo->billing_first_name,
                    'lastname'  => $mOrderInfo->billing_last_name,
                    'street'    => $mOrderInfo->billing_street1 . " ". 
                                   $mOrderInfo->billing_street2,
                    'postcode'  => $mOrderInfo->billing_postal_code,
                    'city'      => $mOrderInfo->billing_city,
                    'region'    => uc_get_zone_code($mOrderInfo->billing_zone),
                    'country'   => $country_b[0]['country_iso_code_3'],
                    'phone'     => $mOrderInfo->billing_phone,
                    'email'     => $mOrderInfo->primary_email
                ),
                'delivery'  => (object)array(
                    'firstname' => $mOrderInfo->delivery_first_name,
                    'lastname'  => $mOrderInfo->delivery_last_name,
                    'street'    => $mOrderInfo->delivery_street1 . " " . 
                                   $mOrderInfo->delivery_street2,
                    'postcode'  => $mOrderInfo->delivery_postal_code,
                    'city'      => $mOrderInfo->delivery_city,
                    'region'    => uc_get_zone_code($mOrderInfo->delivery_zone),
                    'country'   => $country_d[0]['country_iso_code_3'],
                    'phone'     => $mOrderInfo->delivery_phone,
                    'email'     => $mOrderInfo->primary_email
                )
            );
    }

    function dibsflex_helper_getShippingObj($mOrderInfo) {
        $iShippingTax = $this->uc_dibsflex_shippingtax($mOrderInfo);
        return (object)array(
                'method' => $mOrderInfo->quote['method'],
                'rate'   => round($mOrderInfo->quote['rate'], 2) * 100,
                'tax'    => $iShippingTax
            );
    }

    function dibsflex_helper_getItemsObj($mOrderInfo) {
        foreach($mOrderInfo->products as $oItem) {
            if(isset($oItem->tax)) $sTmpTax = $this->uc_dibsflex_getitemtax($oItem->tax)/100;
            else $sTmpTax = 0;
            $oItems[] = (object)array(
                'item_id'   => $oItem->nid,
                'name'      => $oItem->title,
                'sku'       => $oItem->model,
                'price'     => round($oItem->price, 2) * 100,
                'qty'       => $oItem->qty,
                'tax_name'  => '',
                'tax_rate'  => round($sTmpTax, 2) * 100
            );
        }
        return $oItems;
    }

    function dibsflex_helper_redirect($sURL) {
        drupal_goto(url($sURL, array('absolute' => FALSE)));
    }

    function dibsflex_helper_afterCallback($oOrder) {
        return true;
    }
    
    function dibsflex_helper_getlang($sKey) {
        $aLang = $this->uc_dibsflex_getTextArray();
        return $aLang[$sKey];
    }
    
    function dibsflex_helper_cgiButtonsClass() {
        return "";
    }
    
    function dibsflex_helper_modVersion() {
        return 'drup6_u3.0.0';
    }
    /** END OF DIBS HELPERS AREA **/
}
?>