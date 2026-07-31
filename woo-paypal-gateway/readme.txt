=== Payment Gateway for PayPal on WooCommerce ===
Contributors: easypayment  
Tags: PayPal, PayPal Checkout, Credit Cards, Venmo  
Requires at least: 5.3
Tested up to: 7.0.2
Stable tag: 9.2.4
Requires PHP: 7.4  
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl-3.0.html  

PayPal, Credit/Debit Cards, Google Pay, Apple Pay, Pay Later, Venmo, SEPA, iDEAL, Mercado Pago, Bancontact & more - by an official PayPal Partner

== Description ==

Payment Gateway for PayPal on WooCommerce is the ideal solution for adding PayPal payment options to your WooCommerce store. This comprehensive plugin integrates all major PayPal payment methods, providing a complete PayPal For WooCommerce" experience. **Developed by an Official PayPal Partner**, this plugin ensures high performance and reliability.

### Key Features:
- **Advanced credit and debit card payments**: Accept credit card payments directly on your site.
- **Fastlane by PayPal**: Accelerated guest checkout — returning Fastlane members are recognized by their email address, verify with a one-time code and pay with their saved card in a couple of clicks.
- **PayPal Checkout**: Provide PayPal Smart Buttons and alternative payment methods.
- **Real-Time Order Status Update**: Stay informed with instant payment notifications (Webhooks).

### Why Choose PayPal For WooCommerce?
- **Improved User Experience**: Simplifies the checkout process, reducing cart abandonment rates.
- **Enhanced Security**: Leverages PayPal’s secure payment processing, building customer trust.
- **Easy Integration**: Set up quickly and manage directly from your WooCommerce dashboard.
- **Comprehensive PayPal Integration**: Supports all major PayPal methods, making it the best "PayPal For WooCommerce" plugin.

### List of Methods

* **PayPal** - The world's most trusted online payment service, offering secure transactions with global reach.
* **Advanced credit and debit card payments** - Accept credit card payments directly on your site.
* **Fastlane by PayPal** - Accelerated guest checkout for US merchants: members check out with their saved card after a one-time-code verification, and new customers can enroll while paying.
* **Google Pay** - A fast, simple, and secure payment method, available globally, enabling users to pay with their saved cards through their Android devices or web browsers.  
* **Apple Pay** - Streamlined payments using Apple’s secure payment platform.
* **Pay Later** - This service, offered by PayPal, lets customers defer payments, popular in the U.S. and Europe for flexible purchasing.  
* **Venmo** - A major mobile payment service in the U.S. with over 70 million users, ideal for peer-to-peer and e-commerce transactions.  
* **Bancontact** - The most widely used payment method in Belgium, processing millions of secure transactions annually.  
* **BLIK** - A leading payment option in Poland, widely used for online and mobile transactions, with millions of users.  
* **Discover** - A popular credit card option in the U.S., serving millions of cardholders and accepted by numerous merchants nationwide.  
* **eps** - An Austrian online bank transfer system supported by major banks, handling millions of secure transactions annually.  
* **iDEAL** - Dominant in the Netherlands, iDEAL is used for over half of online transactions, offering trusted bank-based payments.  
* **MyBank** - A secure online payment method in several European countries, including Italy and France, serving millions of users.  
* **Mastercard** - A globally recognized and trusted credit card option, accepted by merchants worldwide.  
* **Przelewy24** - A leading payment method in Poland, connecting with numerous banks to facilitate millions of transactions.  
* **Mercado Pago** - A major digital payment platform in Latin America with tens of millions of users across countries like Brazil and Argentina.  
* **SEPA-Lastschrift** - Covering over 36 European countries, SEPA enables euro-denominated bank transfers for hundreds of millions of users.  

### Supports

* WooCommerce Subscriptions
* WooCommerce Blocks

### Seamless integration with popular WooCommerce Side Cart and Mini Cart plugins:

* Side Cart WooCommerce | WooCommerce Cart
* WooCommerce Cart & Floating Cart
* XT Floating Cart for WooCommerce
* WPC Fly Cart for WooCommerce
* Addonify Floating Cart for WooCommerce
* All In One Woo Cart
* WooCommerce Fast Cart


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

### Does the plugin support subscription payments?
Yes, the plugin is compatible with the WooCommerce Subscriptions plugin.

### Does the plugin support subscription payments?
Yes, to enable subscription payments with the "PayPal for WooCommerce" plugin, you can integrate it with WooCommerce Subscriptions or compatible third-party plugins.

== Changelog ==

