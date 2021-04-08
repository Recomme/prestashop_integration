<?php
/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Recomme extends Module
{
    public $base_api_url = 'https://api.recomme.io';

    protected $config_form = true;

    public function __construct()
    {
        $this->name = 'recomme';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Recomme';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Recomme');
        $this->description = $this->l('Here is a module for build referral program');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Recomme?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('RECOMME_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayOrderConfirmation');
    }

    public function uninstall()
    {
        Configuration::deleteByName('RECOMME_LIVE_MODE');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitRecommeModule')) == true) {
            $this->postProcess();
        }

        $output = $this->renderForm();
        return $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRecommeModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'RECOMME_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),           
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'desc' => $this->l('Enter a valid account key'),
                        'name' => 'RECOMME_ACCOUNT_KEY',
                        'label' => $this->l('Account'),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'desc' => $this->l('Enter a valid api key'),
                        'name' => 'RECOMME_API_KEY',
                        'label' => $this->l('API Key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'RECOMME_LIVE_MODE' => Configuration::get('RECOMME_LIVE_MODE', true),
            'RECOMME_SUCCESS_STATUS' => Configuration::get('RECOMME_SUCCESS_STATUS', null),
            'RECOMME_ACCOUNT_KEY' => Configuration::get('RECOMME_ACCOUNT_KEY', null),
            'RECOMME_API_KEY' => Configuration::get('RECOMME_API_KEY', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
        }
    }

    public function hookHeader($params)
    {
        if (isset($_GET['rcr']) && $_GET['rcr'] !== null) {
            $rcr = $_GET['rcr'];
            $days_to_keep_cookies = 28;
            setcookie('recomme_r_code', $rcr, time() + (86400 * $days_to_keep_cookies));
            Context::getContext()->cookie->__set('recomme_r_code', $rcr);
            Context::getContext()->cookie->write();
        }
    }

    public function buildOrder($orderId)
    {
        $order = new Order((int) $orderId);

        if (is_object($order)) {
            $customer    = new Customer($order->id_customer);
            $address     = new Address((int) $order->id_address_delivery);
            $currency    = new Currency((int) $order->id_currency);
            $country     = new Country($address->id_country);
            $tax = round($order->total_paid_tax_incl - $order->total_paid_tax_excl, 2);
            $price = round($order->total_products - $order->total_discounts, 2);
            $discount = round($order->total_discounts, 2);
            $total = round($tax + $order->total_shipping + $order->total_products, 2) - round($order->total_discounts, 2);
            $revenue = round(($order->total_products + $order->total_shipping ) - $order->total_discounts, 2);

            $orderData = [
                'api_key'               => Configuration::get('RECOMME_API_KEY'),
                'first_name'            => $customer->firstname,
                'last_name'             => $customer->lastname,
                'email'                 => $customer->email,
                'order_timestamp'       => strtotime($order->date_add),
                'browser_ip'            => !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : "",
                'user_agent'            => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "",
                'invoice_amount'        => $total,
                'currency_code'         => $currency->iso_code,
                'country'               => $country->iso_code,
                'external_reference_id' => $orderId,
                'ref_code'              => Context::getContext()->cookie->__isset('recomme_r_code') ? Context::getContext()->cookie->__get('recomme_r_code') : "",
                'timestamp'             => time(),
            ];
            return $orderData;
        }
        return false;
    }

    private function sendOrder($order) 
    {
        $bearerToken = "Bearer: " . Configuration::get('RECOMME_API_KEY');
        $customer = "Customer: " . Configuration::get('RECOMME_ACCOUNT_KEY');
        $endpoint = join('/', [$this->base_api_url, 'purchase']);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', $bearerToken, $customer],
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $endpoint,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => Tools::jsonEncode($order),
        ));
        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        // echo "<pre>"; var_dump($http_status, $response); exit;
    }

    public function hookDisplayOrderConfirmation()
    {
        $id_order = Tools::getValue('id_order');       
        $order = $this->buildOrder($id_order);
        
        if($order)
            $this->sendOrder($order);
    }
}
