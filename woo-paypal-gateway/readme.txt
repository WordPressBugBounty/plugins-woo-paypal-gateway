=== Payment Gateway for PayPal on WooCommerce ===
Contributors: easypayment  
Tags: PayPal Express Checkout, PayPal Pro, Braintree, PayPal Checkout  
Requires at least: 3.3  
Tested up to: 6.6.2  
Stable tag: 9.0.5  
Requires PHP: 7.2  
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl-3.0.html  

Seamlessly enable PayPal payments for WooCommerce. Accept PayPal, Pay Later, cards, wallets, and bank payments—powered by an official PayPal Partner.

== Description ==

**Payment Gateway for PayPal on WooCommerce** is the ideal solution for adding PayPal payment options to your WooCommerce store. This comprehensive plugin integrates all major PayPal payment methods, providing a complete "PayPal For WooCommerce" experience. Enhance your checkout with PayPal Express Checkout, PayPal Pro, and Braintree, ensuring secure and efficient transactions for your customers.

### Key Features:
- **PayPal Express Checkout / PayPal Credit**: Enable fast and secure payments with or without a PayPal account.
- **PayPal Pro**: Accept credit card payments directly on your site, ensuring a seamless "PayPal For WooCommerce" experience.
- **Braintree Payments**: Use an integrated drop-in UI for card payments, PayPal, and PayPal Credit.
- **PayPal Payments Advanced**: Keep customers on your site with a simple and secure payment page.
- **PayPal Smart Buttons**: Utilize customizable, responsive payment buttons via REST API.
- **Real-Time Order Status Update**: Stay informed with instant payment notifications (IPN).

### Why Choose PayPal For WooCommerce?
- **Improved User Experience**: Simplifies the checkout process, reducing cart abandonment rates.
- **Enhanced Security**: Leverages PayPal’s secure payment processing, building customer trust.
- **Easy Integration**: Set up quickly and manage directly from your WooCommerce dashboard.
- **Comprehensive PayPal Integration**: Supports all major PayPal methods, making it the best "PayPal For WooCommerce" plugin.

### Coming Soon:
- **Fastlane**: A faster checkout experience.
- **Google Pay**: Easy, secure payments directly from Google.
- **Apple Pay**: Streamlined payments using Apple’s secure payment platform.

== Installation ==

### Automatic Installation
1. Log in to your WordPress dashboard.
2. Navigate to Plugins > Add New.
3. Search for "Payment Gateway for PayPal on WooCommerce."
4. Click "Install Now" and activate the plugin to fully integrate "PayPal For WooCommerce."

### Manual Installation
1. Download the plugin and unzip the files.
2. Upload the plugin folder to `/wp-content/plugins/`.
3. Activate the plugin through the 'Plugins' menu in WordPress.

### Usage
1. Open the WooCommerce settings page and click the "Checkout" tab.
2. Select "PayPal Express Checkout" or any other PayPal method.
3. Enter your API credentials and adjust settings to fit your store's needs for a complete "PayPal For WooCommerce" experience.

== Screenshots ==
1. **Settings Page**: Configure PayPal API credentials easily for WooCommerce.
2. **Checkout Page**: Display multiple PayPal payment options, ideal for "PayPal For WooCommerce."
3. **Order Confirmation**: Real-time status updates for seamless transactions.

== Frequently Asked Questions ==