= 9.2.4 - 2026-07-31 =
 * Improved - Your customers now see straight away why a card was declined. On the block-based checkout the reason appears the moment the payment is refused, so a shopper can correct their details or reach for another card immediately. No confusion, no abandoned carts from a checkout that seemed to do nothing.
 * Improved - Every order carries its complete 3D Secure history, not just the final attempt. Where a shopper's bank turned a payment down before a different card succeeded, the order shows how many attempts were blocked and exactly what each one returned — precisely the evidence you want when reviewing an order or answering a chargeback.
 * Improved - 3D Secure order notes are written in plain English: whether the payment was allowed through, blocked with the shopper invited to try again, or blocked with the shopper asked for another payment method. No bank response codes to decode.
 * Added - Chargeback liability at a glance. Orders paid with a card the bank did not authenticate are labelled on the order screen and in the order notes, because a fraud chargeback on one of those is charged to your store rather than to the card issuer. It is information, not an alarm — it applies to a large share of perfectly ordinary orders and does not by itself suggest fraud — with emphasis reserved for the one case that genuinely warrants a look.
 * Improved - Card payments stopped by the 3D Secure check are recorded in the PayPal log together with the reason, so you always have a straight answer when a customer asks why their payment did not go through.
 * Added - Card-testing awareness across your whole store. The plugin recognises card rejections arriving from the same internet address across every order and writes a clear warning to the PayPal log when an unusual number appear in a short window, surfacing a pattern no single order could reveal. Nothing is ever refused on this signal alone: offices, universities and mobile networks place many genuine shoppers behind one address, and your real customers always come first. The warning names the address and the orders, so the judgement stays yours. Tunable with the `wpg_ppcp_3ds_velocity_threshold` and `wpg_ppcp_3ds_velocity_window` filters.
 * Added - A generous per-order attempt allowance. A single checkout that collects ten issuer rejections stops accepting further attempts, so one order cannot be hammered indefinitely. Set high on purpose: a shopper working out which of their cards their bank will accept should never run out of road first, and a card that authenticates is always honoured. Tunable with `wpg_ppcp_3ds_max_rejections`.
 * Improved - The "Review" liability-handling mode gives you a genuine approval step: the payment is taken, the order waits at On hold, and the order screen explains the hold in full — that the customer has already been charged, that the order is waiting on your approval or refund, why it was held, and which setting to change if you would rather these orders complete on their own.
 * Improved - Clearer wording throughout the Liability Shift Handling setting, including exactly what each mode does with payments the bank could not authenticate.
 * Improved - Orders completed by a PayPal webhook now carry their transaction ID, so the Refund button is ready the moment you need it and shipment tracking flows straight through to PayPal. Orders completed before this update are unchanged and can be refunded from the PayPal dashboard.
 * Improved - A paid order stays paid. PayPal re-sends declined, expired and voided notifications, and the plugin recognises a late notification of this kind and leaves a completed order exactly as it is — your reports stay accurate, and your customer never receives anything that contradicts their receipt.
 * Improved - The order-received (thank you) page renders reliably for every shopper the moment payment completes, so customers always land on a proper confirmation of their purchase.
 * Changed - Debug logging is now off by default on new installations, and the setting states plainly that logs can contain customer names and addresses — a privacy-first default for every new store. Stores that already use logging keep it exactly as it is.

= 9.2.3 - 2026-07-30 =
 * Fixed - Your PayPal API credentials are no longer written to the server output. The plugin previously switched on cURL's diagnostic mode for every PayPal API call, which printed the whole HTTP conversation — including the authorisation header carrying your live client ID and secret — to the server's error output, where it was visible in WP-CLI sessions and server logs.
 * Fixed - Debug logging for the PayPal Pro (Payflow) gateway now masks the Payflow user, password, vendor and partner, along with the card number, expiry and security code, so a debug log shared with support never contains your gateway credentials or cardholder data. The other legacy gateways already masked these.
 * Fixed - Card payments on the block-based checkout now complete smoothly from start to finish. The shopper enters their card details, the 3-D Secure verification from their bank appears as expected, and the order is placed as soon as it is approved — all without leaving your checkout page.
 * Fixed - Advanced Credit/Debit Card payments always follow the card route, so the authentication the shopper's bank asks for is applied every time.
 * Fixed - Clearer outcome when a card cannot be charged: if PayPal declines it — for example when the billing address does not match the card (AVS) — the shopper stays on the checkout page, sees the reason, and can update their details and pay again.

