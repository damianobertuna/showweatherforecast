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

class Showweatherforecast extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'showweatherforecast';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'DAMIANO BERTUNA';
        $this->need_instance = 0;
        $this->_html = '';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Show weather forecast');
        $this->description = $this->trans('This module lets you show the weather forecast based on your IP location.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('SHOWWEATHERFORECAST_API_KEY', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayNav1');
    }


    public function uninstall()
    {
        Configuration::deleteByName('SHOWWEATHERFORECAST_API_KEY');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitShowweatherforecastModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $output .= $this->_html;
        $output .= $this->renderForm();
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
        $helper->submit_action = 'submitShowweatherforecastModule';
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
                'title' => $this->trans('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->trans('Enter a valid API key from openweathermap'),
                        'name' => 'SHOWWEATHERFORECAST_API_KEY',
                        'label' => $this->trans('API Key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save'),
                    'class' => 'btn btn-default pull-left'
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
            'SHOWWEATHERFORECAST_API_KEY' => Configuration::get('SHOWWEATHERFORECAST_API_KEY', ''),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            $res = Configuration::updateValue($key, Tools::getValue($key));
            if (!$res) {
                return $res;
            }
        }
        return $res;
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {        
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookDisplayNav1()
    {
        $apiKey = Configuration::get('SHOWWEATHERFORECAST_API_KEY');

        if ($apiKey == "") {
            return false;
        }

        if (!file_exists(_PS_GEOIP_DIR_ . _PS_GEOIP_CITY_FILE_)) {
            return false;
        }

        $geoLocationIp = Tools::getRemoteAddr();
        $reader = new GeoIp2\Database\Reader(_PS_GEOIP_DIR_ . _PS_GEOIP_CITY_FILE_);
        $error  = "";

        try {
            $record = $reader->city($geoLocationIp);
            if ($record->city->name == NULL) {
                $error  = "City not found for this IP ".$geoLocationIp;
            }            
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            $record = null;
            return false;
        }

        $url = 'http://api.openweathermap.org/data/2.5/weather?q='.$record->city->name.'&appid='.$apiKey.'&units=metric';
        $weatherData = $this->cUrl($url);
        $weatherData = json_decode($weatherData);

        if ($weatherData == NULL) {
            return false;
        }

        $weatherMain                = $weatherData->weather[0]->main;
        $weatherDescription         = $weatherData->weather[0]->description;
        $temperature                = $weatherData->main->temp;
        $wind                       = $weatherData->wind->speed;
        $country                    = $weatherData->sys->country;

        $this->context->smarty->assign(array(
            'weatherMain'           => $weatherMain,
            'weatherDescription'    => $weatherDescription,
            'temperature'           => $temperature,
            'wind'                  => $wind,
            'country'               => $country,
            'city'                  => $record->city->name,
            'error'                 => $error,
        ));
        
        return $this->display(dirname(__FILE__), '/views/templates/hook/forecast.tpl');
    }

    private function cUrl($url) {
		$ch 				= curl_init();
		
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		$result_live 		= curl_exec($ch);

		$info 			= curl_getinfo($ch);
		$curl_status	= $info["http_code"];

		if ($curl_status != 200 && $curl_status != 302) {
			return false;
		}

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$result_live = substr($result_live, $header_size);

		curl_close($ch);
		return $result_live;
	}
}
