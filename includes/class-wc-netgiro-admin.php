<?php
class Netgiro_Admin {

    /**
	 *
	 * @since    4.2.0
	 * @access   protected
	 * @var      WC_Netgiro    $payment_gateway_reference
	 */
    protected $payment_gateway_reference;

    public function __construct(&$payment_gateway_reference)
    {
		$this->payment_gateway_reference = $payment_gateway_reference;
    }

    public function get_form_fields()
    {
		return array(
            'enabled'        => array(
                'title'   => esc_html__( 'Enable/Disable', 'netgiro' ),
                'type'    => 'checkbox',
                'label'   => esc_html__( 'Enable Netgíró Payment Module.', 'netgiro' ),
                'default' => 'no',
            ),
            'title'          => array(
                'title'       => esc_html__( 'Title', 'netgiro' ),
                'type'        => 'text',
                'description' => esc_html__( 'Title of payment method on checkout page', 'netgiro' ),
                'default'     => esc_html__( 'Netgíró', 'netgiro' ),
            ),
            'description'    => array(
                'title'       => esc_html__( 'Lýsing', 'netgiro' ),
                'type'        => 'textarea',
                'description' => esc_html__( 'Description of payment method on checkout page.', 'netgiro' ),
                'default'     => esc_html__( 'Borgaðu með Netgíró.', 'netgiro' ),
            ),
            'test'           => array(
                'title'       => esc_html__( 'Prófunarumhverfi', 'netgiro_valitor' ),
                'type'        => 'checkbox',
                'label'       => esc_html__( 'Senda á prófunarumhverfi Netgíró', 'netgiro' ),
                'description' => esc_html__( 'If selected, you need to provide Application ID and Secret Key. Not the production keys for the merchant' ),
                'default'     => 'option_is_enabled',
            ),
            'application_id' => array(
                'title'       => esc_html__( 'Application ID', 'netgiro' ),
                'type'        => 'text',
                'default'     => '881E674F-7891-4C20-AFD8-56FE2624C4B5',
                'description' => esc_html__( 'Available from https://partner.netgiro.is or provided by Netgíró' ),
            ),
            'secretkey'      => array(
                'title'       => esc_html__( 'Secret Key', 'netgiro' ),
                'type'        => 'textarea',
                'description' => esc_html__( 'Available from https://partner.netgiro.is or provided by Netgíró', 'netgiro' ),
                'default'     => 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=',
            ),
            'cancel_page_id' => array(
                'title'       => esc_html__( 'Cancel Page' ),
                'type'        => 'select',
                'options'     => $this->payment_gateway_reference->get_pages( 'Select Page' ),
                'description' => 'URL if payment cancelled',
            ),
        );

    }
}