= 9.2.2 - 2026-07-29 =
 * Fixed - Card payments that require bank authorisation (3-D Secure) now complete smoothly. Shoppers who confirm a payment in their banking app come straight back to your store and their order is placed, and if PayPal is still finalising the authentication the plugin waits for it rather than interrupting the purchase.
 * Fixed - The card form stays responsive throughout checkout. The Place order button keeps working after the checkout totals refresh, and after a bank authorisation window is closed, so shoppers can always complete or retry their payment.
 * Fixed - "Save payment information to my account" now works on the block-based checkout: the customer's card is added to their saved payment methods along with the purchase. The classic checkout is unchanged.
 * Fixed - Paying with a saved card completes reliably, and customers with more than one saved card are always charged the card they selected.
 * Fixed - Card payments on the block-based checkout are fully compatible with the latest WooCommerce releases: the billing details entered at checkout reach PayPal reliably when the order is placed.
 * Improved - Greater resilience during plugin updates: optional third-party integrations now load independently of one another, so your store keeps serving customers smoothly while an update completes.

= 9.2.1 - 2026-07-27 =
 * Fixed - Verified payment confirmation for PayPal Advanced (Payflow): each returning transaction is now confirmed with a direct server-to-server inquiry to PayPal, and the invoice, amount and currency are reconciled against the WooCommerce order before it is marked paid. Reported by security researcher Muni Nitish Kumar Yaddala.
 * Fixed - Trusted IPN handling: instant payment notifications are now confirmed to be for your own PayPal account and validated against the environment set in your plugin settings, so only genuine notifications update an order. Repeat deliveries are handled cleanly.
 * Fixed - Stronger order integrity: the zero-total signup flow now applies strictly to zero-total orders, so every payable order follows the full, verified payment path.
 * Fixed - Customer account safety: saved PayPal payment methods, subscription payment-method changes and the add-payment-method flow are now tied to the signed-in customer's own account, keeping stored billing agreements private to their owner.
 * Fixed - Accurate FunnelKit upsells: the offer return now captures exactly the PayPal order the store created for that offer, so upsell totals always match what the buyer approved.
 * Fixed - WordPress coding standards: admin actions carry explicit capability checks and the codebase now passes the WordPress Plugin Check review.
 * Fixed - Dependable checkout totals: the PayPal line-item breakdown is validated before every order create and update, so per-line rounding, coupon distribution or tax-inclusive pricing can never hold up a checkout with an "amount mismatch" error.
 * Fixed - Resilient capture handling: if a capture or authorization succeeds at PayPal but the confirmation is lost to a network timeout, the plugin recognises PayPal's "already captured / already authorized" reply, confirms the payment belongs to the order and completes it from the existing transaction — the customer is never charged twice.
 * Fixed - Reliable subscription renewals: the card recorded on the subscription is always the card charged, and a renewal that cannot use it fails cleanly so it can be retried. The previous fallback behaviour remains available via the wpg_ppcp_renewal_charge_first_token_when_stored_missing filter.
 * Fixed - Redirect-flow orders re-sync their final amount and shipping to PayPal after approval, so the buyer always confirms the correct total.
 * Fixed - Complete refund records: refunds issued from the PayPal dashboard, including partial refunds, are recorded as real WooCommerce refunds on the order, with de-duplication when PayPal redelivers the same webhook. Fully refunded orders move to the Refunded status.
 * Fixed - Dependable webhook processing: an event that cannot be processed is requeued with PayPal for redelivery instead of being dropped, and concurrent deliveries for the same order are serialised with an atomic lock so an order is completed exactly once.
 * Fixed - 3D Secure results for card payments made through Google Pay and Apple Pay are now read from the wallet's card data. Liability-shift enforcement for wallets stays off by default (opt-in via the wpg_ppcp_enforce_wallet_3ds filter), so existing wallet payments are unaffected.
 * Fixed - Smoother Fastlane retries on the block checkout: after an unsuccessful attempt the single-use card token is cleared so the buyer can re-authenticate and complete their purchase.
 * Fixed - Cleaner webhook logs: events without a summary field are processed without a PHP notice.
 * Improved - Stronger reCAPTCHA coverage: a checkout submission arriving without its token is now challenged rather than allowed through, closing a simple avenue for bots. PayPal-approved express sessions are unaffected, and the previous lenient behaviour is available via the wpg_ppcp_recaptcha_block_on_missing_token filter.
 * Improved - Express checkout keeps totals in step with the buyer's chosen shipping address: if the total cannot be updated at PayPal, the address change is declined so the buyer can retry with an accurate amount. (Adjustable via the wpg_ppcp_reject_express_on_patch_failure filter.)
 * Improved - [wpg_paypal_button] shortcode buttons recalculate shipping when the buyer changes their address in the PayPal popup, and a shortcode targeting a specific product now routes that product into the order.
 * Improved - WooCommerce Blocks: the PayPal payment method handles incomplete configuration data gracefully and supports a store-defined eligibility override (window.wpgPPCPCanMakePayment).
 * Improved - Scheduled subscription renewals can be classified as RECURRING stored-credential transactions per card-network guidance, and a card's first vaulting as FIRST usage (opt-in via the wpg_ppcp_use_recommended_stored_credentials filter; default behaviour unchanged).
 * Improved - Compatibility filters are wired up for the PayPal SDK locale (WPML / Polylang), the Germanized checkout button label and frontend script data injection.
 * Removed - The deprecated "PayPal Credit Card Payments" gateway (REST API direct card payments) and the bundled PayPal-PHP-SDK library, which PayPal has long since archived. Merchants using it continue to see the migration notice and are encouraged to move to PayPal Checkout, which supports Advanced Credit/Debit Card payments, Google Pay and Apple Pay.
 * Removed - The legacy Braintree gateway, its WooCommerce Blocks integration and the bundled Braintree PHP SDK. Merchants using it continue to see the migration notice and are encouraged to move to PayPal Checkout.
 * Credits - Our sincere thanks to security researcher Muni Nitish Kumar Yaddala, whose responsible disclosure prompted the payment-verification improvements in this release, and to the WordPress Plugin Directory team for coordinating it. We welcome and act on reports like this one.

