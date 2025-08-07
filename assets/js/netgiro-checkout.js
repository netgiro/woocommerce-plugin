/**
 * netgiro-checkout.js
 *
 * JavaScript integration for the Netgíró payment method in block-based checkout.
 */
(() => {
  'use strict';

  const { __ } = window.wp.i18n;
  const { decodeEntities } = window.wp.htmlEntities;
  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
  const { getSetting } = window.wc.wcSettings;
  const { createElement } = window.wp.element;

  const netgiroData = getSetting('netgiro_data', {});

  const DEFAULT_TITLE = __('Netgíró', 'netgiro');
  const DEFAULT_DESC = __('Pay with Netgíró.', 'netgiro');

  const label = decodeEntities(netgiroData.title || DEFAULT_TITLE);

  const NetgiroContent = () => {
      const description = decodeEntities(netgiroData.description || DEFAULT_DESC);
      return createElement('div', null, description);
  };

  // Register the Netgíró payment method with WooCommerce Blocks.
  registerPaymentMethod({
      name: 'netgiro',             
      label,                       
      ariaLabel: label,
      content: createElement(NetgiroContent, null),
      edit: createElement(NetgiroContent, null),
      canMakePayment: () => true,
      supports: {
          features: netgiroData.supports || [],
      },
  });
})();