<?php
/*
* 2007-2017 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Cashondelivery extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'ps_cashondelivery';
        $this->tab = 'payments_gateways';
        $this->author = 'PrestaShop';
        $this->version = '1.0.7';
        $this->need_instance = 1;

        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => _PS_VERSION_];
        $this->controllers = ['validation'];
        $this->currencies = false;

        parent::__construct();

        $this->displayName = $this->trans('Cash on delivery (COD)', [], 'Modules.Cashondelivery.Admin');
        $this->description = $this->trans('Accept cash payments on delivery to make it easy for customers to purchase on your store.', [], 'Modules.Cashondelivery.Admin');

        /* For 1.4.3 and less compatibility */
        $updateConfig = ['PS_OS_CHEQUE', 'PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_CANCELED', 'PS_OS_REFUND', 'PS_OS_ERROR', 'PS_OS_OUTOFSTOCK', 'PS_OS_BANKWIRE', 'PS_OS_PAYPAL', 'PS_OS_WS_PAYMENT'];
        if (!Configuration::get('PS_OS_PAYMENT')) {
            foreach ($updateConfig as $u) {
                if (!Configuration::get($u) && defined('_' . $u . '_')) {
                    Configuration::updateValue($u, constant('_' . $u . '_'));
                }
            }
        }
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('displayPaymentReturn')
            || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function hasProductDownload($cart)
    {
        $products = $cart->getProducts();

        if (!empty($products)) {
            foreach ($products as $product) {
                $pd = ProductDownload::getIdFromIdProduct((int) ($product['id_product']));
                if ($pd and Validate::isUnsignedInt($pd)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        // Check if cart has product download
        if ($this->hasProductDownload($params['cart'])) {
            return;
        }

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Pay by Cash on Delivery', [], 'Modules.Cashondelivery.Shop'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation($this->fetch('module:ps_cashondelivery/views/templates/hook/ps_cashondelivery_intro.tpl'));

        return [
            $newOption,
        ];
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();

        if ($state) {
            $this->smarty->assign([
                'shop_name' => $this->context->shop->name,
                'total' => $this->context->getCurrentLocale()->formatPrice($params['order']->getOrdersTotalPaid(), (new Currency($params['order']->id_currency))->iso_code),
                'status' => 'ok',
                'reference' => $params['order']->reference,
                'contact_url' => $this->context->link->getPageLink('contact', true),
            ]);
        } else {
            $this->smarty->assign([
                'status' => 'failed',
                'contact_url' => $this->context->link->getPageLink('contact', true),
            ]);
        }

        return $this->fetch('module:ps_cashondelivery/views/templates/hook/payment_return.tpl');
    }
}
