// import { registerPaymentMethod } from "@woocommerce/blocks-registry";

const settings = window.wc.wcSettings.getSetting( "netgiro_data", {} );

const label         =
	window.wp.htmlEntities.decodeEntities( settings.title ) ||
	window.wp.i18n.__( "Netgíró", "wc-netgiro" );
const Content       = () => {
	return window.wp.htmlEntities.decodeEntities( settings.description || "" );
};
const Block_Gateway = {
	name: "netgiro",
	label: label,
	content: Object( window.wp.element.createElement )( Content, null ),
	edit: Object( window.wp.element.createElement )( Content, null ),
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

// registerPaymentMethod(Block_Gateway);

window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
