<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce/includes
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler {

    private static $default_mappings = [
        'en' => 'en_US',
        'fr' => 'fr_XC',
        'es' => 'es_XC',
        'zh' => 'zh_XC',
    ];
    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private static $supported_locales = [
        "en_AL", "ar_DZ", "en_DZ", "fr_DZ", "es_DZ", "zh_DZ", "en_AD", "fr_AD", "es_AD", "zh_AD",
        "en_AO", "fr_AO", "es_AO", "zh_AO", "en_AI", "fr_AI", "es_AI", "zh_AI", "en_AG", "fr_AG",
        "es_AG", "zh_AG", "es_AR", "en_AR", "en_AM", "fr_AM", "es_AM", "zh_AM", "en_AW", "fr_AW",
        "es_AW", "zh_AW", "en_AU", "de_AT", "en_AT", "en_AZ", "fr_AZ", "es_AZ", "zh_AZ", "en_BS",
        "fr_BS", "es_BS", "zh_BS", "ar_BH", "en_BH", "fr_BH", "es_BH", "zh_BH", "en_BB", "fr_BB",
        "es_BB", "zh_BB", "en_BY", "en_BE", "nl_BE", "fr_BE", "en_BZ", "es_BZ", "fr_BZ", "zh_BZ",
        "fr_BJ", "en_BJ", "es_BJ", "zh_BJ", "en_BM", "fr_BM", "es_BM", "zh_BM", "en_BT", "es_BO",
        "en_BO", "fr_BO", "zh_BO", "en_BA", "en_BW", "fr_BW", "es_BW", "zh_BW", "pt_BR", "en_BR",
        "en_VG", "fr_VG", "es_VG", "zh_VG", "en_BN", "en_BG", "fr_BF", "en_BF", "es_BF", "zh_BF",
        "fr_BI", "en_BI", "es_BI", "zh_BI", "en_KH", "fr_CM", "en_CM", "en_CA", "fr_CA", "en_CV",
        "fr_CV", "es_CV", "zh_CV", "en_KY", "fr_KY", "es_KY", "zh_KY", "fr_TD", "en_TD", "es_TD",
        "zh_TD", "es_CL", "en_CL", "fr_CL", "zh_CL", "zh_CN", "es_CO", "en_CO", "fr_CO", "zh_CO",
        "fr_KM", "en_KM", "es_KM", "zh_KM", "en_CG", "fr_CG", "es_CG", "zh_CG", "fr_CD", "en_CD",
        "es_CD", "zh_CD", "en_CK", "fr_CK", "es_CK", "zh_CK", "es_CR", "en_CR", "fr_CR", "zh_CR",
        "fr_CI", "en_CI", "en_HR", "en_CY", "cs_CZ", "en_CZ", "fr_CZ", "es_CZ", "zh_CZ", "da_DK",
        "en_DK", "fr_DJ", "en_DJ", "es_DJ", "zh_DJ", "en_DM", "fr_DM", "es_DM", "zh_DM", "es_DO",
        "en_DO", "fr_DO", "zh_DO", "es_EC", "en_EC", "fr_EC", "zh_EC", "ar_EG", "en_EG", "fr_EG",
        "es_EG", "zh_EG", "es_SV", "en_SV", "fr_SV", "zh_SV", "en_ER", "fr_ER", "es_ER", "zh_ER",
        "en_EE", "ru_EE", "fr_EE", "es_EE", "zh_EE", "en_ET", "fr_ET", "es_ET", "zh_ET", "en_FK",
        "fr_FK", "es_FK", "zh_FK", "da_FO", "en_FO", "fr_FO", "es_FO", "zh_FO", "en_FJ", "fr_FJ",
        "es_FJ", "zh_FJ", "fi_FI", "en_FI", "fr_FI", "es_FI", "zh_FI", "fr_FR", "en_FR", "en_GF",
        "fr_GF", "es_GF", "zh_GF", "en_PF", "fr_PF", "es_PF", "zh_PF", "fr_GA", "en_GA", "es_GA",
        "zh_GA", "en_GM", "fr_GM", "es_GM", "zh_GM", "en_GE", "fr_GE", "es_GE", "zh_GE", "de_DE",
        "en_DE", "en_GI", "fr_GI", "es_GI", "zh_GI", "el_GR", "en_GR", "fr_GR", "es_GR", "zh_GR",
        "da_GL", "en_GL", "fr_GL", "es_GL", "zh_GL", "en_GD", "fr_GD", "es_GD", "zh_GD", "en_GP",
        "fr_GP", "es_GP", "zh_GP", "es_GT", "en_GT", "fr_GT", "zh_GT", "fr_GN", "en_GN", "es_GN",
        "zh_GN", "en_GW", "fr_GW", "es_GW", "zh_GW", "en_GY", "fr_GY", "es_GY", "zh_GY", "es_HN",
        "en_HN", "fr_HN", "zh_HN", "en_HK", "zh_HK", "hu_HU", "en_HU", "fr_HU", "es_HU", "zh_HU",
        "en_IS", "en_IN", "id_ID", "en_ID", "en_IE", "fr_IE", "es_IE", "zh_IE", "he_IL", "en_IL",
        "it_IT", "en_IT", "en_JM", "es_JM", "fr_JM", "zh_JM", "ja_JP", "en_JP", "ar_JO", "en_JO",
        "fr_JO", "es_JO", "zh_JO", "en_KZ", "fr_KZ", "es_KZ", "zh_KZ", "en_KE", "fr_KE", "es_KE",
        "zh_KE", "en_KI", "fr_KI", "es_KI", "zh_KI", "ar_KW", "en_KW", "fr_KW", "es_KW", "zh_KW",
        "en_KG", "fr_KG", "es_KG", "zh_KG", "en_LA", "en_LV", "ru_LV", "fr_LV", "es_LV", "zh_LV",
        "en_LS", "fr_LS", "es_LS", "zh_LS", "en_LI", "fr_LI", "es_LI", "zh_LI", "en_LT", "ru_LT",
        "fr_LT", "es_LT", "zh_LT", "en_LU", "de_LU", "fr_LU", "es_LU", "zh_LU", "en_MK", "en_MG",
        "fr_MG", "es_MG", "zh_MG", "en_MW", "fr_MW", "es_MW", "zh_MW", "en_MY", "en_MV", "fr_ML",
        "en_ML", "es_ML", "zh_ML", "en_MT", "en_MH", "fr_MH", "es_MH", "zh_MH", "en_MQ", "fr_MQ",
        "es_MQ", "zh_MQ", "en_MR", "fr_MR", "es_MR", "zh_MR", "en_MU", "fr_MU", "es_MU", "zh_MU",
        "en_YT", "fr_YT", "es_YT", "zh_YT", "es_MX", "en_MX", "en_FM", "en_MD", "fr_MC", "en_MC",
        "en_MN", "en_ME", "en_MS", "fr_MS", "es_MS", "zh_MS", "ar_MA", "en_MA", "fr_MA", "es_MA",
        "zh_MA", "en_MZ", "fr_MZ", "es_MZ", "zh_MZ", "en_NA", "fr_NA", "es_NA", "zh_NA", "en_NR",
        "fr_NR", "es_NR", "zh_NR", "en_NP", "nl_NL", "en_NL", "en_AN", "fr_AN", "es_AN", "zh_AN",
        "en_NC", "fr_NC", "es_NC", "zh_NC", "en_NZ", "fr_NZ", "es_NZ", "zh_NZ", "es_NI", "en_NI",
        "fr_NI", "zh_NI", "fr_NE", "en_NE", "es_NE", "zh_NE", "en_NG", "en_NU", "fr_NU", "es_NU",
        "zh_NU", "en_NF", "fr_NF", "es_NF", "zh_NF", "no_NO", "en_NO", "ar_OM", "en_OM", "fr_OM",
        "es_OM", "zh_OM", "en_PW", "fr_PW", "es_PW", "zh_PW", "es_PA", "en_PA", "fr_PA", "zh_PA",
        "en_PG", "fr_PG", "es_PG", "zh_PG", "es_PY", "en_PY", "es_PE", "en_PE", "fr_PE", "zh_PE",
        "en_PH", "en_PN", "fr_PN", "es_PN", "zh_PN", "pl_PL", "en_PL", "pt_PT", "en_PT", "en_QA",
        "fr_QA", "es_QA", "zh_QA", "ar_QA", "en_RE", "fr_RE", "es_RE", "zh_RE", "en_RO", "fr_RO",
        "es_RO", "zh_RO", "ru_RU", "en_RU", "fr_RW", "en_RW", "es_RW", "zh_RW", "en_WS", "en_SM",
        "fr_SM", "es_SM", "zh_SM", "en_ST", "fr_ST", "es_ST", "zh_ST", "ar_SA", "en_SA", "fr_SA",
        "es_SA", "zh_SA", "fr_SN", "en_SN", "es_SN", "zh_SN", "en_RS", "fr_RS", "es_RS", "zh_RS",
        "fr_SC", "en_SC", "es_SC", "zh_SC", "en_SL", "fr_SL", "es_SL", "zh_SL", "en_SG", "sk_SK",
        "en_SK", "fr_SK", "es_SK", "zh_SK", "en_SI", "fr_SI", "es_SI", "zh_SI", "en_SB", "fr_SB",
        "es_SB", "zh_SB", "en_SO", "fr_SO", "es_SO", "zh_SO", "en_ZA", "fr_ZA", "es_ZA", "zh_ZA",
        "ko_KR", "en_KR", "es_ES", "en_ES", "en_LK", "en_SH", "fr_SH", "es_SH", "zh_SH", "en_KN",
        "fr_KN", "es_KN", "zh_KN", "en_LC", "fr_LC", "es_LC", "zh_LC", "en_PM", "fr_PM", "es_PM",
        "zh_PM", "en_VC", "fr_VC", "es_VC", "zh_VC", "en_SR", "fr_SR", "es_SR", "zh_SR", "en_SJ",
        "fr_SJ", "es_SJ", "zh_SJ", "en_SZ", "fr_SZ", "es_SZ", "zh_SZ", "sv_SE", "en_SE", "de_CH",
        "fr_CH", "en_CH", "zh_TW", "en_TW", "en_TJ", "fr_TJ", "es_TJ", "zh_TJ", "en_TZ", "fr_TZ",
        "es_TZ", "zh_TZ", "th_TH", "en_TH", "fr_TG", "en_TG", "es_TG", "zh_TG", "en_TO", "en_TT",
        "fr_TT", "es_TT", "zh_TT", "ar_TN", "en_TN", "fr_TN", "es_TN", "zh_TN", "en_TM", "fr_TM",
        "es_TM", "zh_TM", "en_TC", "fr_TC", "es_TC", "zh_TC", "en_TV", "fr_TV", "es_TV", "zh_TV",
        "tr_TR", "en_TR", "en_UG", "fr_UG", "es_UG", "zh_UG", "en_UA", "ru_UA", "fr_UA", "es_UA",
        "zh_UA", "en_AE", "fr_AE", "es_AE", "zh_AE", "ar_AE", "en_GB", "en_US", "fr_US", "es_US",
        "zh_US", "es_UY", "en_UY", "fr_UY", "zh_UY", "en_VU", "fr_VU", "es_VU", "zh_VU", "en_VA",
        "fr_VA", "es_VA", "zh_VA", "es_VE", "en_VE", "fr_VE", "zh_VE", "en_VN", "en_WF", "fr_WF",
        "es_WF", "zh_WF", "ar_YE", "en_YE", "fr_YE", "es_YE", "zh_YE", "en_ZM", "fr_ZM", "es_ZM",
        "zh_ZM", "en_ZW"
    ];

    public static function get_valid_locale() {
        $locale = self::get_locale();

        if (!empty($locale) && self::is_supported($locale)) {
            return $locale;
        }

        return 'en_US';
    }

    public static function get_locale() {
        $locale = get_locale();

        if (strlen($locale) === 2) {
            $locale = self::$default_mappings[$locale] ?? "{$locale}_" . strtoupper($locale);
        } elseif (strlen($locale) > 5) {
            $locale = substr($locale, 0, 5);
        }

        return $locale;
    }

    public static function is_supported($locale) {
        return in_array($locale, self::$supported_locales, true);
    }

    public static function get_supported_locales() {
        return self::$supported_locales;
    }
}