= 9.2.0 - 2026-07-22 =
 * Added - Fastlane by PayPal: accelerated guest checkout for the Advanced Credit/Debit Card gateway. Returning Fastlane members are recognized by their email address, verify with a one-time code and pay with their saved card in a couple of clicks; new customers can enroll while entering their card. Available to US merchants transacting in USD; enable it under PayPal Gateway settings → Advanced Credit Card tab.
 * Added - Fastlane flows: choose Email Detection (Fastlane engages automatically when a member email address is entered) or Express Button (a Fastlane button in the Express Checkout section, styled and sized to match the other express buttons).
 * Added - Fastlane options: "Fastlane by PayPal" watermark under the email field, signup link for non-members, email-field-at-top checkout layout, and optional authenticate-on-page-load for customers whose email is already populated.
 * Added - Fastlane member autofill: the saved card's billing address, the profile's default shipping address, and phone number are applied to the checkout automatically; the card fields are replaced with a tokenized-card tile with "Choose a different card" and "Enter card details manually" actions.
 * Added - Fastlane works on both the classic and block-based checkout, and supports "Save to account": a logged-in customer can vault the Fastlane card for future purchases and subscription renewals.
 * Added - Fastlane is now enabled by default for stores that connect their PayPal account after this release when the account is approved for Fastlane. Authenticate-on-page-load stays off by default (opt-in) so returning members are not shown a one-time-code prompt before they engage with the checkout. Existing stores are unaffected — Fastlane stays off until enabled manually.

= 9.1.5 - 2026-07-20 =
 * Fixed - Security: the approved PayPal order is now reconciled against the WooCommerce order before payment is captured. The order id embedded in the PayPal payment plus its amount and currency must match the order being paid, preventing a checkout from being completed with a different (for example lower-value) PayPal payment.
 * Fixed - Pay Later message now displays on the block-based cart and checkout pages, rendered in the order summary below the totals and updated live as the cart changes. Classic cart and checkout pages keep working as before, using the same Pay Later message settings.

= 9.1.4 - 2026-07-20 =
 * Added - Free-trial subscriptions and charge-upon-release pre-orders can now be purchased with a new payment method: the buyer approves a PayPal vault setup token (nothing is charged now) and the saved method is used for the future charge. Saved payment methods complete zero-total signups directly.
 * Added - Full WooCommerce Pre-Orders "charge upon release" support: the order is marked as pre-ordered at signup and the release charge uses the buyer's vaulted payment method.
 * Added - FunnelKit upsells now work for buyers without a saved payment method: the buyer is redirected to PayPal to approve the offer amount and the upsell completes on return.
 * Added - FunnelKit upsell offers can now be refunded directly from the FunnelKit admin.
 * Added - Elementor: new "PayPal Cart Buttons", "PayPal Product Buttons" and "PayPal Pay Later Message" widgets for context-specific placement.
 * Improved - CheckoutWC order bumps and FunnelKit upsells always charge exactly the approved offer amount when using a saved payment method.
 * Improved - Saved-payment-method charges (subscription renewals, one-click offers) using the "Authorize" payment action are now seamlessly recognized as successful authorizations: the order moves to on-hold and is captured on status change.
 * Improved - Express checkout from the product page now respects the store's product validation rules (for example required Product Add-ons/Extra Product Options fields) and shows the store's own helpful message before payment begins.
 * Improved - Express checkout from the product page always uses the buyer's latest selected quantity and product options.
 * Improved - Pre-Orders compatibility updated for PayPal's latest API requirements.
 * Improved - Smoother Mondial Relay compatibility: the pickup-point check now applies only to this plugin's own payment flows, so classic checkouts validated by the Mondial Relay plugin are never affected.
 * Improved - Refunds for orders paid with the "Authorize" payment action are now processed seamlessly against the captured transaction.
 * Improved - Clearer guidance when refunding an order whose payment has not been captured yet.
 * Improved - Cancelling an order with an uncaptured authorized payment now instantly releases the hold on the customer's funds at PayPal. Captured payments are never affected.
 * Improved - Smarter invoice ID handling: if PayPal reports an invoice number as already in use (for example after a store migration), the plugin automatically retries with a unique invoice ID so the shopper's checkout always completes smoothly.
 * Improved - More accurate address handling during express checkout: only the address details PayPal shares before approval are synced, and the buyer's confirmed address is applied right after approval.
 * Improved - Customer phone numbers entered with a country code (for example "+1 650 555 5555") are automatically formatted to PayPal's expected national format.
 * Improved - Faster admin experience: the seller onboarding status check reuses the securely cached PayPal access token.
 * Improved - Faster webhook processing: PayPal events are matched to orders instantly using the stored PayPal order ID, reducing API calls.
 * Improved - Enhanced customer privacy: debug logs automatically mask buyer email addresses and phone numbers.
 * Improved - Smoother PayPal account switching: disconnecting an account now fully refreshes all cached API credentials, so a newly connected account takes effect immediately.
 
