<?php

defined('ABSPATH') || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate {

    protected static $_instance = null;
    public $country;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * The matrix which countries and currency combinations can be used for DCC.
     *
     */
    private $allowed_country_currency_matrix = array(
        // Standard 22 supported currencies for Expanded Checkout
        'AU' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'AT' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'BE' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'BG' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'CA' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'CN' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'CY' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'CZ' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'DK' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'EE' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'FI' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'FR' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'DE' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'GR' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'HK' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'HU' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'IE' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'IT' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'JP' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'LV' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'LI' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'LT' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'LU' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'MT' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'MX' => array('MXN'),
        'NL' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'NO' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'PL' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'PT' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'RO' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'SG' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'SK' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'SI' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'ES' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'SE' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'GB' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
        'US' => array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
    );

    /**
     * Which countries support which credit cards.
     */
    private $country_card_matrix = array(
        // MOST COUNTRIES — Mastercard / Visa / Amex with NO restrictions
        'AU' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'AT' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'BE' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'BG' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'CY' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'CZ' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'DK' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'EE' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'FI' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'FR' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'DE' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'GR' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'HK' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'HU' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'IE' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'IT' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'LV' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'LI' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'LT' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'LU' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'MT' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'NL' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'NO' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'PL' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'PT' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'RO' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'SG' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'SK' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'SI' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'ES' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'SE' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        'GB' => array('mastercard' => array(), 'visa' => array(), 'amex' => array()),
        // SPECIAL CASE COUNTRIES
        'CA' => array(
            'mastercard' => array(),
            'visa' => array(),
            'amex' => array('CAD', 'USD'), // Limited by spec
            'jcb' => array('CAD'),
        ),
        'CN' => array(
            'mastercard' => array(),
            'visa' => array(),
        // No Amex support in China
        ),
        'JP' => array(
            'mastercard' => array(),
            'visa' => array(),
            'amex' => array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD'),
            'jcb' => array('JPY'),
        ),
        'MX' => array(
            'mastercard' => array('MXN'),
            'visa' => array('MXN'),
            'amex' => array('MXN'),
        ),
        'US' => array(
            'mastercard' => array(),
            'visa' => array(),
            'amex' => array(),
            'discover' => array('USD'),
        ),
    );

    /**
     * Returns whether DCC can be used in the current country and the current currency used.
     */
    public function for_country_currency($country = null) {
        try {
            if ($country === null) {
                $country = $this->country();
            }
            $currency = get_woocommerce_currency();
            if (!in_array($country, array_keys($this->allowed_country_currency_matrix), true)) {
                return false;
            }
            $applies = in_array($currency, $this->allowed_country_currency_matrix[$country], true);
            return $applies;
        } catch (Exception $ex) {
            
        }
    }

    /**
     * Returns credit cards, which can be used.
     */
    public function valid_cards() {
        try {
            $this->country = $this->country();
            $cards = array();
            if (!isset($this->country_card_matrix[$this->country])) {
                return $cards;
            }

            $supported_currencies = $this->country_card_matrix[$this->country];
            foreach ($supported_currencies as $card => $currencies) {
                if ($this->can_process_card($card)) {
                    $cards[] = $card;
                }
            }
            if (in_array('amex', $cards, true)) {
                $cards[] = 'american-express';
            }
            if (in_array('mastercard', $cards, true)) {
                $cards[] = 'master-card';
            }
            return $cards;
        } catch (Exception $ex) {
            
        }
    }

    /**
     * Whether a card can be used or not.
     */
    public function can_process_card($card) {
        try {
            $this->country = $this->country();
            if (!isset($this->country_card_matrix[$this->country])) {
                return false;
            }
            if (!isset($this->country_card_matrix[$this->country][$card])) {
                return false;
            }
            $supported_currencies = $this->country_card_matrix[$this->country][$card];
            $currency = get_woocommerce_currency();
            return empty($supported_currencies) || in_array($currency, $supported_currencies, true);
        } catch (Exception $ex) {
            
        }
    }

    public function country() {
        try {
            $region = wc_get_base_location();
            $country = $region['country'];
            return $country;
        } catch (Exception $ex) {
            
        }
    }
}
