<?php
class dibs_pw_helpers extends dibs_pw_helpers_cms implements dibs_pw_helpers_interface {

    /**
     * Process write SQL query (insert, update, delete) with build-in CMS ADO engine.
     * 
     * @param string $sQuery 
     */
    function helper_dibs_db_write($sQuery) {
        return db_query($sQuery);
    }
    
    /**
     * Read single value ($sName) from SQL select result.
     * If result with name $sName not found null returned.
     * 
     * @param string $sQuery
     * @param string $sName
     * @return mixed 
     */
    function helper_dibs_db_read_single($sQuery, $sName) {
        $mRes = db_query($sQuery);
        
        $mResult = db_result($mRes);
        if($mResult !== FALSE) return $mResult;
        else return null;
    }
    
    /**
     * Return settings with CMS method.
     * 
     * @param string $sVar
     * @param string $sPrefix
     * @return string 
     */
    function helper_dibs_tools_conf($sVar, $sPrefix = 'uc_dibspw_') {
        return variable_get($sPrefix . $sVar, '');
    }
    
    /**
     * Return CMS DB table prefix.
     * 
     * @return string 
     */
    function helper_dibs_tools_prefix() {
        return "uc_";
    }
    
    /**
     * Returns text by key using CMS engine.
     * 
     * @param type $sKey
     * @return type 
     */
    function helper_dibs_tools_lang($sKey) {
        $aLang = $this->cms_dibs_get_textArray();
        return isset($aLang[$sKey]) ? $aLang[$sKey] : "";
    }

    /**
     * Get full CMS url for page.
     * 
     * @param string $sLink
     * @return string 
     */
    function helper_dibs_tools_url($sLink) {
        return url($sLink, array('absolute' => TRUE));
    }
    
    /**
     * Build CMS order information to API object.
     * 
     * @param mixed $mOrderInfo
     * @param bool $bResponse
     * @return object 
     */
    function helper_dibs_obj_order($mOrderInfo, $bResponse = FALSE) {
        return (object)array(
                    'orderid'  => $mOrderInfo->order_id,
                    'amount'   => $mOrderInfo->order_total,
                    'currency' => $this->helper_dibs_tools_conf('curr')
               );
    }
    
    /**
     * Build CMS each ordered item information to API object.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    function helper_dibs_obj_items($mOrderInfo) {
        $aItems = array();
        foreach($mOrderInfo->products as $mItem) {
            $sTmpTax = isset($mItem->tax) ? $this->cms_dibs_get_tax_item($mItem->tax) : 0;
            
            $aItems[] = (object)array(
                'id'    => $mItem->nid,
                'name'  => $mItem->title,
                'sku'   => $mItem->model,
                'price' => $mItem->price,
                'qty'   => $mItem->qty,
                'tax'   => $sTmpTax * 100
            );
        }
        return $aItems;
    }
    
    /**
     * Build CMS shipping information to API object.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    function helper_dibs_obj_ship($mOrderInfo) {
        $iShippable = 0;
        foreach($mOrderInfo->products as $oItem) {
            $iShippable += isset($oItem->shippable) ? $oItem->shippable : 0;
        }
        
        $iShippingTax = $this->cms_dibs_get_tax_shipping($mOrderInfo);
        return (object)array(
                'rate'   => $mOrderInfo->quote['rate'],
                'tax'    => $iShippingTax * 100
            );
    }
    
    /**
     * Build CMS customer addresses to API object.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    function helper_dibs_obj_addr($mOrderInfo) {
        $country_b = uc_get_country_data(array('country_id' => $mOrderInfo->billing_country));
        if ($country_b === FALSE) {
            $country_b = array(0 => array('country_iso_code_3' => 'USA'));
        }
 
        $country_d = uc_get_country_data(array('country_id'=> $mOrderInfo->delivery_country));
        if ($country_d === FALSE) {
            $country_d = array(0 => array('country_iso_code_3' => 'USA'));
        }
        
        return (object)array(
            'shippingfirstname'  => $mOrderInfo->delivery_first_name,
            'shippinglastname'   => $mOrderInfo->delivery_last_name,
            'shippingpostalcode' => $mOrderInfo->delivery_postal_code,
            'shippingpostalplace'=> $mOrderInfo->delivery_city,
            'shippingaddress2'   => $mOrderInfo->delivery_street1 . " " . 
                                    $mOrderInfo->delivery_street2,
            'shippingaddress'    => $country_d[0]['country_iso_code_3'] . " " . 
                                    uc_get_zone_code($mOrderInfo->delivery_zone),
            
            'billingfirstname'   => $mOrderInfo->billing_first_name,
            'billinglastname'    => $mOrderInfo->billing_last_name,
            'billingpostalcode'  => $mOrderInfo->billing_postal_code,
            'billingpostalplace' => $mOrderInfo->billing_city,
            'billingaddress2'    => $mOrderInfo->billing_street1 . " ". 
                                    $mOrderInfo->billing_street2,
            'billingaddress'     => $country_b[0]['country_iso_code_3'] . " " . 
                                    uc_get_zone_code($mOrderInfo->billing_zone),
            
            'billingmobile'      => $mOrderInfo->billing_phone,
            'billingemail'       => $mOrderInfo->primary_email
        );
    }
    
    /**
     * Returns object with URLs needed for API, 
     * e.g.: callbackurl, acceptreturnurl, etc.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    function helper_dibs_obj_urls($mOrderInfo = null) {
        return (object)array(
            'acceptreturnurl' => "cart/dibspw/success/",
            'callbackurl'     => "cart/dibspw/callback/",
            'cancelreturnurl' => "cart/dibspw/cancel/",
            'carturl'         => "cart/"
        );
    }
    
    /**
     * Returns object with additional information to send with payment.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    function helper_dibs_obj_etc($mOrderInfo) {
        return (object)array(
            'sysmod'      => 'dr6u_4_1_0',
            'callbackfix' => $this->helper_dibs_tools_url("cart/dibspw/callback/")
        );
    }
}
?>