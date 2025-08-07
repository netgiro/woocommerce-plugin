=== Netgíró Payment Gateway for WooCommerce ===
Contributors: netgiro, smartmediais
Tags: netgíró, netgiro, split payments, woocommerce
Donate link: -
Stable tag: 5.0.0
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0.0
WC tested up to: 9.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offer your customers Netgíró’s quick, secure, and streamlined payment solution directly in your WooCommerce store.

== Description ==

Netgíró is a secure and widely used Icelandic payment solution, enabling merchants to accept instant payments, spread payments, or provide invoice options. Increase your sales by offering customers a convenient way to pay online or via mobile. Netgíró assumes all risks of defaults, guaranteeing full merchant payout.

**Key Benefits:**

* Instant and secure payments
* Manual capture workflows aligned with WooCommerce standards
* Payment splitting and flexible invoice payments
* Enhanced checkout flow for better customer experience

**Note:** Netgíró is currently exclusive to the Icelandic market.

== Installation ==

1. Upload the Netgíró WooCommerce plugin via WordPress plugin manager.
2. Configure using your Application ID and Secret Key from partner.netgiro.is.

== Upgrade Notice ==

= 5.0 =
Critical update improving plugin structure, security, workflow consistency, and WooCommerce compatibility. All merchants strongly advised to upgrade.

== Frequently Asked Questions ==

= What are the benefits of offering Netgíró? =
Increase sales and customer convenience with secure, instant payments, and flexible payment terms.

= How can my company offer Netgíró? =
Register at: [https://partner.netgiro.is/Account/Register](https://partner.netgiro.is/Account/Register).

== Screenshots ==

1. Netgíró login screen (checkout_login.png)
2. Netgíró checkout page (checkout_page1.png)

== Third-Party Service Integration ==

Integrates securely with Netgíró's payment gateway ([Privacy Policy](https://www.netgiro.is/en/privacy-policy/)).

== Changelog ==

= 5.0 =
* Refactored Plugin Structure
    * Removed Netgiro_Template base class.
    * Decoupled classes like Netgiro_Payment_Form, Netgiro_Payment_Call, Netgiro_Refund from template inheritance.

* Manual Capture Workflow Integration
    * Merchants can now select ConfirmationType=2 for manual captures, harmonizing order processing across multiple gateways.

* Payment Confirmation Enhancements
    * Type 0 payments no longer call PaymentConfirmedURL incorrectly.
    * Added Type 2 manual capture workflow, aligning with Straumur and Verifone payment workflows.

* Send cart items
    * Now you can choose if you send cart items to Netgiro og simply the cart total amount

* Admin & Settings Updates
    * Introduced ConfirmationType setting for choosing between immediate or manual capture.
    * Standardized usage of WooCommerce APIs like wc_get_order() and generate_settings_html().
    * Aligned file naming and structure to WooCommerce standards.

* Improved Debug Logging
    * Transitioned to wc_get_logger(), providing clearer logs under WooCommerce's "Status → Logs" section.

* Updated URLs for Test & Production
    * Corrected endpoints for accurate testing and production environments.

* Netgiro_Refund Improvements
    * Included $reason parameter to prevent warnings.
    * Enhanced JSON response handling for clearer, consistent feedback.

* Security & Code Compliance
    * Implemented secure, signature-based callbacks.
    * Sanitized inputs with esc_attr(), esc_html(), wp_unslash().
    * Renamed variables/files to comply with coding standards.

= 4.4.1 =
* Minor compatibility improvements and maintenance.

= 4.3.1 =
* Fixed invalid headers and verified compatibility with WordPress 6.5.

= 4.3.0 =
* Block support added, code quality improved.

= 4.3.1 =
* Fixed invalid headers and Tested the plugin with WordPress version 6.5

= 4.3.0 =
* Added block supports
* Fixed invalid headers
* Changed default description text.
* Updated coding standards to enhance code quality
* Tested the plugin with WordPress version 6.4.3 and WooCommerce version 8.5.2 for compatibility
* Updated the plugin to meet the latest WordPress and WooCommerce standards

= 4.2.1 =
* Fixed invalid headers

= 4.2.0 =
* Added refund function for improved user experience
* Removed unnecessary code to optimize performance
* Updated coding standards to enhance code quality

= 4.0.2 =
* Tested the plugin with WordPress version 6.2.2 and WooCommerce version 7.2.2 for compatibility

= 4.0.1 =
* Tested the plugin with WordPress version 5.9.3 and WooCommerce version 6.5.1 for compatibility

= 4.0.0 =
* Added callback function for payment confirmation to streamline the payment process

= 3.6.1 =
* Fix failure message

= 3.6.0 =
* Read plugin version number dynamically

= 3.5.9 =
* Add WooCommerce version check feature

= 3.5.7 =
* Our Woocommerce plugins use the iFrame and POST integration
