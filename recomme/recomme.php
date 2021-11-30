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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2021 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
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

        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'recomme_rcrs (
                        id INT NOT NULL AUTO_INCREMENT,
                        id_order INT UNSIGNED NOT NULL,
                        rcr VARCHAR(255) NOT NULL,
                        PRIMARY KEY (id)
                        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
        );

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('actionOrderStatusPostUpdate');


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
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
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
        $states = new OrderState();
        $statesOptions = $states->getOrderStates($this->context->language->id);

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
                        'type' => 'select',
                        'lang' => true,
                        'label' => $this->l('Select paid status'),
                        'name' => 'RECOMME_SUCCESS_STATUS',
                        'desc' => $this->l('Please select status that means the order has been paid'),
                        'options' => array(
                            'query' => $statesOptions,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),

                    array(
                        'type' => 'select',
                        'lang' => true,
                        'label' => $this->l('Select second paid status'),
                        'name' => 'RECOMME_SUCCESS_STATUS_2',
                        'desc' => $this->l('Please select status that means the order has been paid'),
                        'options' => array(
                            'query' => $statesOptions,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'lang' => true,
                        'label' => $this->l('Select third paid status'),
                        'name' => 'RECOMME_SUCCESS_STATUS_3',
                        'desc' => $this->l('Please select status that means the order has been paid'),
                        'options' => array(
                            'query' => $statesOptions,
                            'id' => 'id_order_state',
                            'name' => 'name'
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
            'RECOMME_SUCCESS_STATUS_2' => Configuration::get('RECOMME_SUCCESS_STATUS_2', null),
            'RECOMME_SUCCESS_STATUS_3' => Configuration::get('RECOMME_SUCCESS_STATUS_3', null),
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
        if (isset($_COOKIE['recomme_r_code']) && $_COOKIE['recomme_r_code'] !== null) {
            $rcr = $_COOKIE['recomme_r_code'];
            $this->saveCookie($rcr);
        }
        if (isset($_GET['rcr']) && $_GET['rcr'] !== null) {
            $rcr = $_GET['rcr'];
            $this->saveCookie($rcr);
        }
    }

    private function saveCookie($rcr)
    {
        $days_to_keep_cookies = 28;
        setcookie('recomme_r_code', $rcr, time() + (86400 * $days_to_keep_cookies));
        Context::getContext()->cookie->__set('recomme_r_code', $rcr);
        Context::getContext()->cookie->write();
    }

    public function buildOrder($orderId, $rcr = null)
    {
        $order = new Order((int)$orderId);

        if (is_object($order)) {
            $customer = new Customer($order->id_customer);
            $address = new Address((int)$order->id_address_delivery);
            $currency = new Currency((int)$order->id_currency);
            $country = new Country($address->id_country);
            $orderData = [
                'api_key' => Configuration::get('RECOMME_API_KEY'),
                'first_name' => $customer->firstname,
                'last_name' => $customer->lastname,
                'email' => $customer->email,
                'order_timestamp' => strtotime($order->date_add),
                'browser_ip' => !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : "",
                'user_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "",
                'invoice_amount' => $order->total_paid_tax_incl,
                'currency_code' => $currency->iso_code,
                'country' => $country->iso_code,
                'external_reference_id' => $orderId,
                // 'coupons'                => $this->getOrderCoupons($orderId),
                'ref_code' => $rcr,
                'timestamp' => time(),
            ];
            return $orderData;
        }
        return false;
    }

    private function sendOrder($order)
    {
        $bearerToken = "Authorization: Bearer " . Configuration::get('RECOMME_API_KEY');
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

        if ($response = curl_exec($curl)) {
            $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($http_status == 200)
                Context::getContext()->cookie->__unset('recomme_r_code');
            setcookie("recomme_r_code", "", time() - 3600);
            unset ($_COOKIE['recomme_r_code']);
        }

        curl_close($curl);
    }

    public function getOrderCoupons($id_order)
    {
        $coupons = [];
        $sql = 'SELECT code FROM ' . _DB_PREFIX_ . 'order_cart_rule ocr INNER JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.id_cart_rule = ocr.id_cart_rule WHERE ocr.id_order=' . (int)$id_order;
        $rows = Db::getInstance()->executeS($sql);

        if (count($rows) > 0) {
            foreach ($rows as $code) {
                $coupons[] = $code['code'];
            }
        }

        return $coupons;
    }

    public function insertRcr($id_order, $rcr)
    {
        $insert_sql = 'INSERT INTO ' . _DB_PREFIX_ . 'recomme_rcrs(
                            id_order,
                            rcr
                        ) VALUES (' .
            (int)$id_order . ', ' .
            '\'' . pSQL($rcr) . '\'' .
            ')';
        return Db::getInstance()->execute($insert_sql);
    }

    public function getRcr($id_order)
    {
        $sql = 'SELECT rcr, id_order FROM ' . _DB_PREFIX_ . 'recomme_rcrs WHERE id_order=' . (int)$id_order;
        $rcr = Db::getInstance()->getRow($sql);
        return $rcr['rcr'];
    }

    public function hookDisplayOrderConfirmation()
    {
        $id_order = Tools::getValue('id_order');

        if (Context::getContext()->cookie->__isset('recomme_r_code')) {
            $this->insertRcr($id_order, Context::getContext()->cookie->__get('recomme_r_code'));
        }

        $order = $this->buildOrder($id_order);
        $this->sendOrder($order);
    }


    public function hookActionOrderStatusPostUpdate($params)
    {
        $id_order_state = !empty($params['newOrderStatus']) ? $params['newOrderStatus']->id : false;
        $id_order = @$params['id_order'];
        $rcr = null;

        if (
            ($id_order_state == Configuration::get('RECOMME_SUCCESS_STATUS')) ||
            ($id_order_state == Configuration::get('RECOMME_SUCCESS_STATUS_2')) ||
            ($id_order_state == Configuration::get('RECOMME_SUCCESS_STATUS_3'))
        ) {
            $rcr = $this->getRcr($id_order);
        }

        $order = $this->buildOrder($id_order, $rcr);

        $this->sendOrder($order);
    }
}