= 9.1.3 - 2026-07-15 =
 * Improved - Cleaner post-update experience: the WooCommerce admin stays distraction-free after plugin updates, with detailed migration status available anytime from WooCommerce > Status.
 * Improved - More reliable database updates: version migrations now self-verify and complete automatically on the next admin page load, for a smooth, hands-free upgrade every time.

= 9.1.2 - 2026-07-13 =
 * Added - "Smart" 3D Secure liability handling mode (now the default): declines only card payments that fail the issuer's authentication challenge and lets shoppers retry, protecting against stolen-card chargebacks without declining legitimate customers.
 * Fixed - The 3D Secure liability-shift decision is now enforced on every Advanced Card capture and authorize path (standard checkout, block checkout, and classic return handler), not only the smart-button path.
 * Fixed - 3D Secure settings default is now read from and seeded into the correct gateway option.
 * Changed - Existing stores using the legacy "Accept" 3D Secure default are automatically upgraded to "Smart" on update. Stores that deliberately selected "Review" or "Reject" are left unchanged.
 * Added - AVS/CVV fraud screening for the legacy direct-card gateways (PayPal Pro, Payflow Pro, REST Credit Card): orders where the security code does not match, or the billing address fails address verification, are placed on hold for review instead of being auto-completed, so they are not shipped before the merchant can verify or refund.

= 9.1.1 - 2026-07-12 =
 * Fixed - Fatal error when a third-party plugin (e.g. Advanced Product Fields for WooCommerce Extended) uses a fragile autoloader. Plugin-detection checks no longer trigger other plugins' autoloaders.

= 9.1.0 - 2026-07-10 =
 * Added - reCAPTCHA v3 fraud protection for checkout with configurable score threshold.
 * Added - Configurable 3D Secure liability shift handling with admin settings.
 * Added - WooCommerce Blocks mini-cart express payment buttons (PayPal, Google Pay, Apple Pay).
 * Added - Theme-overridable template system for payment buttons.
 * Added - Shortcode system for flexible PayPal button placement ([wpg_paypal_button]).
 * Added - Elementor widget for PayPal button placement.
 * Added - Third-party plugin compatibility layer (WPC Fly Cart, XT Floating Cart, Addonify, and more).
 * Added - Germanized and CheckoutWC compatibility modules.
 * Added - Admin token management for subscription payment methods.
 * Enhanced - WooCommerce Blocks integration refactored for reliability and compatibility.
 * Enhanced - Subscription renewal payment flow with atomic locking to prevent duplicate charges.
 * Enhanced - reCAPTCHA token refresh on checkout submit to prevent 2-minute expiration failures.
 * Enhanced - Cache flush query bounded with LIMIT to prevent long-running queries on large sites.
 * Enhanced - Failing payment method update now preserves payment method title on subscriptions.

= 9.0.66 - 2026-06-30 =
 * Fixed - billing address not being saved to the order when paying with the Google Pay button in the payment options list on Block Checkout.

= 9.0.65 - 2026-06-02 =
 * Fixed - Missing shipping options in PayPal express checkout for specific addresses.

= 9.0.64 - 2026-04-17 =
 * Fixed - Google Pay [OR_BIBED_06] error on express payment when "Hide shipping costs until an address is entered" is enabled. 

= 9.0.63 - 2026-04-16 =
 * Fixed - Compatibility issue with FunnelKit Upsell in certain cases.
 
