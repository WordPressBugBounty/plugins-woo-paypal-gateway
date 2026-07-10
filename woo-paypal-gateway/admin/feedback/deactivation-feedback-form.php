<?php
defined('ABSPATH') || die('Cheatin&#8217; uh?');
$deactivation_url = wp_nonce_url('plugins.php?action=deactivate&amp;plugin=' . rawurlencode(WPG_PLUGIN_BASENAME), 'deactivate-plugin_' . WPG_PLUGIN_BASENAME);
?>
<div class="deactivation-Modal">
    <div class="deactivation-Modal-header">
        <div>
            <button class="deactivation-Modal-return deactivation-icon-chevron-left"><?php esc_html_e('Return', 'woo-paypal-gateway'); ?></button>
            <h2><?php esc_html_e('We’re sorry to see you go! 💔', 'woo-paypal-gateway'); ?></h2>
        </div>
        <button class="deactivation-Modal-close deactivation-icon-close"><?php esc_html_e('Close', 'woo-paypal-gateway'); ?></button>
    </div>
    <div class="deactivation-Modal-content">
        <div class="deactivation-Modal-question deactivation-isOpen">
            <p><?php esc_html_e('Can you please tell us why you’re deactivating the plugin? Your feedback helps us make it better.', 'woo-paypal-gateway'); ?></p>
            <ul>
                <li>
                    <input type="radio" name="reason" id="reason-temporary" value="Temporary Deactivation">
                    <label for="reason-temporary"><?php esc_html_e('Temporary deactivation (troubleshooting)', 'woo-paypal-gateway'); ?></label>
                </li>
                <li>
                    <input type="radio" name="reason" id="reason-broke" value="Broken Layout">
                    <label for="reason-broke"><?php esc_html_e('Compatibility issue', 'woo-paypal-gateway'); ?></label>
                    <div class="deactivation-Modal-fieldHidden">
                        <textarea placeholder="<?php esc_attr_e('Please describe what part of the layout or functionality was affected.', 'woo-paypal-gateway'); ?>"></textarea>
                    </div>
                </li>
                <li>
                    <input type="radio" name="reason" id="reason-complicated" value="Complicated">
                    <label for="reason-complicated"><?php esc_html_e('Difficult to set up', 'woo-paypal-gateway'); ?></label>
                    <div class="deactivation-Modal-fieldHidden">
                        <textarea placeholder="<?php esc_attr_e('What part of the setup was confusing or unclear?', 'woo-paypal-gateway'); ?>"></textarea>
                    </div>
                </li>
                <li>
                    <input type="radio" name="reason" id="not-provided" value="features not provided">
                    <label for="not-provided"><?php esc_html_e('Missing features', 'woo-paypal-gateway'); ?></label>
                    <div class="deactivation-Modal-fieldHidden">
                        <textarea placeholder="<?php esc_attr_e('Which features were you looking for?', 'woo-paypal-gateway'); ?>"></textarea>
                    </div>
                </li>
                <li>
                    <input type="radio" name="reason" id="reason-other" value="Other">
                    <label for="reason-other"><?php esc_html_e('Other', 'woo-paypal-gateway'); ?></label>
                    <div class="deactivation-Modal-fieldHidden">
                        <textarea placeholder="<?php esc_attr_e('Please share why you’re deactivating the PayPal plugin so we can make improvements.', 'woo-paypal-gateway'); ?>"></textarea>
                    </div>
                </li>
            </ul>
            <input id="deactivation-reason" type="hidden" value="">
            <input id="deactivation-details" type="hidden" value="">


            <input id="deactivation-reason" type="hidden" value="">
            <input id="deactivation-details" type="hidden" value="">
        </div>
        <p style="margin-top: 20px;">
            <?php esc_html_e('Your privacy is important to us. No personal data is collected with this form—just your valuable feedback and basic system information (such as WordPress and plugin versions) to help us improve our plugin.', 'woo-paypal-gateway'); ?>
        </p>
    </div>

    <div class="deactivation-Modal-footer">
        <a href="https://wordpress.org/support/plugin/woo-paypal-gateway" class="button button-primary" target="_blank" title="<?php esc_attr_e('Visit our support page for assistance', 'woo-paypal-gateway'); ?>"><?php esc_html_e('Get Support', 'woo-paypal-gateway'); ?></a>
        <div>
            <a href="<?php echo esc_attr($deactivation_url); ?>" class="button button-primary deactivation-isDisabled" disabled id="mixpanel-send-deactivation"><?php esc_html_e('Send & Deactivate', 'woo-paypal-gateway'); ?></a>
        </div>
        <a id="deactivation-no-reason" href="<?php echo esc_attr($deactivation_url); ?>" class=""><?php esc_html_e('I rather wouldn\'t say', 'woo-paypal-gateway'); ?></a>
    </div>
</div>
<div class="deactivation-Modal-overlay"></div>
