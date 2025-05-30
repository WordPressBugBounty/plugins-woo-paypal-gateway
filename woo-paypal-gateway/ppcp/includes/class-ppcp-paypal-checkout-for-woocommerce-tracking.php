<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce/includes
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Tracking {

    private static $instance = null;
    public $screen_id;
    public $carriers;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('current_screen', array($this, 'get_current_screen'));
        add_action('wp_ajax_submit_tracking_info', [$this, 'save_tracking_details']);
        //add_action('woocommerce_shipstation_shipnotify', [$this, 'handle_notification'], 10, 2);
        add_filter('wc_shipment_tracking_before_add_tracking_items', [$this, 'intercept_tracking_before_add'], 10, 3);
    }

    public function get_current_screen() {
        $screen = get_current_screen();
        if (!isset($screen->id)) {
            return;
        }
        global $pagenow;
        if (( 'shop_order' === $screen->id && 'post-new.php' === $pagenow ) || ( 'woocommerce_page_wc-orders' === $screen->id )) {
            $this->screen_id = $screen->id;
            add_action('add_meta_boxes', array($this, 'add_tracking_meta_box'), 1);
        }
    }

    public function intercept_tracking_before_add($tracking_items, $tracking_item, $order_id) {
        $logger = wc_get_logger();
        $context = ['source' => 'wpg_paypal_checkout'];

        try {
            $logger->debug("Intercepting tracking for order ID: {$order_id}", $context);

            $order = wc_get_order($order_id);
            if (!$order || !is_a($order, 'WC_Order')) {
                $logger->debug("Invalid or missing order object.", $context);
                return $tracking_items;
            }

            if (empty($tracking_item['tracking_number']) || empty($tracking_item['tracking_provider'])) {
                $logger->debug("Missing tracking number or provider in tracking item.", $context);
                return $tracking_items;
            }

            $txn_id = $order->get_transaction_id();
            if (empty($txn_id)) {
                $logger->debug("No transaction ID found for order ID: {$order_id}", $context);
                return $tracking_items;
            }

            $tracking_data = [
                'trackers' => [
                    [
                        'transaction_id' => $txn_id,
                        'tracking_number' => sanitize_text_field($tracking_item['tracking_number']),
                        'status' => 'SHIPPED',
                        'carrier' => sanitize_text_field($tracking_item['tracking_provider']),
                    ]
                ]
            ];

            $logger->debug("Prepared tracking data: " . wc_print_r($tracking_data, true), $context);

            $existing = $order->get_meta('_ppcp_tracking_number');
            if ($existing === $tracking_data['trackers'][0]['tracking_number']) {
                $logger->debug("Tracking number already exists for order, skipping re-submission.", $context);
                return $tracking_items;
            }

            $order->update_meta_data('_ppcp_tracking_info', $tracking_data);
            $order->update_meta_data('_ppcp_tracking_number', $tracking_data['trackers'][0]['tracking_number']);
            $order->save();

            $logger->debug("Tracking data saved to order meta. Sending to PayPal...", $context);

            $this->send_tracking_to_paypal($order_id, $tracking_data);

            $logger->debug("Tracking successfully submitted to PayPal.", $context);
            return $tracking_items;
        } catch (Exception $ex) {
            $logger->error("Exception in intercept_tracking_before_add: " . $ex->getMessage(), $context);
            return $tracking_items;
        }
    }

    public function handle_notification($order, $args) {
        try {
            if (!is_a($order, 'WC_Order') || empty($args['tracking_number']) || empty($args['carrier'])) {
                return;
            }
            $tracking_number = sanitize_text_field($args['tracking_number']);
            $carrier = sanitize_text_field($args['carrier']);
            $txn_id = $order->get_transaction_id();
            if (empty($txn_id)) {
                return;
            }
            $tracking_data = array(
                'trackers' => array(
                    array(
                        'transaction_id' => $txn_id,
                        'tracking_number' => $tracking_number,
                        'status' => 'SHIPPED',
                        'carrier' => $carrier,
                    )
                )
            );
            $existing = $order->get_meta('_ppcp_tracking_number');
            if ($existing === $tracking_number) {
                return;
            }
            $order->update_meta_data('_ppcp_tracking_info', $tracking_data);
            $order->update_meta_data('_ppcp_tracking_number', $tracking_number);
            $order->save();
            $this->send_tracking_to_paypal($order->get_id(), $tracking_data);
        } catch (Exception $ex) {
            
        }
    }

    public function add_tracking_meta_box() {
        add_meta_box(
                'ppcp_paypal_tracking_box',
                __('PayPal Shipment Tracking', 'woo-paypal-gateway'),
                array($this, 'render_tracking_meta_box'),
                'woocommerce_page_wc-orders',
                'side',
                'high'
        );
    }

    public function render_tracking_meta_box($post_or_order_object) {
        $wc_order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
        if (!is_a($wc_order, 'WC_Order')) {
            return;
        }
        wp_enqueue_style('ppcp-paypal-checkout-for-woocommerce-admin', WPG_PLUGIN_ASSET_URL . 'ppcp/admin/css/ppcp-paypal-checkout-for-woocommerce-tracking.css', array(), WPG_PLUGIN_VERSION, 'all');
        wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-admin', WPG_PLUGIN_ASSET_URL . 'ppcp/admin/js/ppcp-paypal-checkout-for-woocommerce-tracking.js', array('jquery'), WPG_PLUGIN_VERSION, false);
        $capture_id = $wc_order->get_transaction_id();
        $shipments = $wc_order->get_meta('_ppcp_tracking_info');
        $this->carriers = $this->carrier_name();
        $tracking_number = '';
        $status = '';
        $carrier = '';
        if (!empty($shipments['trackers'][0])) {
            $tracking_data = $shipments['trackers'][0];
            $tracking_number = $tracking_data['tracking_number'];
            $status = $tracking_data['status'];
            $carrier = $tracking_data['carrier'];
        }
        ?>
        <div class="ppcp-tracking-columns-wrapper">
            <div class="ppcp-tracking-column">
                <p>
                    <label for="ppcp-tracking-capture_id"><?php esc_html_e('Transaction Id', 'woo-paypal-gateway'); ?></label>
                    <input type="text" disabled class="ppcp-tracking-capture_id disabled" id="ppcp-tracking-capture_id" name="ppcp-tracking[capture_id]" value="<?php echo esc_attr($capture_id); ?>" />
                </p>
                <p>
                    <label for="ppcp-tracking-tracking_number"><?php esc_html_e('Tracking Number*', 'woo-paypal-gateway'); ?></label>
                    <input type="text" class="ppcp-tracking-tracking_number" id="ppcp-tracking-tracking_number" name="ppcp-tracking[tracking_number]" maxlength="64" value="<?php echo esc_attr($tracking_number); ?>" />
                </p>
                <p>
                    <label for="ppcp-tracking-status"><?php esc_html_e('Status', 'woo-paypal-gateway'); ?></label>
                    <select class="wc-enhanced-select ppcp-tracking-status" id="ppcp-tracking-status" name="ppcp-tracking[status]" tabindex="-1" aria-hidden="true">
                        <option value="SHIPPED" <?php selected($status, 'SHIPPED'); ?>>Shipped</option>
                        <option value="ON_HOLD" <?php selected($status, 'ON_HOLD'); ?>>On Hold</option>
                        <option value="DELIVERED" <?php selected($status, 'DELIVERED'); ?>>Delivered</option>
                        <option value="CANCELLED" <?php selected($status, 'CANCELLED'); ?>>Cancelled</option>
                    </select>
                </p>
                <p>
                    <label for="ppcp-tracking-carrier"><?php esc_html_e('Carrier', 'woo-paypal-gateway'); ?></label>
                    <select class="wc-enhanced-select ppcp-tracking-carrier" id="ppcp-tracking-carrier" name="ppcp-tracking[carrier]">
                        <option value=""><?php esc_html_e('Select Carrier', 'woo-paypal-gateway'); ?></option> <!-- Default option -->
                        <?php foreach ($this->carriers as $carrier_group) : ?>
                            <?php if (empty($carrier_group)) continue; ?>
                            <optgroup label="<?php echo esc_attr($carrier_group['name']); ?>">
                                <?php foreach ($carrier_group['items'] as $carrier_code => $carrier_name) : ?>
                                    <option value="<?php echo esc_attr($carrier_code); ?>" <?php selected($carrier, $carrier_code); ?>>
                                        <?php echo esc_html($carrier_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="hidden">
                    <label for="ppcp-tracking-carrier_name_other"><?php esc_html_e('Carrier Name*', 'woo-paypal-gateway'); ?></label>
                    <input type="text" class="ppcp-tracking-carrier_name_other" id="ppcp-tracking-carrier_name_other" 
                           name="ppcp-tracking[carrier_name_other]" />
                </p>
                <input type="hidden" id="ppcp-tracking-order_id" name="ppcp-tracking[order_id]" value="<?php echo (int) $wc_order->get_id(); ?>" />
                <p>
                    <button type="button" class="button submit_tracking_info">
                        <?php esc_html_e('Add tracking information', 'woo-paypal-gateway'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_tracking_details() {
        if (isset($_POST['capture_id'], $_POST['tracking_number'], $_POST['status'], $_POST['carrier'], $_POST['order_id'])) {
            $capture_id = sanitize_text_field($_POST['capture_id']);
            $tracking_number = sanitize_text_field($_POST['tracking_number']);
            $status = sanitize_text_field($_POST['status']);
            $carrier = sanitize_text_field($_POST['carrier']);
            $order_id = sanitize_text_field($_POST['order_id']);
            $wc_order = wc_get_order($order_id);
            if ($wc_order) {
                $ppcp_tracking_info = array(
                    'trackers' => array(
                        array(
                            'transaction_id' => $capture_id,
                            'tracking_number' => $tracking_number,
                            'status' => $status,
                            'carrier' => $carrier
                        )
                    )
                );
            }
            $wc_order->update_meta_data('_ppcp_tracking_info', $ppcp_tracking_info);
            $wc_order->save();
            $bool = $this->send_tracking_to_paypal($order_id, $ppcp_tracking_info);
            if ($bool) {
                wp_send_json_success('Tracking info submit successfully!');
            } else {
                wp_send_json_error('Error.');
            }
        } else {
            wp_send_json_error('Missing required fields.');
        }
    }

    private function send_tracking_to_paypal($order_id, $ppcp_tracking_info) {
        include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        return $request->ppcp_add_tracking_api_info($order_id, $ppcp_tracking_info);
    }

    public function carrier_name() {
        return array(
            'global' => array(
                'name' => 'Global',
                'items' => array(
                    '99MINUTOS' => '99minutos',
                    'A2B_BA' => 'A2B Express Logistics',
                    'ABCUSTOM_SFTP' => 'AB Custom Group',
                    'ACILOGISTIX' => 'ACI Logistix',
                    'ACOMMERCE' => 'ACOMMERCE',
                    'ACTIVOS24_API' => 'Activos24',
                    'ADS' => 'ADS Express',
                    'AEROFLASH' => 'AEROFLASH',
                    'AGEDISS_SFTP' => 'Agediss',
                    'AIR_21' => 'AIR 21',
                    'AIRSPEED' => 'AIRSPEED',
                    'AIRTERRA' => 'Airterra',
                    'AITWORLDWIDE_API' => 'AIT',
                    'AITWORLDWIDE_SFTP' => 'AIT',
                    'ALLIED_EXPRESS_FTP' => 'Allied Express (FTP)',
                    'ALLJOY' => 'ALLJOY SUPPLY CHAIN',
                    'AMAZON_EMAIL_PUSH' => 'Amazon',
                    'AMAZON_ORDER' => 'Amazon order',
                    'AMAZON_UK_API' => 'amazon_uk_api',
                    'AMS_GRP' => 'AMS Group',
                    'ANDREANI_API' => 'Andreani',
                    'ANTERAJA' => 'Anteraja',
                    'ARAMEX' => 'Aramex',
                    'ARAMEX_API' => 'Aramex',
                    'ARASKARGO' => 'Aras Cargo',
                    'ARGENTS_WEBHOOK' => 'Argents Express Group',
                    'ASENDIA_DE' => 'asendia_de',
                    'ATSHEALTHCARE_REFERENCE' => 'ATS Healthcare',
                    'ATSHEALTHCARE' => 'ATS Healthcare',
                    'AUEXPRESS' => 'Au Express',
                    'AUSTRALIA_POST_API' => 'Australia Post',
                    'AVERITT' => 'Averitt Express',
                    'AXLEHIRE_FTP' => 'Axlehire',
                    'AXLEHIRE' => 'AxleHire',
                    'BARQEXP' => 'Barq',
                    'BDMNET' => 'BDMnet',
                    'BEL_BELGIUM_POST' => 'bel_belgium_post',
                    'BLR_BELPOST' => 'Belpost',
                    'BERT' => 'BERT',
                    'BESTTRANSPORT_SFTP' => 'Best Transport',
                    'BETTERTRUCKS' => 'Better Trucks',
                    'BIGSMART' => 'Big Smart',
                    'BIOCAIR_FTP' => 'BioCair',
                    'BJSHOMEDELIVERY' => 'BJS Distribution courier',
                    'BJSHOMEDELIVERY_FTP' => 'BJS Distribution, Storage & Couriers - FTP',
                    'BLUEDART' => 'BLUEDART',
                    'BLUEDART_API' => 'Bluedart',
                    'BOLLORE_LOGISTICS' => 'Bollore Logistics',
                    'BOMI' => 'Bomi Group',
                    'BE_BPOST' => 'Bpost (www.bpost.be)',
                    'BPOST_API' => 'Bpost API',
                    'BPOST_INT' => 'Bpost international',
                    'BRT_IT_API' => 'BRT Bartolini API',
                    'BUFFALO' => 'BUFFALO',
                    'BURD' => 'Burd Delivery',
                    'CHROBINSON' => 'C.H. Robinson Worldwide',
                    'CAGO' => 'Cago',
                    'CANPAR' => 'CANPAR',
                    'CAPITAL' => 'Capital Transport',
                    'CARRY_FLAP' => 'Carry-Flap Co.',
                    'CBL_LOGISTICA_API' => 'CBL Logistica (API)',
                    'CDLDELIVERS' => 'CDL Last Mile',
                    'CELERITAS' => 'Celeritas Transporte',
                    'CEVA' => 'CEVA LOGISTICS',
                    'CEVA_TRACKING' => 'CEVA Package',
                    'CHAZKI' => 'Chazki',
                    'CHIENVENTURE_WEBHOOK' => 'Chienventure',
                    'CHILEXPRESS' => 'Chile Express',
                    'CITY56_WEBHOOK' => 'City Express',
                    'CJ_GLS' => 'CJ GLS',
                    'CJ_LOGISTICS' => 'CJ Logistics International',
                    'CJ_PHILIPPINES' => 'cj_philippines',
                    'CLICKLINK_SFTP' => 'ClickLink',
                    'CN_LOGISTICS' => 'CN Logistics',
                    'COLLECTPLUS' => 'COLLECTPLUS',
                    'COM1EXPRESS' => 'ComOne Express',
                    'CONCISE' => 'Concise',
                    'CONCISE_WEBHOOK' => 'Concise',
                    'CONCISE_API' => 'Concise',
                    'COORDINADORA_API' => 'Coordinadora',
                    'COPA_COURIER' => 'Copa Airlines Courier',
                    'CORREOS_DE_ESPANA' => 'CORREOS DE ESPANA',
                    'CORREOSEXPRESS_API' => 'Correos Express (API)',
                    'CORREOS_ES' => 'correos Express (www.correos.es)',
                    'COURANT_PLUS_API' => 'Courant Plus',
                    'COURIER_POST' => 'COURIER POST',
                    'COURIERPLUS' => 'COURIERPLUS',
                    'CRLEXPRESS' => 'CRL Express',
                    'CROSSFLIGHT' => 'Crossflight Limited',
                    'CRYOPDP_FTP' => 'CryoPDP',
                    'CESKAPOSTA_API' => 'Czech Post',
                    'DEXPRESS_WEBHOOK' => 'D Express',
                    'DACHSER' => 'DACHSER',
                    'DACHSER_WEB' => 'DACHSER',
                    'DAESHIN' => 'Daeshin',
                    'DAIICHI' => 'Daiichi Freight System Inc',
                    'DANNIAO' => 'Danniao',
                    'DAO365' => 'DAO365',
                    'DAYROSS' => 'Day & Ross',
                    'DYLT' => 'Daylight Transport',
                    'DBSCHENKER_API' => 'DB Schenker',
                    'DBSCHENKER_B2B' => 'DB Schenker B2B',
                    'DBSCHENKER_ICELAND' => 'DB Schenker Iceland',
                    'DDEXPRESS' => 'DD Express Courier',
                    'DE_DHL' => 'DE DHL',
                    'DELCART_IN' => 'delcart_in',
                    'DELIVERYOURPARCEL_ZA' => 'Deliver Your Parcel',
                    'DELIVER_IT' => 'Deliver-iT',
                    'DELIVERE' => 'delivere',
                    'DELIVERR_SFTP' => 'Deliverr',
                    'DELTEC_DE' => 'DELTEC DE',
                    'DEMANDSHIP' => 'DemandShip',
                    'DEUTSCHE_DE' => 'deutsche_de',
                    'DHL_API' => 'DHL',
                    'DHL_REFERENCE_API' => 'DHL (Reference number)',
                    'DHL_ACTIVE_TRACING' => 'DHL Active Tracing',
                    'DHL_ECOMMERCE_GC' => 'DHL eCommerce Greater China',
                    'DHL_GLOBAL_MAIL_API' => 'DHL eCommerce Solutions',
                    'DE_DHL_EXPRESS' => 'DHL Express',
                    'DHL_SFTP' => 'DHL Express',
                    'DHL_FR' => 'DHL France (www.dhl.com)',
                    'DHL_FREIGHT' => 'DHL Freight',
                    'DHL' => 'dhl Global',
                    'DHL_GLOBAL_FORWARDING_API' => 'DHL Global Forwarding API',
                    'DHL_GT_API' => 'DHL Global Forwarding Guatemala',
                    'DHL_PA_API' => 'DHL GLOBAL FORWARDING PANAMÁ',
                    'IT_DHL_ECOMMERCE' => 'DHL International',
                    'DHL_JP' => 'DHL Japan',
                    'DHL_PARCEL_NL' => 'DHL Parcel NL',
                    'DHL_SG' => 'dhl Singapore',
                    'DHL_ES_SFTP' => 'DHL Spain Domestic',
                    'DHL_SUPPLYCHAIN_IN' => 'DHL supply chain India',
                    'DHL_SUPPLYCHAIN_ID' => 'DHL Supply Chain Indonesia',
                    'DHL_AT' => 'dhl_at',
                    'DHL_GLOBAL_MAIL' => 'dhl_global_mail',
                    'DHL_IT' => 'dhl_it',
                    'DHL_PIECEID' => 'dhl_pieceid',
                    'DHL_SUPPLY_CHAIN_AU' => 'dhl_supply_chain_au',
                    'DHLPARCEL_UK' => 'dhlparcel_uk',
                    'DIALOGO_LOGISTICA_API' => 'Dialogo Logistica',
                    'DIALOGO_LOGISTICA' => 'Dialogo Logistica',
                    'DIRECTFREIGHT_AU_REF' => 'Direct Freight Express',
                    'DIREX' => 'Direx',
                    'DKSH' => 'DKSH',
                    'DMFGROUP' => 'DMF',
                    'DNJ_EXPRESS' => 'DNJ Express',
                    'DOTZOT' => 'DOTZOT',
                    'DPD' => 'DPD',
                    'DPD_AT_SFTP' => 'DPD Austria',
                    'DPD_DELISTRACK' => 'DPD delistrack',
                    'DPD_NL' => 'DPD Netherlands',
                    'DPD_RU_API' => 'DPD Russia',
                    'DPD_SK_SFTP' => 'DPD Slovakia',
                    'DPD_CH_SFTP' => 'DPD Switzerland',
                    'DPD_UK_SFTP' => 'DPD UK',
                    'DPD_DE' => 'dpd_de',
                    'DPD_FR_REFERENCE' => 'dpd_fr_reference',
                    'DPD_UK' => 'dpd_uk',
                    'CN_DPEX' => 'DPEX',
                    'DPEX' => 'DPEX (www.dpex.com)',
                    'DSV' => 'DSV courier',
                    'DSV_REFERENCE' => 'DSV Futurewave',
                    'DX' => 'DX',
                    'DX_B2B_CONNUM' => 'DX (B2B)',
                    'DX_FREIGHT' => 'DX Freight',
                    'DYNALOGIC' => 'Dynamic Logistics',
                    'EASTWESTCOURIER_FTP' => 'East West Courier Pte Ltd',
                    'EC_CN' => 'EC_CN',
                    'ECARGO' => 'ECARGO',
                    'ECEXPRESS' => 'ECexpress',
                    'ECMS' => 'ECMS International Logistics Co.',
                    'ECOFREIGHT' => 'Eco Freight',
                    'ECOURIER' => 'ecourier',
                    'ECOUTIER' => 'eCoutier',
                    'EFS' => 'EFS (E-commerce Fulfillment Service)',
                    'ELITE_CO' => 'Elite Express',
                    'ELOGISTICA' => 'elogistica',
                    'ELTA_GR' => 'elta_gr',
                    'ARE_EMIRATES_POST' => 'Emirates Post',
                    'EMS' => 'EMS',
                    'EMS_CN' => 'ems_cn',
                    'ENSENDA' => 'ENSENDA',
                    'EFWNOW_API' => 'Estes Forwarding Worldwide',
                    'ETOMARS' => 'Etomars',
                    'ETOTAL' => 'eTotal Solution Limited',
                    'EDF_FTP' => 'Eurodifarm',
                    'EURODIS' => 'eurodis',
                    'EUROPAKET_API' => 'Europacket+',
                    'MYHERMES_UK_API' => 'EVRi',
                    'EWE' => 'EWE Global Express',
                    'EXELOT_FTP' => 'Exelot Ltd.',
                    'EXPEDITORS' => 'Expeditors',
                    'EXPEDITORS_API_REF' => 'Expeditors API Reference',
                    'EZSHIP' => 'EZship',
                    'FAIRSENDEN_API' => 'fairsenden',
                    'FXTRAN' => 'Falcon Express',
                    'FAN' => 'FAN COURIER EXPRESS',
                    'FNF_ZA' => 'Fast & Furious',
                    'FASTDESPATCH' => 'Fast Despatch Logistics Limited',
                    'FASTBOX' => 'Fastbox',
                    'FASTSHIP' => 'Fastship Express',
                    'FASTTRACK' => 'fasttrack',
                    'FASTWAY_AU' => 'fastway_au',
                    'FASTWAY_UK' => 'FASTWAY_UK',
                    'FASTWAY_US' => 'FASTWAY_US',
                    'FASTWAY_ZA' => 'fastway_za',
                    'FAXECARGO' => 'Faxe Cargo',
                    'FEDEX_FR' => 'FedEx® Freight',
                    'FEDEX_API' => 'FedEx®',
                    'FERCAM_IT' => 'fercam_it',
                    'FETCHR' => 'Fetchr',
                    'FIRST_LOGISTICS_API' => 'First Logistics',
                    'FIRST_LOGISITCS' => 'first_logisitcs',
                    'FITZMARK_API' => 'FitzMark',
                    'FLASHEXPRESS_WEBHOOK' => 'Flash Express',
                    'FLIGHTLG' => 'Flight Logistics Group',
                    'FLIPXP' => 'FlipXpress',
                    'FLYTEXPRESS' => 'FLYTEXPRESS',
                    'FORWARDAIR' => 'Forward Air',
                    'FOUR_PX_EXPRESS' => 'FOUR PX EXPRESS',
                    'FR_COLISSIMO' => 'fr_colissimo',
                    'FR_MONDIAL' => 'fr_mondial',
                    'FRAGILEPAK_SFTP' => 'FragilePAK',
                    'FRONTDOORCORP' => 'FRONTdoor Collective',
                    'FUJEXP' => 'FUJIE EXPRESS',
                    'GWLOGIS_API' => 'G.I.G',
                    'GAC' => 'GAC',
                    'GATI_KWE_API' => 'Gati-KWE',
                    'GDPHARM' => 'GDPharm Logistics',
                    'GW_WORLD' => 'Gebrüder Weiss',
                    'GEODIS' => 'GEODIS',
                    'GEODIS_API' => 'GEODIS - Distribution & Express',
                    'GPOST' => 'Georgian Post',
                    'GIAO_HANG' => 'Giao hàng nhanh',
                    'GIO_ECOURIER_API' => 'GIO Express Ecourier',
                    'GIO_ECOURIER' => 'GIO Express Inc',
                    'GOGLOBALPOST' => 'Global Post',
                    'GLOBEGISTICS' => 'GLOBEGISTICS',
                    'GLOVO' => 'Glovo',
                    'GLS' => 'GLS',
                    'GLS_SPAIN_API' => 'GLS Spain',
                    'GLS_DE' => 'GLS_DE',
                    'GLS_ES' => 'GLS_ES',
                    'GLS_FR' => 'GLS_FR',
                    'GLS_ITALY_FTP' => 'gls_italy_ftp',
                    'GLS_SPAIN' => 'gls_spain',
                    'GOLS' => 'GO Logistics & Storage',
                    'GOPEOPLE' => 'Go People',
                    'GORUSH' => 'Go Rush',
                    'GOJEK' => 'Gojek',
                    'GREYHOUND' => 'GREYHOUND',
                    'MAZET' => 'Groupe Mazet',
                    'HANJIN' => 'HanJin',
                    'HELLENIC_POST' => 'Hellenic (Greece) Post',
                    'HELLMANN' => 'Hellmann Worldwide Logistics',
                    'HELTHJEM_API' => 'Helthjem',
                    'HERMES_DE_FTP' => 'Hermes Germany',
                    'HERMES_UK_SFTP' => 'Hermes UK',
                    'HERMESWORLD_UK' => 'hermesworld_uk',
                    'HEROEXPRESS' => 'Hero Express',
                    'HFD' => 'HFD',
                    'HK_RPX' => 'hk_rpx',
                    'HOMELOGISTICS' => 'Home Logistics',
                    'HOMERUNNER' => 'HomeRunner',
                    'HERMES_IT' => 'HR Parcel',
                    'HSDEXPRESS' => 'HSDEXPRESS',
                    'HSM_GLOBAL' => 'HSM Global',
                    'HUANTONG' => 'HuanTong',
                    'HUBBED' => 'HUBBED',
                    'HUNTER_EXPRESS_SFTP' => 'Hunter Express',
                    'IBVENTURE_WEBHOOK' => 'IB Venture',
                    'POSTUR_IS' => 'Iceland Post',
                    'ICSCOURIER' => 'ICS COURIER',
                    'IDEXPRESS_ID' => 'iDexpress Indonesia',
                    'IDN_POS' => 'idn_pos',
                    'IDS_LOGISTICS' => 'ids_logistics',
                    'ILYANGLOGIS' => 'Ilyang logistics',
                    'IMEXGLOBALSOLUTIONS' => 'imexglobalsolutions',
                    'IMILE_API' => 'iMile',
                    'IML' => 'IML courier',
                    'IMX' => 'IMX',
                    'INDIA_POST' => 'India Post Domestic',
                    'INDIA_POST_INT' => 'India Post International',
                    'INEXPOST' => 'Inexpost',
                    'INNTRALOG_SFTP' => 'Inntralog GmbH',
                    'INPOST_UK' => 'InPost',
                    'INSTABOX_WEBHOOK' => 'Instabox',
                    'INTERNATIONAL_SEUR_API' => 'International Seur API',
                    'INTERSMARTTRANS' => 'INTERSMARTTRANS & SOLUTIONS SL',
                    'INTEX_DE' => 'INTEX Paketdienst GmbH',
                    'INTIME_FTP' => 'InTime',
                    'ITHINKLOGISTICS' => 'iThink Logistics',
                    'JTCARGO' => 'J&T CARGO',
                    'JTEXPRESS_PH' => 'J&T Express Philippines',
                    'JTEXPRESS_SG_API' => 'J&T Express Singapore',
                    'JT_LOGISTICS' => 'J&T International logistics',
                    'JAVIT' => 'Javit',
                    'CN_JCEX' => 'JCEX courier',
                    'JD_EXPRESS' => 'JD Logistics',
                    'JD_WORLDWIDE' => 'JD Worldwide',
                    'JETSHIP_MY' => 'jetship_my',
                    'JNE_API' => 'JNE (API)',
                    'IDN_JNE' => 'JNE Express (Jalur Nugraha Ekakurir)',
                    'JOYINGBOX' => 'joyingbox',
                    'KARGOMKOLAY' => 'KargomKolay (CargoMini)',
                    'KEDAEX' => 'KedaEX',
                    'HK_TGX' => 'Kerry Express Hong Kong',
                    'KERRY_EXPRESS_TW_API' => 'Kerry Express TaiWan',
                    'THA_KERRY' => 'Kerry Express Thailand',
                    'KERRY_EXPRESS_TH_WEBHOOK' => 'Kerry Logistics',
                    'KNG' => 'Keuhne + Nagel Global',
                    'BE_KIALA' => 'Kiala',
                    'LOGISYSTEMS_SFTP' => 'Kiitääjät',
                    'KOMON_EXPRESS' => 'Komon Express',
                    'KRONOS_WEBHOOK' => 'Kronos Express',
                    'KRONOS' => 'Kronos Express',
                    'KUEHNE' => 'Kuehne + Nagel',
                    'LALAMOVE_API' => 'Lalamove',
                    'LBCEXPRESS_FTP' => 'LBC EXPRESS INC.',
                    'LBCEXPRESS_API' => 'LBC EXPRESS INC.',
                    'LCTBR_API' => 'LCT do Brasil',
                    'LTU_LIETUVOS' => 'Lietuvos pastas',
                    'LINKBRIDGE' => 'Link Bridge(BeiJing)international logistics co.',
                    'LION_PARCEL' => 'LION PARCEL',
                    'LIVRAPIDE' => 'Livrapide',
                    'LOGGI' => 'Loggi',
                    'LOGISTICSWORLDWIDE_KR' => 'LOGISTICSWORLDWIDE KR',
                    'LOGISTICSWORLDWIDE_MY' => 'LOGISTICSWORLDWIDE MY',
                    'LOGWIN_LOGISTICS' => 'Logwin Logistics',
                    'LOGYSTO' => 'Logysto',
                    'LUWJISTIK' => 'Luwjistik',
                    'MX_CARGO' => 'M&X cargo',
                    'M3LOGISTICS' => 'M3 Logistics',
                    'REIMAGINEDELIVERY' => 'maergo',
                    'MAGYAR_POSTA_API' => 'Magyar Posta',
                    'MAIL_BOX_ETC' => 'Mail Boxes Etc.',
                    'MYS_EMS' => 'Malaysia Post EMS / Pos Laju',
                    'MALCA_AMIT_API' => 'Malca Amit',
                    'MALCA_AMIT' => 'Malca-Amit',
                    'MARKEN' => 'Marken',
                    'MEDAFRICA' => 'Med Africa Logistics',
                    'MEEST' => 'Meest',
                    'MEGASAVE' => 'megasave',
                    'MENSAJEROSURBANOS_API' => 'Mensajeros Urbanos',
                    'MWD' => 'Metropolitan Warehouse & Delivery',
                    'MWD_API' => 'Metropolitan Warehouse & Delivery',
                    'MHI' => 'Mhi',
                    'MIKROPAKKET' => 'Mikropakket',
                    'MISUMI_CN' => 'MISUMI Group Inc.',
                    'MNX' => 'MNX',
                    'MOBI_BR' => 'Mobi Logistica',
                    'MONDIALRELAY_FR' => 'Mondial Relay France',
                    'MONDIALRELAY_ES' => 'Mondial Relay Spain(Punto Pack)',
                    'MONDIAL_BE' => 'MONDIAL_BE',
                    'MOOVA' => 'Moova',
                    'MORNINGLOBAL' => 'Morning Global',
                    'MOTHERSHIP_API' => 'Mothership',
                    'MOVIANTO' => 'Movianto',
                    'MUDITA' => 'MUDITA',
                    'MYDYNALOGIC' => 'My DynaLogic',
                    'MYSENDLE_API' => 'mySendle',
                    'NMTRANSFER' => 'N&M Transfer Co., Inc.',
                    'NACEX_SPAIN_REFERENCE' => 'nacex_spain_reference',
                    'NAEKO_FTP' => 'Naeko Logistics',
                    'NAQEL_EXPRESS' => 'Naqel Express',
                    'NEWZEALAND_COURIERS' => 'NEW ZEALAND COURIERS',
                    'NEWGISTICS' => 'Newgistics',
                    'NEWGISTICSAPI' => 'Newgistics API',
                    'NIGHTLINE_UK' => 'nightline_uk',
                    'NIMBUSPOST' => 'NimbusPost',
                    'NIPPON_EXPRESS_FTP' => 'Nippon Express',
                    'NIPPON_EXPRESS' => 'Nippon Express',
                    'NORTHLINE' => 'Northline',
                    'NOVA_POSHTA_API' => 'Nova Poshta API',
                    'NOVOFARMA_WEBHOOK' => 'Novofarma',
                    'NTL' => 'NTL logistics',
                    'NYTLOGISTICS' => 'NYT SUPPLY CHAIN LOGISTICS Co., LTD',
                    'OHI_WEBHOOK' => 'Ohi',
                    'SHOPOLIVE' => 'Olive',
                    'OMLOGISTICS_API' => 'OM LOGISTICS LTD',
                    'OMNIRPS_WEBHOOK' => 'Omni Returns',
                    'ONTRAC' => 'ONTRAC',
                    'ORANGECONNEX' => 'orangeconnex',
                    'ORANGE_DS' => 'OrangeDS (Orange Distribution Solutions Inc)',
                    'OSM_WORLDWIDE_SFTP' => 'OSM Worldwide',
                    'OZEPARTS_SHIPPING' => 'Ozeparts Shipping',
                    'P2P_TRC' => 'P2P TrakPak',
                    'PACKETA' => 'Packeta',
                    'PACKFLEET' => 'PACKFLEET',
                    'PACKS' => 'Packs',
                    'PAKAJO' => 'Pakajo World',
                    'PANDAGO_API' => 'Pandago',
                    'PANDION' => 'Pandion',
                    'PANDU' => 'PANDU',
                    'PANTHER_REFERENCE_API' => 'Panther Reference',
                    'PANTHER_ORDER_NUMBER' => 'panther_order_number',
                    'PAPA_WEBHOOK' => 'Papa',
                    'PARCELRIGHT' => 'Parcel Right',
                    'PARCEL_2_POST' => 'Parcel To Post',
                    'PARCELFORCE' => 'PARCELFORCE',
                    'PARCELSTARS_WEBHOOK' => 'Parcelstars',
                    'PARCLL' => 'PARCLL',
                    'PASSPORTSHIPPING' => 'Passport Shipping',
                    'PATHEON' => 'Patheon Logistics',
                    'PAYO' => 'Payo',
                    'PCHOME_API' => 'Pchome Express',
                    'PGEON_API' => 'Pgeon',
                    'PHSE_API' => 'PHSE',
                    'PICKUPP_VNM' => 'pickupp_vnm',
                    'PIDGE' => 'Pidge',
                    'PIL_LOGISTICS' => 'PIL Logistics (China) Co.',
                    'PLYCONGROUP' => 'Plycon Transportation Group',
                    'POLARSPEED' => 'PolarSpeed Inc',
                    'POSTONE' => 'Post ONE',
                    'POSTAPLUS' => 'Posta Plus',
                    'POSTE_ITALIANE_PACCOCELERE' => 'Poste Italiane Paccocelere',
                    'POSTEN_NORGE' => 'Posten Norge (www.posten.no)',
                    'POSTI_API' => 'Posti API',
                    'POSTNL_INT_3_S' => 'PostNL International',
                    'NLD_POSTNL' => 'PostNL International',
                    'POSTNL_INTERNATIONAL' => 'PostNL International',
                    'SWE_POSTNORD' => 'Postnord sweden',
                    'POSTPLUS' => 'PostPlus',
                    'PROCARRIER' => 'Pro Carrier',
                    'PRODUCTCAREGROUP_SFTP' => 'Product Care Services Limited',
                    'PROFESSIONAL_COURIERS' => 'PROFESSIONAL COURIERS',
                    'PPL' => 'Professional Parcel Logistics',
                    'PROMEDDELIVERY' => 'ProMed Delivery',
                    'PUROLATOR' => 'purolator',
                    'PUROLATOR_INTERNATIONAL' => 'Purolator International',
                    'QTRACK' => 'QTrack',
                    'QUALITYPOST' => 'qualitypost',
                    'QINTL_API' => 'Quickstat Courier LLC',
                    'QUIQUP' => 'Quiqup',
                    'RANSA_WEBHOOK' => 'Ransa',
                    'REDJEPAKKETJE' => 'Red je Pakketje',
                    'RELAISCOLIS' => 'Relais Colis',
                    'RHENUS_GROUP' => 'Rhenus Logistics',
                    'RHENUS_UK_API' => 'Rhenus Logistics UK',
                    'AIR_CANADA' => 'Rivo',
                    'RIXONHK_API' => 'Rixon Logistics',
                    'ROCHE_INTERNAL_SFTP' => 'Roche Internal Courier',
                    'ROYAL_MAIL_FTP' => 'Royal Mail',
                    'ROYALSHIPMENTS' => 'royalshipments',
                    'RRDONNELLEY' => 'rrdonnelley',
                    'RUSSIAN_POST' => 'Russian post',
                    'SAEE' => 'saee',
                    'SAGAWA' => 'SAGAWA',
                    'SAGAWA_API' => 'Sagawa',
                    'SBERLOGISTICS_RU' => 'Sber Logistics',
                    'SECRETLAB_WEBHOOK' => 'Secretlab',
                    'SEINO_API' => 'Seino',
                    'SEKO_SFTP' => 'SEKO Worldwide',
                    'SENDING' => 'Sending Transporte Urgente y Comunicacion',
                    'SHOWL' => 'SENHONG INTERNATIONAL LOGISTICS',
                    'NOWLOG_API' => 'Sequoialog',
                    'SERVIENTREGA' => 'Servientrega',
                    'SERVIP_WEBHOOK' => 'SerVIP',
                    'SETEL' => 'Setel Express',
                    'SF_EX' => 'SF Express',
                    'SF_EXPRESS_CN' => 'SF Express China',
                    'SGT_IT' => 'SGT_IT',
                    'SHADOWFAX' => 'Shadowfax',
                    'SHENZHEN' => 'shenzhen 1st International Logistics(Group)Co',
                    'HOTSIN_CARGO' => 'SHENZHEN HOTSIN CARGO INTL FORWARDING CO., LTD',
                    'KWT' => 'Shenzhen Jinghuada Logistics Co.',
                    'SHERPA' => 'Sherpa',
                    'SHIPA' => 'SHIPA',
                    'SHIPPIE' => 'Shippie',
                    'SHIPPIFY' => 'Shippify, Inc',
                    'SHIPROCKET' => 'Shiprocket X',
                    'SHIPX' => 'ShipX',
                    'SHIPXPRES' => 'SHIPXPRESS',
                    'SPX' => 'Shopee Express',
                    'SPX_TH' => 'Shopee Xpress',
                    'SHUNBANG_EXPRESS' => 'ShunBang Express',
                    'SHYPLITE' => 'Shypmax',
                    'SIMPLETIRE_WEBHOOK' => 'SimpleTire',
                    'SIMSGLOBAL' => 'Sims Global',
                    'SIODEMKA' => 'SIODEMKA',
                    'SKynet_WORLDWIDE' => 'SkyNet Worldwide Express',
                    'SKY_POSTAL' => 'SkyPostal',
                    'SK_POSTA' => 'Slovenska pošta',
                    'SMARTCAT' => 'SMARTCAT',
                    'SMARTKARGO' => 'SmartKargo',
                    'SMG_EXPRESS' => 'SMG Direct',
                    'SMSA_EXPRESS_WEBHOOK' => 'SMSA Express',
                    'SNTGLOBAL_API' => 'Snt Global Etrax',
                    'SOLISTICA_API' => 'solistica',
                    'SPANISH_SEUR_FTP' => 'Spanish Seur',
                    'SPECTRAN' => 'Spectran',
                    'SPEEDEX' => 'speedex',
                    'SPEEDY' => 'Speedy',
                    'SPREETAIL_API' => 'Spreetail',
                    'SPRINT_PACK' => 'SPRINT PACK',
                    'SRT_TRANSPORT' => 'SRT Transport',
                    'STAR_TRACK_NEXT_FLIGHT' => 'Star Track Next Flight',
                    'STARLINKS_API' => 'Starlinks Global',
                    'STARTRACK' => 'startrack',
                    'STAR_TRACK_WEBHOOK' => 'StarTrack',
                    'STARTRACK_EXPRESS' => 'startrack_express',
                    'STATOVERNIGHT' => 'Stat Overnight',
                    'CN_STO' => 'STO Express',
                    'SWISHIP' => 'Swiship',
                    'SWISS_POST' => 'SWISS POST',
                    'T_CAT' => 'T-cat',
                    'T_CAT_API' => 'T-cat',
                    'LOGINEXT_WEBHOOK' => 'T&W Delivery',
                    'TW_TAIWAN_POST' => 'Taiwan Post',
                    'TAMERGROUP_WEBHOOK' => 'Tamer Logistics',
                    'TAQBIN_HK' => 'TAQBIN Hong Kong',
                    'TAQBIN_SG' => 'taqbin_sg',
                    'TCS_API' => 'TCS',
                    'TECOR' => 'tecor',
                    'TELEPORT_WEBHOOK' => 'Teleport',
                    'SIC_TELIWAY' => 'Teliway SIC Express',
                    'TESTING_COURIER_WEBHOOK' => 'Testing Courier',
                    'TESTING_COURIER' => 'Testing Courier',
                    'TH_CJ' => 'TH_CJ',
                    'THIJS_NL' => 'Thijs Logistiek',
                    'THUNDEREXPRESS' => 'Thunder Express Australia',
                    'TIPSA_API' => 'Tipsa API',
                    'TIPSA_REF' => 'Tipsa Reference',
                    'TNT_FR_REFERENCE' => 'TNT France Reference',
                    'TNT_REFR' => 'TNT Reference',
                    'TNT_AU' => 'tnt_au',
                    'TNT_CN' => 'TNT_CN',
                    'TNT_DE' => 'TNT_DE',
                    'TNT_ES' => 'TNT_ES',
                    'TNT_IT' => 'tnt_it',
                    'TNT_JP' => 'TNT_JP',
                    'TNT_PL' => 'TNT_PL',
                    'TOLL_WEBHOOK' => 'Toll Group',
                    'TOLL_IPEC' => 'TOLL IPEC',
                    'TOLL_PRIORITY' => 'Toll Priority',
                    'TOMYDOOR' => 'Tomydoor',
                    'TONAMI_FTP' => 'Tonami',
                    'ESDEX' => 'Top Ideal Express',
                    'TOTAL_EXPRESS_API' => 'Total Express',
                    'TOURLINE_REFERENCE' => 'Tourline Express',
                    'THAIPARCELS' => 'TP Logistic',
                    'TRANS2U' => 'Trans2u',
                    'TRANSMISSION' => 'TRANSMISSION',
                    'TANET' => 'Transport Ambientales',
                    'TRANSVIRTUAL' => 'TransVirtual',
                    'TRUNKRS' => 'Trunkrs',
                    'TRUSK' => 'Trusk France',
                    'TUSKLOGISTICS' => 'Tusk Logistics',
                    'TYP' => 'TYP',
                    'U_ENVIOS' => 'U-ENVIOS',
                    'UBER_WEBHOOK' => 'Uber',
                    'UCS' => 'UCS',
                    'UDS' => 'United Delivery Service',
                    'UPS' => 'United Parcel Service',
                    'UP_EXPRESS' => 'up_express',
                    'UPARCEL' => 'uParcel',
                    'UPS_API' => 'UPS',
                    'UPS_FREIGHT' => 'UPS Freight',
                    'UPS_REFERENCE' => 'UPS Reference',
                    'URGENT_CARGUS' => 'Urgent Cargus',
                    'US_APC' => 'us_apc',
                    'USPS_API' => 'USPS API',
                    'PB_USPSFLATS_FTP' => 'USPS Flats (Pitney Bowes)',
                    'USPS_WEBHOOK' => 'USPS Informed Visibility - Webhook',
                    'VALUE_WEBHOOK' => 'Value Logistics',
                    'VIAXPRESS' => 'ViaXpress',
                    'VNPOST_API' => 'Vietnam Post',
                    'VIRTRANSPORT_SFTP' => 'Vir Transport',
                    'VNPOST_EMS' => 'vnpost_ems',
                    'VOX' => 'VOX SOLUCION EMPRESARIAL SRL',
                    'WATKINS_SHEPARD' => 'watkins_shepard',
                    'WEWORLDEXPRESS' => 'We World Express',
                    'WESHIP_API' => 'WeShip',
                    'WESHIP' => 'WeShip',
                    'WHISTL_SFTP' => 'Whistl',
                    'WINESHIPPING_WEBHOOK' => 'Wineshipping',
                    'WISH_EMAIL_PUSH' => 'Wish',
                    'WOOYOUNG_LOGISTICS_SFTP' => 'WOO YOUNG LOGISTICS CO., LTD.',
                    'WORLDCOURIER' => 'World Courier',
                    'WORLDNET' => 'Worldnet Logistics',
                    'WSPEXPRESS' => 'WSP Express',
                    'XYY' => 'Xingyunyi Logistics',
                    'XPEDIGO' => 'Xpedigo',
                    'XPRESSBEES' => 'XPRESSBEES',
                    'YAMATO' => 'YAMATO',
                    'TAQBIN_SG_API' => 'Yamato Singapore',
                    'YIFAN' => 'YiFan Express',
                    'YODEL' => 'yodel',
                    'YODEL_API' => 'Yodel API',
                    'YODEL_DIR' => 'Yodel Direct',
                    'YODEL_INTNL' => 'Yodel International',
                    'YUSEN' => 'Yusen Logistics',
                    'YUSEN_SFTP' => 'Yusen Logistics',
                    'YYCOM' => 'yycom',
                    'YYEXPRESS' => 'YYEXPRESS',
                    'ZTO_DOMESTIC' => 'ZTO Express China',
                    'ZUELLIGPHARMA_SFTP' => 'Zuellig Pharma Korea',
                ),
            ),
            'AG' => array(
                'name' => 'Argentina',
                'items' => array(
                    'FASTRACK' => 'Fasttrack',
                    'ANDREANI' => 'Grupo logistico Andreani',
                    'ARG_OCA' => 'OCA Argentina',
                ),
            ),
            'AU' => array(
                'name' => 'Australia',
                'items' => array(
                    'ADSONE' => 'Adsone',
                    'ALLIEDEXPRESS' => 'Allied Express',
                    'ARAMEX_AU' => 'Aramex Australia (formerly Fastway AU)',
                    'AU_AU_POST' => 'Australia Post',
                    'BLUESTAR' => 'Blue Star',
                    'BONDSCOURIERS' => 'Bonds Courier Service (bondscouriers.com.au)',
                    'BORDEREXPRESS' => 'Border Express',
                    'COPE' => 'Cope Sensitive Freight',
                    'COURIERS_PLEASE' => 'CouriersPlease (couriersplease.com.au)',
                    'DELIVERE' => 'deliverE',
                    'DESIGNERTRANSPORT_WEBHOOK' => 'Designer Transport',
                    'DHL_AU' => 'DHL Supply Chain Australia',
                    'DIRECTCOURIERS' => 'Direct Couriers',
                    'DTDC_AU' => 'DTDC Australia',
                    'ENDEAVOUR_DELIVERY' => 'Endeavour Delivery',
                    'HUNTER_EXPRESS' => 'Hunter Express',
                    'ICUMULUS' => 'iCumulus',
                    'INTERPARCEL_AU' => 'Interparcel Australia',
                    'NEWAY' => 'Neway Transport',
                    'PARCELPOINT' => 'Parcelpoint',
                    'PFLOGISTICS' => 'PFL',
                    'SENDLE' => 'Sendle',
                    'SHIPPIT' => 'Shippit',
                    'THENILE_WEBHOOK' => 'SortHub courier',
                    'STAR_TRACK_EXPRESS' => 'Star Track Express',
                    'AUS_STARTRACK' => 'StarTrack (startrack.com.au)',
                    'TFM' => 'TFM Xpress',
                    'TIGFREIGHT' => 'TIG Freight',
                    'TOLL' => 'Toll IPEC',
                    'UBI_LOGISTICS' => 'UBI Smart Parcel',
                    'XL_EXPRESS' => 'XL Express',
                ),
            ),
            'AT' => array(
                'name' => 'Austria',
                'items' => array(
                    'AUSTRIAN_POST_EXPRESS' => 'Austrian Post',
                    'AU_AUSTRIAN_POST' => 'Austrian Post (Registered)',
                ),
            ),
            'BGD' => array(
                'name' => 'Bangladesh',
                'items' => array(
                    'PAPERFLY' => 'Paperfly Private Limited',
                ),
            ),
            'BE' => array(
                'name' => 'Belgium',
                'items' => array(
                    'B_TWO_C_EUROPE' => 'B2C courier Europe',
                    'DHL_BENELUX' => 'dhl benelux',
                    'BEL_DHL' => 'DHL Benelux',
                    'LANDMARK_GLOBAL' => 'Landmark Global',
                    'LANDMARK_GLOBAL_REFERENCE' => 'Landmark Global Reference',
                    'MIKROPAKKET_BE' => 'Mikropakket Belgium',
                ),
            ),
            'BIH' => array(
                'name' => 'Bosnia and Herzegovina',
                'items' => array(
                    'BH_POSTA' => 'BH Posta (www.posta.ba)',
                ),
            ),
            'BR' => array(
                'name' => 'Brazil',
                'items' => array(
                    'BRA_CORREIOS' => 'Correios Brazil',
                    'DIRECTLOG' => 'Directlog',
                    'FRETERAPIDO' => 'Frete Rapido',
                    'INTELIPOST' => 'Intelipost',
                    'TOTAL_EXPRESS' => 'Total Express',
                ),
            ),
            'BG' => array(
                'name' => 'Bulgaria',
                'items' => array(
                    'A1POST' => 'A1Post',
                    'BG_BULGARIAN_POST' => 'Bulgarian Posts',
                ),
            ),
            'KHM' => array(
                'name' => 'Cambodia',
                'items' => array(
                    'AFLLOG_FTP' => 'AFL LOGISTICS',
                    'KHM_CAMBODIA_POST' => 'Cambodia Post',
                    'ROADRUNNER_FREIGHT' => 'Roadbull Logistics',
                ),
            ),
            'CA' => array(
                'name' => 'Canada',
                'items' => array(
                    'CA_CANADA_POST' => 'Canada Post',
                    'CHITCHATS' => 'Chit Chats',
                    'CORPORATECOURIERS_WEBHOOK' => 'Corporate Couriers',
                    'COURANT_PLUS' => 'Courant Plus',
                    'GLOBAL_ESTES' => 'Estes Express Lines',
                    'DICOM' => 'GLS Logistic Systems Canada Ltd./Dicom',
                    'LOCUS_WEBHOOK' => 'Locus courier',
                    'LOOMIS_EXPRESS' => 'Loomis Express',
                    'MBW' => 'MBW Courier Inc.',
                    'NATIONEX' => 'Nationex courier',
                    'PARCELPAL_WEBHOOK' => 'ParcelPal',
                    'AIR_CANADA_GLOBAL' => 'Rivo (Air canada)',
                    'ROUTIFIC_WEBHOOK' => 'Routific',
                    'RPXLOGISTICS' => 'RPX Logistics',
                    'STALLIONEXPRESS' => 'Stallion Express',
                    'ZIINGFINALMILE' => 'Ziing Final Mile Inc',
                ),
            ),
            'CL' => array(
                'name' => 'Chile',
                'items' => array(
                    'BLUEX' => 'Blue Express',
                    'STARKEN' => 'STARKEN couriers',
                ),
            ),
            'CN' => array(
                'name' => 'China',
                'items' => array(
                    'CN_17POST' => '17 Post Service',
                    'ACSWORLDWIDE' => 'ACS Worldwide Express',
                    'CAINIAO' => 'AliExpress Standard Shipping',
                    'ANJUN' => 'Anjun couriers',
                    'ANSERX' => 'ANSERX courier',
                    'AUPOST_CN' => 'AuPost China',
                    'BEL_RS' => 'BEL North Russia',
                    'CN_BESTEXPRESS' => 'Best Express',
                    'CN_BOXC' => 'BoxC courier',
                    'BUYLOGIC' => 'buylogic',
                    'CPEX' => 'Captain Express International',
                    'CGS_EXPRESS' => 'CGS Express',
                    'CN_CHINA_POST_EMS' => 'China Post',
                    'CHUKOU1' => 'Chukou1',
                    'CJPACKET' => 'CJ Packet',
                    'CLEVY_LINKS' => 'Clevy Links',
                    'CNDEXPRESS' => 'CND Express',
                    'CNEXPS' => 'CNE Express',
                    'COMET_TECH' => 'CometTech',
                    'CPACKET' => 'Cpacket couriers',
                    'CUCKOOEXPRESS' => 'Cuckoo Express',
                    'DEX_I' => 'DEX-I courier',
                    'DIDADI' => 'DIDADI Logistics tech',
                    'DPE_EXPRESS' => 'DPE Express',
                    'DTD_EXPR' => 'DTD Express',
                    'EMPS_CN' => 'EMPS Express',
                    'CN_EQUICK' => 'Equick China',
                    'ESHIPPING' => 'Eshipping',
                    'ZES_EXPRESS' => 'Eshun international Logistic',
                    'FAR_INTERNATIONAL' => 'Far international',
                    'FARGOOD' => 'FarGood',
                    'FULFILLME' => 'Fulfillme',
                    'GANGBAO' => 'GANGBAO Supplychain',
                    'GESWL' => 'GESWL Express',
                    'CN_GOFLY' => 'GoFly',
                    'HDB' => 'Haidaibao',
                    'HDB_BOX' => 'Haidaibao (BOX)',
                    'HH_EXP' => 'Hua Han Logistics',
                    'HUAHAN_EXPRESS' => 'HUAHANG EXPRESS',
                    'HUODULL' => 'Huodull',
                    'HX_EXPRESS' => 'HX Express',
                    'IDEXPRESS' => 'IDEX courier',
                    'INTEL_VALLEY' => 'Intel-Valley Supply chain (ShenZhen) Co. Ltd',
                    'J_NET' => 'J-Net',
                    'JINDOUYUN' => 'jindouyun courier',
                    'JOOM_LOGIS' => 'Joom Logistics',
                    'JOYING_BOX' => 'Joying Box',
                    'K1_EXPRESS' => 'K1 Express',
                    'KY_EXPRESS' => 'Kua Yue Express',
                    'LALAMOVE' => 'Lalamove',
                    'LEADER' => 'leader',
                    'SDH_SCM' => 'lightning monkey',
                    'LOGISTERS' => 'Logisters',
                    'LTIANEXP' => 'LTIAN EXP',
                    'LTL' => 'LTL COURIER',
                    'MORE_LINK' => 'Morelink',
                    'MXE' => 'MXE Express',
                    'NANJINGWOYUAN' => 'Nanjing Woyuan',
                    'ONEWORLDEXPRESS' => 'One World Express',
                    'PADTF' => 'padtf.com',
                    'PAGO' => 'Pago Logistics',
                    'PAN_ASIA' => 'Pan-Asia International',
                    'CN_PAYPAL_PACKAGE' => 'PayPal Package',
                    'PFCEXPRESS' => 'PFC Express',
                    'CN_POST56' => 'Post56',
                    'HKD' => 'Qingdao HKD International Logistics',
                    'ETS_EXPRESS' => 'RETS express',
                    'RUSTON' => 'Ruston',
                    'CN_SF_EXPRESS' => 'SF Express (www.sf-express.com)',
                    'SFB2C' => 'SF International',
                    'SFC_LOGISTICS' => 'SFC',
                    'SFCSERVICE' => 'SFC Service',
                    'DAJIN' => 'Shanghai Aqrum Chemical Logistics Co.Ltd',
                    'SINOTRANS' => 'Sinotrans',
                    'STONE3PL' => 'STONE3PL',
                    'SYPOST' => 'Sunyou Post',
                    'TARRIVE' => 'TONDA GLOBAL',
                    'TOPHATTEREXPRESS' => 'Tophatter Express',
                    'TOPYOU' => 'TopYou',
                    'UC_EXPRE' => 'ucexpress',
                    'VIWO' => 'VIWO IoT',
                    'WANBEXPRESS' => 'WanbExpress',
                    'WEASHIP' => 'Weaship',
                    'CN_WEDO' => 'WeDo Logistics',
                    'WINIT' => 'WinIt',
                    'WISE_EXPRESS' => 'Wise Express',
                    'CN_WISHPOST' => 'WishPost',
                    'XQ_EXPRESS' => 'XQ Express',
                    'YANWEN' => 'Yanwen Logistics',
                    'YDH_EXPRESS' => 'YDH express',
                    'ELIAN_POST' => 'Yilian (Elian) Supply Chain',
                    'YINGNUO_LOGISTICS' => 'yingnuo logistics',
                    'YTO' => 'YTO Express',
                    'CN_YUNDA' => 'Yunda Express',
                    'YUNEXPRESS' => 'YunExpress',
                    'ZJS_EXPRESS' => 'ZJS International',
                    'ZTO_EXPRESS' => 'ZTO Express',
                ),
            ),
            'COL' => array(
                'name' => 'Colombia',
                'items' => array(
                    'COORDINADORA' => 'Coordinadora',
                ),
            ),
            'HRV' => array(
                'name' => 'Croatia',
                'items' => array(
                    'GLS_CROTIA' => 'GLS Croatia',
                    'HRV_HRVATSKA' => 'Hrvatska posta',
                    'OVERSE_EXP' => 'Overseas Express',
                ),
            ),
            'CY' => array(
                'name' => 'Cyprus',
                'items' => array(
                    'CYPRUS_POST_CYP' => 'Cyprus Post',
                ),
            ),
            'CZ' => array(
                'name' => 'Czech Republic',
                'items' => array(
                    'CESKA_CZ' => 'Ceska Post',
                    'GLS_CZ' => 'GLS Czech Republic',
                ),
            ),
            'DNK' => array(
                'name' => 'Denmark',
                'items' => array(
                    'BUDBEE_WEBHOOK' => 'Budbee courier',
                    'DANSKE_FRAGT' => 'Danske Fragtaend',
                    'POSTNORD_LOGISTICS_DK' => 'ostnord denmark',
                    'POSTNORD_LOGISTICS' => 'PostNord Logistics',
                    'XPRESSEN_DK' => 'Xpressen courier',
                ),
            ),
            'EST' => array(
                'name' => 'Estonia',
                'items' => array(
                    'OMNIVA' => 'Omniva',
                ),
            ),
            'FIN' => array(
                'name' => 'Finland',
                'items' => array(
                    'MATKAHUOLTO' => 'Matkahuolto',
                    'POSTI' => 'Posti courier',
                ),
            ),
            'FR' => array(
                'name' => 'France',
                'items' => array(
                    'CHRONOPOST_FR' => 'Chronopost france (www.chronopost.fr)',
                    'COLIS_PRIVE' => 'Colis Privé',
                    'FR_COLIS' => 'Colissimo',
                    'CUBYN' => 'Cubyn',
                    'DPD_FR' => 'DPD France',
                    'FR_EXAPAQ' => 'DPD France (formerly exapaq)',
                    'GEODIS_ESPACE' => 'Geodis E-space',
                    'HEPPNER_FR' => 'Heppner France',
                    'LA_POSTE_SUIVI' => 'La Poste',
                    'TNT_FR' => 'TNT France',
                    'VIRTRANSPORT' => 'VIR Transport',
                ),
            ),
            'DE' => array(
                'name' => 'Germany',
                'items' => array(
                    'HERMES_DE' => 'Hermes Germany',
                    'AO_DEUTSCHLAND' => 'AO Deutschland',
                    'DE_DPD_DELISTRACK' => 'DPD Germany',
                    'FIEGE' => 'Fiege Logistics',
                    'GEIS' => 'Geis CZ',
                    'GEL_EXPRESS' => 'Gel Express Logistik',
                    'GENERAL_OVERNIGHT' => 'Go!Express and logistics',
                    'HEPPNER' => 'Heppner Internationale Spedition GmbH & Co.',
                    'HERMES_2MANN_HANDLING' => 'Hermes Einrichtungs Service GmbH & Co. KG',
                    'NOX_NACHTEXPRESS' => 'Innight Express Germany GmbH (nox NachtExpress)',
                    'LIEFERY' => 'liefery',
                    'NOX_NIGHT_TIME_EXPRESS' => 'NOX NightTimeExpress',
                    'PARCELONE' => 'PARCEL ONE',
                    'PRESSIODE' => 'Pressio',
                    'RABEN_GROUP' => 'Raben Group',
                    'STRECK_TRANSPORT' => 'Streck Transport',
                    'SWISHIP_DE' => 'Swiship DE',
                ),
            ),
            'GR' => array(
                'name' => 'Greece',
                'items' => array(
                    'ACS_GR' => 'ACS Courier',
                    'EASY_MAIL' => 'Easy Mail',
                    'GENIKI_GR' => 'Geniki Taxydromiki',
                    'SPEEDCOURIERS_GR' => 'Speed Couriers',
                    'SPEEDEXCOURIER' => 'SPEEDEX couriers',
                ),
            ),
            'HK' => array(
                'name' => 'Hong Kong',
                'items' => array(
                    'CFL_LOGISTICS' => 'CFL Logistics',
                    'CJ_HK_INTERNATIONAL' => 'CJ Logistics International(Hong Kong)',
                    'CLE_LOGISTICS' => 'CL E-Logistics Solutions Limited',
                    'CONTINENTAL' => 'Continental',
                    'COSTMETICSNOW' => 'Cosmetics Now',
                    'DEALERSEND' => 'DealerSend',
                    'DHL_ECOMERCE_ASA' => 'DHL eCommerce Asia (API)',
                    'DHL_GLOBAL_MAIL_ASIA' => 'DHL Global Mail Asia',
                    'DHL_HK' => 'DHL Hong Kong',
                    'DPD_HK' => 'DPD Hong Kong',
                    'DTDC_EXPRESS' => 'DTDC express',
                    'GLOBAVEND' => 'Globavend',
                    'HK_POST' => 'Hongkong Post',
                    'JANCO' => 'Janco Ecommerce',
                    'JS_EXPRESS' => 'JS EXPRESS',
                    'KEC' => 'KEC courier',
                    'KERRY_ECOMMERCE' => 'Kerry eCommerce',
                    'LHT_EXPRESS' => 'LHT Express',
                    'LOGISTICSWORLDWIDE_HK' => 'Logistic Worldwide Express',
                    'MAINWAY' => 'Mainway',
                    'MORNING_EXPRESS' => 'Morning Express',
                    'OKAYPARCEL' => 'OkayParcel',
                    'OMNIPARCEL' => 'Omni Parcel',
                    'PALEXPRESS' => 'PAL Express Limited',
                    'PICKUP' => 'Pickupp',
                    'QUANTIUM' => 'Quantium',
                    'RPX' => 'RPX Online',
                    'SEKOLOGISTICS' => 'SEKO Logistics',
                    'SHIP_IT_ASIA' => 'Ship It Asia',
                    'SMOOTH' => 'Smooth Couriers',
                    'STEPFORWARDFS' => 'STEP FORWARD FREIGHT SERVICE CO LTD',
                    'SFPLUS_WEBHOOK' => 'Zeek courier',
                    'ZEEK_2_DOOR' => 'Zeek2Door',
                ),
            ),
            'HU' => array(
                'name' => 'Hungary',
                'items' => array(
                    'DPD_HGRY' => 'DPD Hungary',
                    'MAGYAR_HU' => 'Magyar Post',
                ),
            ),
            'IN' => array(
                'name' => 'India',
                'items' => array(
                    'BOMBINOEXP' => 'Bombino Express Pvt',
                    'IND_DELHIVERY' => 'Delhivery India',
                    'DELIVERYONTIME' => 'DELIVERYONTIME LOGISTICS PVT LTD',
                    'DTDC_IN' => 'DTDC India',
                    'IND_ECOM' => 'Ecom Express',
                    'EKART' => 'Ekart logistics (ekartlogistics.com)',
                    'IND_FIRSTFLIGHT' => 'First Flight Couriers',
                    'IND_GATI' => 'Gati-KWE',
                    'IND_GOJAVAS' => 'GoJavas',
                    'HOLISOL' => 'Holisol',
                    'LEXSHIP' => 'LexShip',
                    'OCS' => 'OCS ANA Group',
                    'PARCELLED_IN' => 'Parcelled.in',
                    'PICKRR' => 'Pickrr',
                    'IND_SAFEEXPRESS' => 'Safexpress',
                    'SCUDEX_EXPRESS' => 'Scudex Express',
                    'SHREE_ANJANI_COURIER' => 'Shree Anjani Courier',
                    'SHREE_MARUTI' => 'Shree Maruti Courier Services Pvt Ltd',
                    'SHREENANDANCOURIER' => 'SHREE NANDAN COURIER',
                    'SHREETIRUPATI' => 'SHREE TIRUPATI COURIER SERVICES PVT. LTD.',
                    'SPOTON' => 'SPOTON Logistics Pvt Ltd',
                    'TRACKON' => 'Trackon Couriers Pvt. Ltd',
                ),
            ),
            'ID' => array(
                'name' => 'Indonesia',
                'items' => array(
                    'ALFATREX' => 'AlfaTrex',
                    'CHOIR_EXP' => 'Choir Express Indonesia',
                    'INDOPAKET' => 'INDOPAKET',
                    'JX' => 'JX courier',
                    'KURASI' => 'KURASI',
                    'NINJAVAN_ID' => 'Ninja Van Indonesia',
                    'NINJAVAN_WB' => 'Ninjavan Webhook',
                    'MGLOBAL' => 'PT MGLOBAL LOGISTICS INDONESIA',
                    'PRIMAMULTICIPTA' => 'PT Prima Multi Cipta',
                    'RCL' => 'Red Carpet Logistics',
                    'RPX_ID' => 'RPX Indonesia',
                    'SAP_EXPRESS' => 'SAP EXPRESS',
                    'SIN_GLBL' => 'Sin Global Express',
                    'TIKI_ID' => 'Tiki shipment',
                    'TRANS_KARGO' => 'Trans Kargo Internasional',
                    'WAHANA_ID' => 'Wahana express (www.wahana.com)',
                ),
            ),
            'IE' => array(
                'name' => 'Ireland',
                'items' => array(
                    'AN_POST' => 'An Post',
                    'DPD_IR' => 'DPD Ireland',
                    'FASTWAY_IR' => 'Fastway Ireland',
                    'WISELOADS' => 'Wiseloads',
                ),
            ),
            'IL' => array(
                'name' => 'Israel',
                'items' => array(
                    'ISRAEL_POST' => 'Israel Post',
                    'ISR_POST_DOMESTIC' => 'Israel Post Domestic',
                ),
            ),
            'IT' => array(
                'name' => 'Italy',
                'items' => array(
                    'BRT_IT_PARCELID' => 'BRT Bartolini (Parcel ID)',
                    'BRT_IT' => 'BRT Couriers Italy',
                    'ARCO_SPEDIZIONI' => 'Arco Spedizioni SP',
                    'BLINKLASTMILE' => 'Blink',
                    'BRT_IT_SENDER_REF' => 'BRT Bartolini (Sender Reference)',
                    'DMM_NETWORK' => 'DMM Network',
                    'GLS_IT' => 'GLS Italy',
                    'HRPARCEL' => 'HR Parcel',
                    'I_DIKA' => 'i-dika',
                    'LICCARDI_EXPRESS' => 'LICCARDI EXPRESS COURIER',
                    'MILKMAN' => 'Milkman Courier',
                    'IT_NEXIVE' => 'Nexive (TNT Post Italy)',
                    'IT_POSTE_ITALIA' => 'Poste Italiane',
                    'SAILPOST' => 'SAILPOST',
                    'SDA_IT' => 'SDA Italy',
                    'TNT_CLICK_IT' => 'TNT-Click Italy',
                ),
            ),
            'JP' => array(
                'name' => 'Japan',
                'items' => array(
                    'EFEX' => 'eFEx (E-Commerce Fulfillment & Express)',
                    'JPN_JAPAN_POST' => 'Japan Post',
                    'KWE_GLOBAL' => 'KWE Global',
                    'MAIL_PLUS' => 'MailPlus',
                    'MAILPLUS_JPN' => 'MailPlus (Japan)',
                    'SEINO' => 'Seino',
                ),
            ),
            'JEY' => array(
                'name' => 'Jersey',
                'items' => array(
                    'JERSEY_POST' => 'Jersey Post',
                ),
            ),
            'KR' => array(
                'name' => 'Korea',
                'items' => array(
                    'CELLO_SQUARE' => 'Cello Square',
                    'CROSHOT' => 'Croshot',
                    'DOORA' => 'Doora Logistics',
                    'EPARCEL_KR' => 'eParcel Korea',
                    'KPOST' => 'Korea Post',
                    'KR_KOREA_POST' => 'Koreapost (www.koreapost.go.kr)',
                    'KYUNGDONG_PARCEL' => 'Kyungdong Parcel',
                    'LOTTE' => 'Lotte Global Logistics',
                    'RINCOS' => 'Rincos',
                    'ROCKET_PARCEL' => 'Rocket Parcel International',
                    'SHIP_GATE' => 'ShipGate',
                    'SHIPTER' => 'SHIPTER',
                    'SRE_KOREA' => 'SRE Korea (www.srekorea.co.kr)',
                    'TOLOS' => 'Tolos courier',
                ),
            ),
            'KWT' => array(
                'name' => 'Kuwait',
                'items' => array(
                    'POSTA_PLUS' => 'Posta Plus',
                ),
            ),
            'LAO' => array(
                'name' => "Lao People's Democratic Republic (the)",
                'items' => array(
                    'LAO_POST' => 'Lao Post',
                ),
            ),
            'LVA' => array(
                'name' => 'Latvia',
                'items' => array(
                    'CDEK' => 'CDEK courier',
                    'LATVIJAS_PASTS' => 'Latvijas Pasts',
                ),
            ),
            'LT' => array(
                'name' => 'Lithuania',
                'items' => array(
                    'VENIPAK' => 'Venipak',
                ),
            ),
            'MY' => array(
                'name' => 'Malaysia',
                'items' => array(
                    'ABXEXPRESS_MY' => 'ABX Express',
                    'MYS_AIRPAK' => 'Airpak Express',
                    'CITYLINK_MY' => 'City-Link Express',
                    'CJ_CENTURY' => 'CJ Century',
                    'CJ_INT_MY' => 'CJ International Malaysia',
                    'COLLECTCO' => 'CollectCo',
                    'FMX' => 'FMX',
                    'MYS_GDEX' => 'GDEX Courier',
                    'JTEXPRESS' => 'J&T EXPRESS MALAYSIA',
                    'JINSUNG' => 'JINSUNG TRADING',
                    'JOCOM' => 'Jocom',
                    'KANGAROO_MY' => 'Kangaroo Worldwide Express',
                    'LINE' => 'Line Clear Express & Logistics Sdn Bhd',
                    'LOGISTIKA' => 'Logistika',
                    'M_XPRESS' => 'M Xpress Sdn Bhd',
                    'MYS_MYS_POST' => 'Malaysia Post',
                    'MATDESPATCH' => 'Matdespatch',
                    'MYS_MYPOST_ONLINE' => 'Mypostonline',
                    'NATIONWIDE_MY' => 'Nationwide Express Courier Services Bhd (www.nationwide.com.my)',
                    'NINJAVAN_MY' => 'Ninja Van (www.ninjavan.co)',
                    'PICKUPP_MYS' => 'PICK UPP',
                    'MYS_SKYNET' => 'Skynet Malaysia',
                    'TAQBIN_MY' => 'TAQBIN Malaysia',
                    'WEPOST' => 'WePost Sdn Bhd',
                    'WYNGS' => 'Wyngs',
                    'ZEPTO_EXPRESS' => 'ZeptoExpress',
                ),
            ),
            'MX' => array(
                'name' => 'Mexico',
                'items' => array(
                    'CORREOS_DE_MEXICO' => 'Correos Mexico',
                    'MEX_ESTAFETA' => 'Estafeta (www.estafeta.com)',
                    'GRUPO' => 'Grupo ampm',
                    'HOUNDEXPRESS' => 'Hound Express',
                    'IVOY_WEBHOOK' => 'Ivoy courier',
                    'MEX_SENDA' => 'Mexico Senda Express',
                    'PAQUETEXPRESS' => 'Paquetexpress',
                    'MEX_REDPACK' => 'Redpack',
                ),
            ),
            'NL' => array(
                'name' => 'Netherlands',
                'items' => array(
                    'BROUWER_TRANSPORT' => 'Brouwer Transport en Logistiek',
                    'NLD_DHL' => 'DHL Netherlands',
                    'FIEGE_NL' => 'Fiege Netherlands',
                    'NLD_GLS' => 'GLS Netherlands',
                    'HAPPY2POINT' => 'Happy 2ThePoint',
                    'PAPER_EXPRESS' => 'Paper Express',
                    'POSTNL_INTL_3S' => 'PostNL International 3S',
                    'TRUNKRS_WEBHOOK' => 'Trunkrs courier',
                ),
            ),
            'NZ' => array(
                'name' => 'New Zealand',
                'items' => array(
                    'FASTWAY_NZ' => 'Fastway New Zealand',
                    'INTERPARCEL_NZ' => 'Interparcel New Zealand',
                    'MAINFREIGHT' => 'Mainfreight',
                    'NZ_NZ_POST' => 'New Zealand Post',
                    'TOLL_NZ' => 'Toll New Zealand',
                ),
            ),
            'NG' => array(
                'name' => 'Nigeria',
                'items' => array(
                    'NIPOST_NG' => 'NIpost (www.nipost.gov.ng)',
                ),
            ),
            'NO' => array(
                'name' => 'Norway',
                'items' => array(
                    'HELTHJEM' => 'Helthjem',
                ),
            ),
            'PAK' => array(
                'name' => 'Pakistan',
                'items' => array(
                    'FORRUN' => 'forrun Pvt Ltd (Arpatech Venture)',
                    'TCS' => 'TCS courier	',
                ),
            ),
            'PRY' => array(
                'name' => 'Paraguay',
                'items' => array(
                    'AEX' => 'AEX Group',
                ),
            ),
            'PH' => array(
                'name' => 'Philippines',
                'items' => array(
                    'TWO_GO' => '2GO Courier',
                    'PHL_JAMEXPRESS' => 'Jam Express Philippines',
                    'PIXSELL' => 'PIXSELL LOGISTICS',
                    'RAF_PH' => 'RAF Philippines',
                    'XDE_WEBHOOK' => 'Ximex Delivery Express',
                    'XPOST' => 'Xpost.ph',
                ),
            ),
            'PL' => array(
                'name' => 'Poland',
                'items' => array(
                    'DHL_PL' => 'DHL Poland',
                    'DPD_POLAND' => 'DPD Poland',
                    'FEDEX_POLAND' => 'FedEx® Poland Domestic',
                    'INPOST_PACZKOMATY' => 'InPost Paczkomaty',
                    'PL_POCZTA_POLSKA' => 'Poczta Polska',
                    'ROYAL_MAIL' => 'Royal Mail',
                ),
            ),
            'PT' => array(
                'name' => 'Portugal',
                'items' => array(
                    'ADICIONAL' => 'Adicional Logistics',
                    'BNEED' => 'Bneed courier',
                    'CARRIERS' => 'Carriers courier',
                    'PRT_CHRONOPOST' => 'Chronopost Portugal',
                    'PRT_CTT' => 'CTT Portugal',
                    'DELNEXT' => 'Delnext',
                ),
            ),
            'RO' => array(
                'name' => 'Romania',
                'items' => array(
                    'DPD_RO' => 'DPD Romania',
                    'POSTA_RO' => 'Post Roman (www.posta-romana.ro)',
                ),
            ),
            'RUS' => array(
                'name' => 'Russia',
                'items' => array(
                    'BOX_BERRY' => 'Boxberry courier',
                    'CSE' => 'CSE courier',
                    'DHL_PARCEL_RU' => 'DHL Parcel Russia',
                    'DOBROPOST' => 'DobroPost',
                    'DPD_RU' => 'DPD Russia',
                    'EXPRESSSALE' => 'Expresssale',
                    'GBS_BROKER' => 'GBS-Broker',
                    'PONY_EXPRESS' => 'Pony express',
                    'SHOPFANS' => 'ShopfansRU LLC',
                ),
            ),
            'SAU' => array(
                'name' => 'Saudi Arabia',
                'items' => array(
                    'SAU_SAUDI_POST' => 'Saudi Post',
                    'SMSA_EXPRESS' => 'SMSA Express',
                    'THABIT_LOGISTICS' => 'Thabit Logistics',
                    'ZAJIL_EXPRESS' => 'Zajil Express Company',
                ),
            ),
            'SRB' => array(
                'name' => 'Serbia',
                'items' => array(
                    'POST_SERBIA' => 'Posta Serbia',
                ),
            ),
            'SG' => array(
                'name' => 'Singapore',
                'items' => array(
                    'CLOUDWISH_ASIA' => 'Cloudwish Asia',
                    'SG_DETRACK' => 'Detrack',
                    'FONSEN' => 'Fonsen Logistics',
                    'GRAB_WEBHOOK' => 'Grab courier',
                    'SIMPLYPOST' => 'J&T Express Singapore',
                    'JANIO' => 'Janio Asia',
                    'IND_JAYONEXPRESS' => 'Jayon Express (JEX)',
                    'JET_SHIP' => 'Jet-Ship Worldwide',
                    'KGMHUB' => 'KGM Hub',
                    'LEGION_EXPRESS' => 'Legion Express',
                    'NHANS_SOLUTIONS' => 'Nhans Solutions',
                    'NINJAVAN_SG' => 'Ninja van Singapore',
                    'PARCELPOST_SG' => 'Parcel Post Singapore',
                    'PARKNPARCEL' => 'Park N Parcel',
                    'PICKUPP_SGP' => 'PICK UPP (Singapore)',
                    'SG_QXPRESS' => 'Qxpress',
                    'RAIDEREX' => 'RaidereX',
                    'ROADBULL' => 'Red Carpet Logistics',
                    'RZYEXPRESS' => 'RZY Express',
                    'SG_SG_POST' => 'Singapore Post',
                    'SG_SPEEDPOST' => 'Singapore Speedpost',
                    'TCK_EXPRESS' => 'TCK Express',
                    'COUREX' => 'Urbanfox',
                    'WMG' => 'WMG Delivery',
                    'ZYLLEM' => 'Zyllem',
                ),
            ),
            'SVK' => array(
                'name' => 'Slovakia',
                'items' => array(
                    'GLS_SLOV' => 'GLS General Logistics Systems Slovakia s.r.o.',
                ),
            ),
            'SVN' => array(
                'name' => 'Slovenia',
                'items' => array(
                    'GLS_SLOVEN' => 'GLS Slovenia',
                    'POST_SLOVENIA' => 'Post of Slovenia',
                ),
            ),
            'ZA' => array(
                'name' => 'South Africa',
                'items' => array(
                    'ZA_COURIERIT' => 'Courier IT',
                    'DAWN_WING' => 'Dawn Wing',
                    'DPE_SOUTH_AFRC' => 'DPE South Africa',
                    'INTEXPRESS' => 'Internet Express',
                    'COLLIVERY' => 'MDS Collivery Pty (Ltd)',
                    'RAM' => 'RAM courier',
                    'SKYNET_ZA' => 'Skynet World Wide Express South Africa',
                    'SOUTH_AFRICAN_POST_OFFICE' => 'South African Post Office',
                    'ZA_SPECIALISED_FREIGHT' => 'Specialised Freight',
                    'THECOURIERGUY' => 'The Courier Guy',
                ),
            ),
            'ES' => array(
                'name' => 'Spain',
                'items' => array(
                    'ABCUSTOM' => 'AB Custom Group',
                    'ADERONLINE' => 'Ader couriers',
                    'ASIGNA' => 'ASIGNA courier',
                    'ESP_ASM' => 'ASM(GLS Spain)',
                    'CBL_LOGISTICA' => 'CBL Logistica',
                    'CORREOS_EXPRESS' => 'Correos Express',
                    'DHL_PARCEL_ES' => 'DHL parcel Spain(www.dhl.com)',
                    'DHL_ES' => 'DHL Spain(www.dhl.com)',
                    'ECOSCOOTING' => 'ECOSCOOTING',
                    'ESP_ENVIALIA' => 'Envialia',
                    'ENVIALIA_REFERENCE' => 'Envialia Reference',
                    'INTEGRA2_FTP' => 'Integra2',
                    'MRW_FTP' => 'MRW courier',
                    'ESP_MRW' => 'MRW spain',
                    'NACEX' => 'NACEX',
                    'NACEX_ES' => 'NACEX Spain',
                    'ESP_NACEX' => 'NACEX Spain',
                    'PAACK_WEBHOOK' => 'Paack courier',
                    'ESP_PACKLINK' => 'Packlink',
                    'ESP_REDUR' => 'Redur Spain',
                    'PRT_INT_SEUR' => 'SEUR International',
                    'PRT_SEUR' => 'SEUR portugal',
                    'SEUR_ES' => 'Seur Spain',
                    'SEUR_SP_API' => 'Spanish Seur API',
                    'SPRING_GDS' => 'Spring GDS',
                    'SZENDEX' => 'SZENDEX',
                    'TNT_NL' => 'THT Netherland',
                    'TIPSA' => 'TIPSA courier',
                    'TNT' => 'TNT Express',
                    'GLOBAL_TNT' => 'TNT global',
                    'TOURLINE' => 'tourline',
                    'VAMOX' => 'VAMOX',
                    'VIA_EXPRESS' => 'Viaxpress',
                    'ZELERIS' => 'Zeleris',
                ),
            ),
            'SE' => array(
                'name' => 'Sweden',
                'items' => array(
                    'AIRMEE_WEBHOOK' => 'Airmee couriers',
                    'BRING' => 'Bring',
                    'DBSCHENKER_SE' => 'DB Schenker (www.dbschenker.com)',
                    'DBSCHENKER_SV' => 'DB Schenker Sweden',
                ),
            ),
            'CH' => array(
                'name' => 'Switzerland',
                'items' => array(
                    'ASENDIA_HK' => 'Asendia HonKong',
                    'PLANZER' => 'Planzer Group',
                    'SWISS_POST_FTP' => 'Swiss Post FTP',
                    'VIAEUROPE' => 'ViaEurope',
                ),
            ),
            'TW' => array(
                'name' => 'Taiwan',
                'items' => array(
                    'CNWANGTONG' => 'cnwangtong',
                    'CTC_EXPRESS' => 'CTC Express',
                    'DIMERCO' => 'Dimerco Express Group',
                    'HCT_LOGISTICS' => 'HCT LOGISTICS CO.LTD.',
                    'KERRYTJ' => 'Kerry TJ Logistics',
                    'PRESIDENT_TRANS' => 'PRESIDENT TRANSNET CORP',
                    'GLOBAL_EXPRESS' => 'Tai Wan Global Business',
                ),
            ),
            'TH' => array(
                'name' => 'Thailand',
                'items' => array(
                    'ALPHAFAST' => 'Alphafast',
                    'CJ_KR' => 'CJ Korea Express',
                    'THA_DYNAMIC_LOGISTICS' => 'Dynamic Logistics',
                    'FASTRK_SERV' => 'Fastrak Services',
                    'FLASHEXPRESS' => 'Flash Express',
                    'NIM_EXPRESS' => 'Nim Express',
                    'NINJAVAN_THAI' => 'Ninja van Thai',
                    'SENDIT' => 'Sendit',
                    'SKYBOX' => 'SKYBOX',
                    'THA_THAILAND_POST' => 'Thailand Post',
                ),
            ),
            'TR' => array(
                'name' => 'Turkey',
                'items' => array(
                    'ASE' => 'ASE KARGO',
                    'CDEK_TR' => 'CDEK TR',
                    'PTS' => 'PTS courier',
                    'PTT_POST' => 'PTT Post',
                    'SHIPENTEGRA' => 'ShipEntegra',
                    'YURTICI_KARGO' => 'Yurtici Kargo',
                ),
            ),
            'UA' => array(
                'name' => 'Ukraine',
                'items' => array(
                    'NOVA_POSHTA_INT' => 'Nova Poshta (International)',
                    'NOVA_POSHTA' => 'Nova Poshta (novaposhta.ua)',
                    'POSTA_UKR' => 'UkrPoshta',
                ),
            ),
            'AE' => array(
                'name' => 'United Arab Emirates',
                'items' => array(
                    'IBEONE' => 'Beone Logistics',
                    'MARA_XPRESS' => 'Mara Xpress',
                    'FETCHR_WEBHOOK' => 'Mena 360 (Fetchr)',
                    'ONECLICK' => 'One click delivery services',
                    'SKYNET_UAE' => 'SKYNET UAE',
                ),
            ),
            'GB' => array(
                'name' => 'United Kingdom',
                'items' => array(
                    'AMAZON' => 'Amazon Shipping',
                    'AO_COURIER' => 'AO Logistics',
                    'APC_OVERNIGHT' => 'APC overnight (apc-overnight.com)',
                    'APC_OVERNIGHT_CONNUM' => 'APC Overnight Consignment',
                    'APG' => 'APG eCommerce Solutions',
                    'ARK_LOGISTICS' => 'ARK Logistics',
                    'GB_ARROW' => 'Arrow XL',
                    'ASENDIA_UK' => 'Asendia UK',
                    'BH_WORLDWIDE' => 'B&H Worldwide',
                    'BIRDSYSTEM' => 'BirdSystem',
                    'BLUECARE' => 'Bluecare Express Ltd',
                    'CAE_DELIVERS' => 'CAE Delivers',
                    'CARIBOU' => 'Caribou',
                    'DAIGLOBALTRACK' => 'DAI Post',
                    'DELTEC_UK' => 'Deltec Courier',
                    'DHL_REFR' => 'DHl (Reference number)',
                    'DHL_UK' => 'dhl UK',
                    'DIAMOND_EUROGISTICS' => 'Diamond Eurogistics Limited',
                    'DIRECTPARCELS' => 'Direct Parcels',
                    'DMS_MATRIX' => 'DMSMatrix',
                    'DPD_LOCAL' => 'DPD Local',
                    'DPD_LOCAL_REF' => 'DPD Local reference',
                    'DX_SFTP' => 'DX (SFTP)',
                    'EU_FLEET_SOLUTIONS' => 'EU Fleet Solutions',
                    'FEDEX_UK' => 'FedEx® UK',
                    'FURDECO' => 'Furdeco',
                    'GBA' => 'GBA Services Ltd',
                    'GEMWORLDWIDE' => 'GEM Worldwide',
                    'HERMES' => 'HermesWorld UK',
                    'HOME_DELIVERY_SOLUTIONS' => 'Home Delivery Solutions Ltd',
                    'INTERPARCEL_UK' => 'Interparcel UK',
                    'MYHERMES' => 'MyHermes UK',
                    'NATIONAL_SAMEDAY' => 'National Sameday',
                    'GB_NORSK' => 'Norsk Global',
                    'OCS_WORLDWIDE' => 'OCS WORLDWIDE',
                    'PALLETWAYS' => 'Palletways',
                    'GB_PANTHER' => 'Panther',
                    'PANTHER_REFERENCE' => 'Panther Reference',
                    'PARCEL2GO' => 'Parcel2Go',
                    'PARCELINKLOGISTICS' => 'Parcelink Logistics',
                    'PLUS_LOG_UK' => 'Plus UK Logistics',
                    'RPD2MAN' => 'RPD2man Deliveries',
                    'SKYNET_UK' => 'Skynet UK',
                    'AMAZON_FBA_SWISHIP' => 'Swiship UK',
                    'THEDELIVERYGROUP' => 'TDG – The Delivery Group',
                    'PALLET_NETWORK' => 'The Pallet Network',
                    'TNT_UK' => 'TNT UK Limited (www.tnt.com)',
                    'TNT_UK_REFR' => 'TNT UK Reference',
                    'GB_TUFFNELLS' => 'Tuffnells Parcels Express',
                    'TUFFNELLS_REFERENCE' => 'Tuffnells Parcels Express- Reference',
                    'UK_UK_MAIL' => 'UK mail (ukmail.com)',
                    'WHISTL' => 'Whistl',
                    'WNDIRECT' => 'wnDirect',
                    'UK_XDP' => 'XDP Express',
                    'XDP_UK_REFERENCE' => 'XDP Express Reference',
                    'XPERT_DELIVERY' => 'Xpert Delivery',
                    'UK_YODEL' => 'Yodel (www.yodel.co.uk)',
                ),
            ),
            'US' => array(
                'name' => 'United States',
                'items' => array(
                    'GIO_EXPRESS' => 'Gio Express',
                    'GLOBALTRANZ' => 'GlobalTranz',
                    'GSI_EXPRESS' => 'GSI EXPRESS',
                    'GSO' => 'GSO (GLS-USA)',
                    'HIPSHIPPER' => 'Hipshipper',
                    'GLOBAL_IPARCEL' => 'i-parcel',
                    'DESCARTES' => 'Innovel courier',
                    'US_LASERSHIP' => 'LaserShip',
                    'LONESTAR' => 'Lone Star Overnight',
                    'MAILAMERICAS' => 'MailAmericas',
                    'NEWEGGEXPRESS' => 'Newegg Express',
                    'US_OLD_DOMINION' => 'Old Dominion Freight Line',
                    'OSM_WORLDWIDE' => 'OSM Worldwide',
                    'PCFCORP' => 'PCF Final Mile',
                    'PILOT_FREIGHT' => 'Pilot Freight Services',
                    'PITNEY_BOWES' => 'Pitney Bowes',
                    'PITTOHIO' => 'PITT OHIO',
                    'QWINTRY' => 'Qwintry Logistics',
                    'RL_US' => 'RL Carriers',
                    'SAIA_FREIGHT' => 'Saia LTL Freight',
                    'SHIPTOR' => 'Shiptor',
                    'SONICTL' => 'Sonic Transportation & Logistics',
                    'SEFL' => 'Southeastern Freight Lines',
                    'SPEEDEE' => 'Spee-Dee Delivery',
                    'SUTTON' => 'Sutton Transport',
                    'TAZMANIAN_FREIGHT' => 'Tazmanian Freight Systems',
                    'TFORCE_FINALMILE' => 'TForce Final Mile',
                    'LOGISTYX_TRANSGROUP' => 'Transgroup courier',
                    'TRUMPCARD' => 'TRUMPCARD LLC',
                    'USPS' => 'United States Postal Service',
                    'UPS_MAIL_INNOVATIONS' => 'UPS Mail Innovations',
                    'USF_REDDAWAY' => 'USF Reddaway',
                    'USHIP' => 'uShip courier',
                    'WESTBANK_COURIER' => 'West Bank Courier',
                    'WESTGATE_GL' => 'Westgate Global',
                    'WIZMO' => 'Wizmo',
                    'XPO_LOGISTICS' => 'XPO logistics',
                    'YAKIT' => 'Yakit courier',
                    'US_YRC' => 'YRC courier',
                    'ZINC' => 'Zinc courier',
                ),
            ),
            'URY' => array(
                'name' => 'Uruguay',
                'items' => array(
                    'CORREO_UY' => 'Correo Uruguayo',
                ),
            ),
            'VN' => array(
                'name' => 'Vietnam',
                'items' => array(
                    'JTEXPRESS_VN' => 'J&T Express Vietnam',
                    'KERRYTTC_VN' => 'Kerry Express (Vietnam) Co Ltd',
                    'NTLOGISTICS_VN' => 'Nhat Tin Logistics',
                    'VNM_VIETNAM_POST' => 'Vietnam Post',
                    'VNM_VIETTELPOST' => 'ViettelPost',
                ),
            ),
        );
    }
}