= 9.0.62 - 2026-04-08 =
 * Fixed - Pay Later Messaging shortcode not rendering on default WordPress/WooCommerce pages. (handled special case).
 * Fixed - Inconsistent behavior of “Use Place Order Button” setting on Order Pay page.
 * Fixed - Shipping-related issue.

= 9.0.61 - 2026-03-30 =
* Fixed - Apple Pay and Google Pay buttons not rendering in mini cart on non-product pages (e.g. homepage).

= 9.0.60 - 2026-03-21 =
* Fixed - Improved handling of authorization-only payments to ensure correct order status updates.

= 9.0.59 - 2026-03-20 =
* Fixed PayPal order capture skipped and shipping address update not applied due to session status race condition during express checkout flow.

= 9.0.58 - 2026-03-19 =
* Added - Settings to configure Authorized Order Status and Capture Order Statuses for better control over PayPal payment flow.
* Improved - Enhanced locale cookie handling to avoid repeated cookie resets and improve performance with caching systems.

= 9.0.57 - 2026-03-03 =
 * Added - Auto-complete paid orders option to automatically mark orders as Completed after successful payment.
 * Updated - Improved Polylang compatibility.

= 9.0.56 - 2026-02-04 =
* Added - Polylang compatibility.
* Added - Notice prompting Classic payment method users to migrate to the new PPCP.
* Fixed - Shipping address country is now correctly restricted based on the configured settings for Google Pay and Apple Pay.
* Updated - Default environment switched from Sandbox to Production.

= 9.0.55 - 2026-01-17 =
* Added - Exclude PayPal SDK from Cache plugin.
* Added - PayPal button preview in the setting panel.
* Fixed - Google Pay button shape issue for express checkout.

= 9.0.54 - 2025-12-15 =
* Fixed - Access control and request validation for admin settings updates.
* Fixed - WooCommerce pending order issue.
* Fixed - Block checkout order note issue.

= 9.0.53 - 2025-12-15 =
* Added - Compatibility with YayCurrency for Express Checkout.
* Fixed - Updated required capability from activate_plugins to deactivate_plugins for improved admin access handling.
* Fixed - For Order Pay, ensured the final payment provider is correctly reflected on the order when checkout is completed via a different payment flow than initially selected.
* Fixed - Field validation for Express Checkout.

= 9.0.52 - 2025-11-27 =
* Added - Option to skip the final Order Review page for faster checkout.
* Fixed - Apple Pay and Google Pay validation issues on express checkout when the phone number field is set as required.

= 9.0.51 - 2025-11-24 =
* Added - Support for selecting shipping methods directly within PayPal, Google Pay, and Apple Pay pop-ups.

= 9.0.50 - 2025-11-17 =
* Added – Compatibility with YayCurrency Multi-Currency Switcher plugin for Express Checkout.
* Fixed - Displaying decline reason on checkout page.

= 9.0.49 - 2025-11-06 =
* Fixed - Hide PayPal/Express buttons when order total is $0.
* Fixed - Shipping not calculating for Express Checkout when using wallet's default shipping address (calculates correctly at final capture).
* Fixed - Clear decline reason now displayed with improved logging.

= 9.0.48 - 2025-10-14 =
* Fixed - Hide place order button issue for order review page.

= 9.0.47 - 2025-10-10 =
* Fixed - In Block Checkout, Smart Buttons not reinitializing on subsequent page loads in some themes.
* Fixed - Google Pay button appearing when Credit Card method is selected in a specific layout.
* Fixed - "Place Order" button hidden when both Advanced Credit Card and Express Checkout options are disabled.
* Fixed - Credit Card subscription renewal processing issue.

= 9.0.46 - 2025-09-26 =
* Fixed – Intermittent Google Pay button not rendering on first load.
* Fixed – Skeleton loader extending beyond container width.

= 9.0.45 – 2025-09-23 =
* Enhanced – PayPal button layout updated from vertical to horizontal in Checkout Block for improved design consistency.
* Fixed – Resolved friction with the Place Order button on the checkout page.

= 9.0.44 – 2025-09-08 =
* Enhanced – Added WooCommerce Subscriptions compatibility for Advanced Card Payments.
* Enhanced – Google Pay integration now includes line item support.

= 9.0.43 – 2025-09-03 =
* Fixed - Compatibility issue with FunnelKit Express Checkout.
* Fixed - PayPal SDK conflict affecting Advanced Credit Card fields.

= 9.0.42 – 2025-08-27 =
* Added - Compatibility with Checkout Field Editor (Checkout Manager) for WooCommerce by ThemeHigh.
* Added - Compatibility with FunnelKit’s Express Checkout.
* Added - Loading placeholder for Smart Buttons.

