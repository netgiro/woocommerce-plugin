# Netgíró Payment Plugin for WooCommerce

**Supported Platforms:** WooCommerce (on WordPress)

## Introduction

This document provides a comprehensive guide for configuring and using the Netgíró payment gateway plugin for your WooCommerce store. This plugin seamlessly integrates Netgíró, a leading payment solution, allowing you to offer secure and convenient payment options to your customers. This version of the plugin introduces enhanced customization options, enabling you to define the payment method's name and description as displayed during the checkout process.

## Installation

There are two primary methods to install the Netgíró payment gateway plugin for your WooCommerce store: via the WordPress Plugin Directory or through manual installation.

### Method 1: Installation via WordPress Plugin Directory

The Netgíró plugin is available on the WordPress Plugin Directory.

1.  Navigate to **Plugins** > **Add New** in your WordPress admin dashboard.
2.  Search for **"Netgíró"**.
3.  Click **Install Now** and then **Activate**.

### Method 2: Manual Installation

You can also manually install the Netgíró plugin by downloading it from the following GitHub repository: [https://github.com/netgiro/woocommerce-plugin](https://github.com/netgiro/woocommerce-plugin)

To install manually, follow these steps:

1.  **Download the Plugin:** Obtain the latest version of the plugin as a ZIP file.
2.  **Upload the Plugin:** In your WordPress admin dashboard, go to **Plugins** > **Add New** > **Upload Plugin**.
3.  **Choose File:** Select the downloaded ZIP file and click **Install Now**.
4.  **Activate the Plugin:** Once installation is complete, click the **Activate Plugin** button.

## Configuration

To begin using the Netgíró payment gateway, you need to configure its settings within your store's admin panel. Follow these steps:

1.  Navigate to **Settings**: On the Admin sidebar, go to **WooCommerce** > **Settings**.
2.  **Access Payments**: Click on the **Payments** tab.
3.  **Locate Netgíró**: Find Netgíró in the list of payment methods. Click on the **Manage** or **Setup** button to open the settings.

### General Settings

*   **Enable/Disable**
    *   **Options:** Checkbox
    *   **Description:** Controls whether Netgíró is active. When checked, Netgíró appears as a payment option on the checkout page.
*   **Title**
    *   **Input:** Text field
    *   **Description:** The name of the payment method that the customer sees during checkout (e.g., "Netgíró").
*   **Description**
    *   **Input:** Text area
    *   **Description:** A brief message shown to the customer when they select Netgíró. This usually explains that the invoice will appear in their online bank or that they can choose installments.

### API Credentials

Note: You can obtain these details from [partner.netgiro.is](https://partner.netgiro.is).

*   **Test Mode**
    *   **Options:** Checkbox
    *   **Description:**
        *   Checked: Connects to the Netgíró test environment for sandbox transactions.
        *   Unchecked: Connects to the live production environment for real customer payments.
*   **Application ID**
    *   **Input:** Unique alphanumeric ID
    *   **Description:** Your unique identifier provided by Netgíró to authenticate your store.
*   **Secret Key**
    *   **Input:** Encrypted password field
    *   **Description:** A secure key used to sign and verify communication between your website and Netgíró.

### Payment Options

*   **Payment Confirmation Type**
    *   **Options:** Dropdown menu
        *   **Automatic Confirmation (Capture immediately):** Transactions are authorized and captured (funds requested) as soon as the customer completes the process.
        *   **Server Callback (Recommended):** Uses server-side validation to confirm the payment. This is the most secure method as it ensures the order status is updated even if the customer closes their browser before returning to your site.
        *   **Manual Confirmation (Authorize now, Capture later):** Checks if the customer has the funds/credit, but you must manually "Capture" the payment in the WooCommerce order screen later (usually when shipping the goods).
*   **Send Order Item Details**
    *   **Options:** Checkbox
    *   **Description:** When enabled, the plugin sends a detailed breakdown of the order (product names, prices, and quantities) to Netgíró so the customer can see exactly what they are paying for in their Netgíró portal.

### Page Redirects

*   **Cancel Page**
    *   **Options:** Dropdown menu
    *   **Description:** Determines which page the customer is sent to if they cancel the payment process on the Netgíró gateway. By default, this is set to the standard Checkout Page.

## Important Considerations

*   **Agreement Requirement:** You must have a valid payment processing agreement with Netgíró before using this plugin in live mode. Apply for an agreement here: [Apply for Agreement](https://partner.netgiro.is/Account/Register).
*   **Currency Support:** This Netgíró plugin currently only supports Icelandic Krona (ISK). Ensure your store's base currency in WooCommerce settings is set to ISK for the plugin to function correctly.
*   **Test Mode vs. Live Mode:** Always begin by configuring the plugin in **Test mode** to thoroughly test the payment flow and ensure proper integration. Once testing is complete and successful, uncheck the Test mode setting and enter your live App ID and Secret Key provided by Netgíró.
*   **Required Fields:** When **Test mode** is set to No, the App ID and Secret Key fields are mandatory. If these fields are left empty in live mode, the plugin will not function correctly, and customers will not be able to use Netgíró for payments.
*   **Save Configuration:** After making any changes to the Netgíró settings, remember to click the **Save changes** button at the bottom of the page. Failure to save will result in your changes not being applied.
*   **Callback Handling:** The Netgíró payment provider communicates the transaction status to your store via a callback (server-to-server communication). It is crucial that your server is configured to accept and process these incoming requests and return a **200 OK HTTP response**. If the Netgíró system does not receive a successful 200 OK response, the corresponding payment will be automatically cancelled in the Netgíró system.

### Server Configuration Considerations for Callbacks

While the plugin handles the processing of the callback internally within your WordPress/WooCommerce installation, ensure the following at your server level:

*   **Firewall Rules:** Verify that your server's firewall is not blocking incoming HTTP requests from Netgíró's servers to your store. You may need to consult with Netgíró for a list of their server IP addresses or ranges to whitelist.
*   **Web Server Configuration:** Ensure your web server (e.g., Apache, Nginx) is configured to correctly handle incoming POST requests to the callback URL associated with the Netgíró plugin.
*   **PHP Configuration:** Ensure your PHP settings (e.g., `max_execution_time`, `memory_limit`) are sufficient to handle the callback processing without timeouts or memory errors.
*   **SSL Certificate:** For security reasons, ensure your website has a valid and properly configured SSL certificate (HTTPS). The callback communication will likely occur over HTTPS.

## Testing the Netgíró Payment Gateway

To test the Netgíró payment gateway, including the callback functionality in test mode, follow these steps:

1.  **Enable Test Mode:** Ensure the **Test mode** setting in the Netgíró configuration is checked (set to Yes).
2.  **Place a Test Order:** Navigate to your store's frontend as a customer and proceed through the checkout process.
3.  **Select Netgíró:** On the payment information page, select the Netgíró payment method (it will appear with the name and description you configured).
4.  **Netgíró Payment Website:** You will be redirected to the Netgíró test payment website.
5.  **Use Test Credentials:** On the Netgíró test payment page, select the option **"Borga með kennitölu"** (Pay with National ID). Use the following test credentials:
    *   **Kennitala (National ID):** 1111111119
    *   **Password:** meerko1
6.  **Complete Payment:** Follow the on-screen instructions to complete the test payment.
7.  **Order Confirmation:** After successful test payment and the subsequent callback to your store, you should be redirected back to your WooCommerce store, and the order status should be updated accordingly. If the callback fails, the order status might not update correctly, and the payment could be cancelled in the Netgíró system.

## Post-Configuration Steps

After successfully configuring the Netgíró payment gateway settings, it is highly recommended to clear any WordPress or WooCommerce cache. This ensures that the new configuration settings are properly applied to your storefront. This can usually be done via your caching plugin's settings or your hosting provider's control panel.

## Support

For any further information, questions, or assistance with the Netgíró payment gateway plugin, especially regarding callback issues or server configuration, please do not hesitate to contact the Netgíró support team at netgiro@netgiro.is.