### How do I create sandbox accounts for testing?
1. Log in at [PayPal Developer](http://developer.paypal.com).
2. Click "Applications" in the top menu.
3. Select "Sandbox Accounts" and click "Create Account" to test "PayPal For WooCommerce" settings.

### Where do I get my API credentials?
- **Live Credentials**: Obtain them by signing into your PayPal account [here](https://www.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run).
- **Sandbox Credentials**: Access them via your PayPal Developer account [here](https://www.sandbox.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run).

### How do I get phone numbers for PayPal Checkout orders?
To enable phone numbers for "PayPal For WooCommerce," go to your PayPal business account settings and require them for all checkouts.

### Can I use this plugin with other payment gateways?
Yes, you can use this plugin alongside other WooCommerce payment methods for flexible checkout options, making it versatile for any "PayPal For WooCommerce" setup.

### Does the plugin support recurring payments?
Currently, the plugin supports one-time payments. For recurring payments in "PayPal For WooCommerce," consider integrating with WooCommerce Subscriptions or other plugins.

== Changelog ==

= 9.0.5 - 2024-10-24 =
* Displayed Smart Button in Checkout block.
* Separated payment methods into PayPal Checkout and Debit & Credit Cards.
* Added icon for PayPal method in the Checkout block.
* Resolved jQuery conflict with PayPal JS SDK.
* Fixed issue where Access Token was not found in cache.

= 9.0.4 =
* Resolved PayPal IPN warning.

= 9.0.3 = 
* Adds Send Item Details option.

= 9.0.2 = 
* Fix - Resolved access token not found in cache.

= 9.0.1 = 
* Fix - Checkout failed: Payment error due to field length being either too long or too short.

= 9.0.0 = 
* Fix - Checkout failed: Payment error due to field length being either too long or too short.

= 8.0.5 = 
* Fix - resolved save button issue.

= 8.0.4 = 
* Fix - PHP error.

= 8.0.3 = 
* Fix - Access Token not found in cache.

= 8.0.1 = 
* Fix - Update Js.

= 8.0.1 = 
* Fix - PHP fatal error.

= 8.0.0 = 
* Adds Block Checkout compatibility.

= 7.2.2 =
* Fix - Resolved PHP notice and some other issue.

= 7.2.0 =
* Fix - Resolved PHP notice and some other issue.

= 7.1.8 =
* Fix - Resolved issue related to phone number.

= 7.1.7 =
* Verification - WooCoomerce 7.7 compatibility.

= 7.1.6 =
* Verification - WooCoomerce 6.8.2 compatibility.

= 7.1.5 =
* Fix - Resolved issue with guest checkout in order review page.
* Fix - Display PayPal validation messages on checkout page.

= 7.1.4 =

* Tested with WC 7.2.0 

= 7.1.3 = 
* Coupon disable when PayPal checkout enable.

= 7.1.2 = 
* Adds Gift Card plugin compatibility.

= 7.1.1 = 
* Fix - Resolved issue with checkout page. hide other payment method on review page.

= 7.1.0 =
* Adds major changes related to PayPal SDK and use Latest PayPal JS.
* improved performance. 

= 7.0.0 =
* Upgrade PayPal Checkout.

= 6.0.1 =
* Resolve rounding issue.
* Resolve PayPal Payflow CC exp_year issue.

= 6.0.0 =
* Verification - WooCoomerce 6.8.2 compatibility.

= 5.0.8 - 08/09/2022
* Verification - WooCoomerce 6.7.0 compatibility.

= 5.0.7 - 04/13/2022
* Verification - WooCoomerce 6.4.0 compatibility.

= 5.0.6 - 03/11/2022
* Resolved PHP notice.

= 5.0.5 - 02/25/2022
* Resolved Body class issue on checkout page.

= 5.0.4 - 02/23/2022
* Verification - WordPress 6.2.1 compatibility.

= 5.0.3 - 02/15/2022
* Resolved multiple PayPal display when update checkout fields.

= 5.0.2 - 02/01/2022
* Resolved PHP Notice.

= 4.0.9 - 14/12/2021
* Remove Trademark 

= 2.0.0 - 08/04/2019 = 
* Add new PayPal Express Checkout Smart button

= 1.0.0 - 12/08/2017 =
* Feature - PayPal Express Checkout

= 1.0.1 - 12/08/2017 =

* PayPal IPN bug resolved.

= 1.0.2 - 12/12/2017 = 

* Add Pre-Order support and Payment token.

= 1.0.3 - 13/12/2017 = 

* Add PayPal Pro payment method.

= 1.0.4 - 15/12/2017 = 

* Add braintree Payment.
* Add icons for all payment methods.

= 1.0.5 - 17/12/2017 = 

* Add PayPal Pro
* Add PayPal Advanced
* Add PayPal Payflow
* Add PayPal Rest

= 1.0.6 - 24-12-2017 =
* WPML compability

= 1.0.7 - 06-01-2018 =
* Code optimizing and better error handling

== Upgrade Notice ==
Upgrade to version 8.0.5 to add the latest PayPal Smart Button integration and improved compatibility for "PayPal For WooCommerce."

== Support and Feedback ==
Need help? Visit our [support page](https://wordpress.org/support/plugin/payment-gateway-for-paypal-on-woocommerce). If you enjoy our plugin, please [leave a review](https://wordpress.org/support/plugin/payment-gateway-for-paypal-on-woocommerce/reviews/)!

## License
This plugin is licensed under the [GPL v3](http://www.gnu.org/licenses/gpl-3.0.html).