= 9.0.41 – 2025-08-19 =
* Added - Admin Only Mode for safe live site testing.
* Fixed - Compatibility issue with FunnelKit Sliding Cart.
* Fixed - Minor webhook issue affecting real-time order updates.
* Fixed - Error handling when PayPal Fee value is empty.

= 9.0.40 – 2025-08-12 =
* Fixed - Advanced credit card was being automatically enabled after onboarding status sync API.
* Fixed - Pill style not displaying correctly for Apple Pay on cart and checkout blocks.

= 9.0.39 – 2025-07-29 =
* Added - New setting to choose PayPal icon style: Monogram, Wordmark, or Combination.
* Updated - Default PayPal icon set to Monogram for cleaner display beside the payment label.
* Fixed - Styling conflict affecting Google Pay label on some themes.

= 9.0.38 – 2025-07-21 =
* Fixed - Addressed an issue with shipping address validation during checkout.
* Enhanced - Refined the seller onboarding process for a smoother experience.

= 9.0.37 – 2025-07-07 =
* Fixed – Issue with PayPal fee not saving correctly has been resolved.
* Fixed – Deprecated payment methods Giropay and Sofort have been removed.

= 9.0.36 – 2025-06-24 =
* Enhanced – Optimized the settings panel for better usability.
* Fixed – Corrected the return and cancel URLs for improved redirect handling.

= 9.0.35 – 2025-06-12 =
* Added – WooCommerce 9.3.3 compatibility.

= 9.0.34 – 2025-06-05 =
* Enhanced – Added settings for Google Pay and Apple Pay button label, color, and shape.
* Enhanced – Introduced compatibility with Fluid Checkout for WooCommerce.

= 9.0.33 – 2025-05-27 =
* Enhanced – Introduced "Save Card" feature for advanced credit card payments.
* Enhanced – Apple Pay support on product page.
* Fixed – Resolved issues with alternative payment methods (bank redirect flow).

= 9.0.32 – 2025-05-12 =
* Enhanced – Added "Use Place Order Button" setting to show default checkout button instead of PayPal buttons (does not affect Express Checkout).
* Enhanced – Compatibility with WooCommerce Shipment Tracking extension.
* Fixed – Force 3D Secure for eligible transactions.

= 9.0.31 – 2025-04-30 =
* Fixed – Resolved Apple Pay issue in Express Checkout flow.

= 9.0.30 – 2025-04-30 =
* Fixed – Compatibility issues with Google Pay and Apple Pay.
* Fixed – Shipping country validation for Express Checkout.
* Enhanced – Added "Use PayPal Shipping Address as Billing" option under Additional Settings.

= 9.0.29 – 2025-04-18 =
* Fixed – Validation issue with Advanced Credit Card on checkout.
* Fixed – Compatibility issue with Google Pay transactions.

= 9.0.28 – 2025-04-17 =
* Fixed – PHP notice.
* Fixed – Minor changes in the settings panel.

= 9.0.27 – 2025-04-15 =
* Added – Compatibility with Reactify Classic Payments settings.
* Fixed – Spinner issue during Place Order error.
* Added – New FAQs for better user guidance.
* Updated – Notices and setup instructions for PayPal, Google Pay, and Apple Pay.

= 9.0.26 – 2025-03-19 =
* Enhanced – Improved positioning of PayPal Smart Buttons on checkout page.

= 9.0.25 – 2025-02-25 =
* Fixed – Compatibility with WooCommerce Germanized plugin.
* Fixed – Compatibility with YayCurrency Multi-Currency Switcher plugin.
* Added – PayPal Shipment Tracking widget in admin order details.
* Fixed – Removed getmypid() due to Kinsta incompatibility.

= 9.0.24 – 2025-02-17 =
* Fixed – Mini cart quantity update issue.

= 9.0.23 – 2025-02-10 =
* Optimized – Size and positioning adjustments.

= 9.0.22 – 2025-01-29 =
* Fixed – Settings panel issue.

= 9.0.21 – 2025-01-21 =
* Fixed – Shipping preferences API issue.

= 9.0.20 – 2025-01-17 =
* Fixed – Button design issue on Cart page.

= 9.0.19 – 2025-01-16 =
* Fixed – Google Pay integration issue on Checkout page.

= 9.0.18 – 2025-01-10 =
* Added – Apple Pay integration.
* Fixed – Minor Checkout page issue.

= 9.0.17 – 2024-12-30 =
* Added – WooCommerce Subscriptions compatibility.
* Added – PayPal Vault support.

= 9.0.16 – 2024-12-17 =
* Optimized – CSS and layout.

= 9.0.15 – 2024-12-11 =
* Added – PayPal Seller Onboarding.
* Fixed – Google Pay issue.

= 9.0.14 – 2024-12-04 =
* Added – Google Pay support.

= 9.0.13 – 2024-11-25 =
* Added – CSP and Cookies compatibility.
* Improved – Gateway settings organization.
* Enhanced – Button layout and UI styling.

= 9.0.12 – 2024-11-15 =
* Fixed – Sending line item details to PayPal.
* Fixed – "Leaving Site" popup issue.

= 9.0.11 – 2024-11-12 =
* Added – Language files for localization.
* Updated – Accordion design for settings panel.
* Enhanced – Settings fields usability.

= 9.0.10 – 2024-11-05 =
* Updated – CSS and JavaScript enhancements.

= 9.0.9 – 2024-10-30 =
* Fixed – wc\_add\_notice error trigger.

= 9.0.8 – 2024-10-28 =
* Fixed – Loading visibility issue.

= 9.0.7 – 2024-10-25 =
* Fixed – Credit card fields visibility issue.

= 9.0.6 – 2024-10-24 =
* Fixed – jQuery conflict with themes.
* Updated – Logic to toggle payment container visibility.

= 9.0.5 – 2024-10-24 =
* Added – Smart Buttons in Checkout block.
* Separated – PayPal Checkout and Debit/Credit Cards.
* Added – PayPal icon in Checkout block.
* Fixed – jQuery conflict with PayPal SDK.
* Fixed – Access Token cache issue.

= 9.0.4 =
* Fixed – PayPal IPN warning.

= 9.0.3 =
* Added – Option to send item details.

= 9.0.2 =
* Fixed – Access Token cache issue.

= 9.0.1 =
* Fixed – Checkout field length validation error.

= 9.0.0 =
* Fixed – Checkout field length validation error.

= 8.0.5 =
* Fixed – Save button issue.

= 8.0.4 =
* Fixed – PHP error.

= 8.0.3 =
* Fixed – Access Token cache issue.

= 8.0.1 =
* Fixed – JavaScript update.
* Fixed – PHP fatal error.

= 8.0.0 =
* Added – Block Checkout compatibility.

= 7.2.2 =
* Fixed – PHP notices and minor issues.

= 7.2.0 =
* Fixed – PHP notices and minor issues.

= 7.1.8 =
* Fixed – Phone number validation issue.

= 7.1.7 =
* Verified – WooCommerce 7.7 compatibility.

= 7.1.6 =
* Verified – WooCommerce 6.8.2 compatibility.

= 7.1.5 =
* Fixed – Guest checkout issue on order review.
* Fixed – PayPal validation messages display.

= 7.1.4 =
* Tested – WooCommerce 7.2.0 compatibility.

= 7.1.3 =
* Updated – Disabled coupons with PayPal checkout.

= 7.1.2 =
* Added – Gift Card plugin compatibility.

= 7.1.1 =
* Fixed – Hiding other payment methods during review.

= 7.1.0 =
* Major Update – Latest PayPal SDK integration.
* Improved – Performance.

= 7.0.0 =
* Upgraded – PayPal Checkout.

= 6.0.1 =
* Fixed – Rounding issue.
* Fixed – Payflow CC expiration year issue.

= 6.0.0 =
* Verified – WooCommerce 6.8.2 compatibility.

= 5.0.8 =
* Verified – WooCommerce 6.7.0 compatibility.

= 5.0.7 =
* Verified – WooCommerce 6.4.0 compatibility.

= 5.0.6 =
* Fixed – PHP notice.

= 5.0.5 =
* Fixed – Body class issue on checkout.

= 5.0.4 =
* Verified – WordPress 6.2.1 compatibility.

= 5.0.3 =
* Fixed – Multiple PayPal buttons on field updates.

= 5.0.2 =
* Fixed – PHP notice.

= 4.0.9 =
* Removed – Trademark references.

= 2.0.0 =
* Added – PayPal Express Checkout Smart Button.

= 1.0.7 =
* Optimized – Code and error handling.

= 1.0.6 =
* Added – WPML compatibility.

= 1.0.5 =
* Added – PayPal Pro, Advanced, Payflow, and REST.

= 1.0.4 =
* Added – Braintree Payments.
* Added – Payment method icons.

= 1.0.3 =
* Added – PayPal Pro payment method.

= 1.0.2 =
* Added – Pre-Order support and payment token.

= 1.0.1 =
* Fixed – PayPal IPN bug.

= 1.0.0 =
* Feature – Initial PayPal Express Checkout.

== Support and Feedback ==
Need help? Visit our [support page](https://wordpress.org/support/plugin/payment-gateway-for-paypal-on-woocommerce). If you enjoy our plugin, please [leave a review](https://wordpress.org/support/plugin/payment-gateway-for-paypal-on-woocommerce/reviews/)!

## License
This plugin is licensed under the [GPL v3](http://www.gnu.org/licenses/gpl-3.0.html).
