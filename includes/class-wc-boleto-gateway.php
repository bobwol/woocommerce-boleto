<?php
/**
 * WC Boleto Gateway Class.
 *
 * Built the Boleto method.
 */
class WC_Boleto_Gateway extends WC_Payment_Gateway {

    /**
     * Gateway's Constructor.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id           = 'boleto';
        $this->icon         = apply_filters( 'wcboleto_icon', WC_BOLETO_URL . 'assets/images/boleto.png' );
        $this->has_fields   = false;
        $this->method_title = __( 'Boleto', 'wcboleto' );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user settings variables.
        $this->title       = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->boleto_time = $this->settings['boleto_time'];

        // Actions.
        add_action( 'woocommerce_thankyou_boleto', array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 2 );
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        else
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

        // Valid for use.
        $this->enabled = ( 'yes' == $this->settings['enabled'] ) && $this->is_valid_for_use();
    }

    /**
     * Checking if this gateway is enabled and available in the user's country.
     *
     * @return bool
     */
    public function is_valid_for_use() {
        if ( ! in_array( get_woocommerce_currency(), array( 'BRL' ) ) )
            return false;

        return true;
    }

    /**
     * Admin Panel Options.
     *
     * @return string Admin form.
     */
    public function admin_options() {
        echo '<h3>' . __( 'Boleto standard', 'wcboleto' ) . '</h3>';
        echo '<p>' . __( 'Enables payments via Boleto.', 'wcboleto' ) . '</p>';

        // Checks if is valid for use.
        if ( ! $this->is_valid_for_use() ) {
            echo '<div class="inline error"><p><strong>' . __( 'Boleto Disabled', 'wcboleto' ) . '</strong>: ' . __( 'Works only with Brazilian Real.', 'wcboleto' ) . '</p></div>';
        } else {
            // Generate the HTML For the settings form.
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
            echo '<script type="text/javascript">';
            echo 'jQuery(document).ready(function($){$("#woocommerce_boleto_bank").change(function(){$("#mainform").submit();});});';
            echo'</script>';
        }
    }

    /**
     * Start Gateway Settings Form Fields.
     *
     * @return void
     */
    public function init_form_fields() {

        $first = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'wcboleto' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Boleto standard', 'wcboleto' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', 'wcboleto' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcboleto' ),
                'default'     => __( 'Boleto', 'wcboleto' )
            ),
            'description' => array(
                'title'       => __( 'Description', 'wcboleto' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcboleto' ),
                'default'     => __( 'Pay with Boleto', 'wcboleto' )
            ),
            'boleto_details' => array(
                'title' => __( 'Boleto Details', 'wcboleto' ),
                'type'  => 'title'
            ),
            'boleto_time' => array(
                'title'       => __( 'Deadline to pay the Boleto', 'wcboleto' ),
                'type'        => 'text',
                'description' => __( 'Number of days to pay.', 'wcboleto' ),
                'default'     => 5
            ),
            'boleto_logo' => array(
                'title'       => __( 'Boleto Logo', 'wcboleto' ),
                'type'        => 'text',
                'description' => __( 'Logo with 147px x 46px.', 'wcboleto' ),
                'default'     => WC_BOLETO_URL . 'assets/images/logo_empresa.png'
            ),
            'bank_details' => array(
                'title' => __( 'Bank Details', 'wcboleto' ),
                'type'  => 'title'
            ),
            'bank' => array(
                'title'       => __( 'Bank', 'wcboleto' ),
                'type'        => 'select',
                'description' => __( 'Choose the bank for Boleto.', 'wcboleto' ),
                'default'     => __( 'Pay with Boleto', 'wcboleto' ),
                'options'     => array(
                    '0'          => '--',
                    'bb'         => 'Banco do Brasil',
                    'bradesco'   => 'Bradesco',
                    'cef'        => 'Caixa Economica Federal - SR (SICOB)',
                    'cef_sigcb'  => 'Caixa Economica Federal - SIGCB',
                    'cef_sinco'  => 'Caixa Economica Federal - SINCO',
                    'hsbc'       => 'HSBC',
                    'itau'       => 'Itau',
                    'nossacaixa' => 'Nossa Caixa',
                    'real'       => 'Real',
                    'santander'  => 'Santander',
                    'unibanco'   => 'Unibanco',
                )
            )
        );

        $last = array(
            'extra_details' => array(
                'title' => __( 'Optional Data', 'wcboleto' ),
                'type'  => 'title'
            ),
            'quantidade' => array(
                'title'       => __( 'Quantity', 'wcboleto' ),
                'type'        => 'text'
            ),
            'valor_unitario' => array(
                'title'       => __( 'Unitary value', 'wcboleto' ),
                'type'        => 'text'
            ),
            'aceite' => array(
                'title'       => __( 'Acceptance', 'wcboleto' ),
                'type'        => 'text'
            ),
            'especie' => array(
                'title'       => __( 'Currency', 'wcboleto' ),
                'type'        => 'text',
                'default'     => 'R$'
            ),
            'especie_doc' => array(
                'title'       => __( 'Kind of document', 'wcboleto' ),
                'type'        => 'text'
            ),
            'shop_details' => array(
                'title' => __( 'Shop Details', 'wcboleto' ),
                'type'  => 'title'
            ),
            'cpf_cnpj' => array(
                'title'       => __( 'CPF/CNPJ', 'wcboleto' ),
                'type'        => 'text',
                'description' => __( 'Document number.', 'wcboleto' ),
            ),
            'endereco' => array(
                'title'       => __( 'Address', 'wcboleto' ),
                'type'        => 'text',
                'description' => __( 'Shop Address.', 'wcboleto' ),
            ),
            'cidade_uf' => array(
                'title'       => __( 'City/State', 'wcboleto' ),
                'type'        => 'text',
                'description' => __( 'Example <code>S&atilde;o Paulo/SP</code>.', 'wcboleto' ),
            ),
            'cedente' => array(
                'title' => __( 'Corporate Name', 'wcboleto' ),
                'type'  => 'text',
            ),
        );

        $this->form_fields = array_merge( $first, $this->get_bank_fields(), $last );
    }

    /**
     * Get current bank.
     * Method compatible with versions prior to woocommerce 2.0
     *
     * @return Current bank.
     */
    protected function get_current_bank() {
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            return $this->get_option( 'bank' );
        } else {
            // Gets current bank.
            $settings = get_option( 'woocommerce_boleto_settings' );

            return sanitize_text_field( $settings['bank'] );
        }
    }

    /**
     * Gets bank fields.
     *
     * @return array Current bank fields.
     */
    protected function get_bank_fields() {

        switch ( $this->get_current_bank() ) {
            case 'bb':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'convenio' => array(
                        'title' => __( 'Número do convênio', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Convênios de 6, 7 ou 8 digitos.', 'wcboleto' ),
                    ),
                    'contrato' => array(
                        'title' => __( 'Número do contrato', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Número do contrato.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Wallet code.', 'wcboleto' )
                    ),
                    'variacao_carteira' => array(
                        'title' => __( 'Variação da Carteira (opcional)', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Variação da Carteira com traço.', 'wcboleto' )
                    ),
                    'formatacao_convenio' => array(
                        'title' => __( 'Formatação do Convênio', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( '8 para Convênio com 8 dígitos, 7 para Convênio com 7 dígitos, ou 6 para Convênio com 6 dígitos.', 'wcboleto' )
                    ),
                    'formatacao_nosso_numero' => array(
                        'title' => __( 'Formatação do Nosso Número', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Usado apenas para Convênio com 6 dígitos (informe 1 caso o Nosso Número for de até 5 dígitos ou 2 para opção de até 17 dígitos.', 'wcboleto' ),
                        'default' => 2
                    )
                );
                break;
            case 'bradesco':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'agencia_dv' => array(
                        'title' => __( 'Agency digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'conta_dv' => array(
                        'title' => __( 'Account digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'conta_cedente' => array(
                        'title' => __( 'Conta do cedente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Conta cedente sem digito (apenas números).', 'wcboleto' ),
                    ),
                    'conta_cedente_dv' => array(
                        'title' => __( 'Conta do cedente digito', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( '03 or 06.', 'wcboleto' )
                    )
                );
                break;
            case 'cef':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'conta_dv' => array(
                        'title' => __( 'Account digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'conta_cedente' => array(
                        'title' => __( 'Conta do cedente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Conta cedente sem digito. Utilize apenas números.', 'wcboleto' ),
                    ),
                    'conta_cedente_dv' => array(
                        'title' => __( 'Conta do cedente digito', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Digito da conta cedente.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Utilize <code>SR</code> para <strong>Sem Registro</strong> ou <code>CR</code> para <strong>Com Registro</strong>. Nota: Confirme esta informação com o seu gerente.', 'wcboleto' ),
                        'default' => 'SR'
                    ),
                    'inicio_nosso_numero' => array(
                        'title' => __( 'Início do Nosso Número', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Utilize <code>80, 81 ou 82</code> para <strong>Sem Registro</strong> ou <code>90</code> para <strong>Com Registro</strong>. Nota: Confirme esta informação com o seu gerente.', 'wcboleto' ),
                        'default' => '80'
                    )
                );
                break;
            case 'cef_sigcb':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'conta_dv' => array(
                        'title' => __( 'Account digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'conta_cedente' => array(
                        'title' => __( 'Conta do cedente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Conta cedente com 6 digitos. Utilize apenas números.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Utilize <code>SR</code> para <strong>Sem Registro</strong> ou <code>CR</code> para <strong>Com Registro</strong>. Nota: Confirme esta informação com o seu gerente.', 'wcboleto' ),
                        'default' => 'SR'
                    )
                );
                break;
            case 'cef_sinco':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'conta_dv' => array(
                        'title' => __( 'Account digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'conta_cedente' => array(
                        'title' => __( 'Conta do cedente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Conta cedente sem digito. Utilize apenas números.', 'wcboleto' ),
                    ),
                    'conta_cedente_dv' => array(
                        'title' => __( 'Conta do cedente digito', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Digito da conta cedente.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Utilize <code>SR</code> para <strong>Sem Registro</strong> ou <code>CR</code> para <strong>Com Registro</strong>. Nota: Confirme esta informação com o seu gerente.', 'wcboleto' ),
                        'default' => 'SR'
                    ),
                );
                break;
            case 'hsbc':
                $fields = array(
                    'codigo_cedente' => array(
                        'title' => __( 'Código do cedente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Código do cedente com apenas 7 digitos.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Sempre CNR.', 'wcboleto' ),
                        'default' => 'CNR'
                    )
                );
                break;
            case 'itau':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'conta_dv' => array(
                        'title' => __( 'Account digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Insert the code (175, 174, 104, 109, 178, or 157).', 'wcboleto' ),
                    )
                );
                break;
            case 'nossacaixa':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta_cedente' => array(
                        'title' => __( 'Conta Cedente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Conta do cedente sem digito e com apenas 6 digitos.', 'wcboleto' ),
                    ),
                    'conta_cedente_dv' => array(
                        'title' => __( 'Conta Cedente digito', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Utilize 5 para Cobrança Direta ou 1 para Cobrança Simples.', 'wcboleto' )
                    ),
                    'modalidade_conta' => array(
                        'title' => __( 'Modalidade da conta', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( '02 posições.', 'wcboleto' ),
                    )
                );
                break;
            case 'real':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Wallet code.', 'wcboleto' )
                    )
                );
                break;
            case 'santander':
                $fields = array(
                    'codigo_cliente' => array(
                        'title' => __( 'Código do Cliente', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Código do Cliente (PSK) com apenas 7 digitos.', 'wcboleto' ),
                    ),
                    'ponto_venda' => array(
                        'title' => __( 'Ponto de venda (Agência)', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agencia number.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Cobrança Simples - SEM Registro.', 'wcboleto' )
                    ),
                    'carteira_descricao' => array(
                        'title' => __( 'Descrição da Carteira', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Descrição da Carteira.', 'wcboleto' ),
                        'default' => 'COBRANÇA SIMPLES - CSR'
                    )
                );
                break;
            case 'unibanco':
                $fields = array(
                    'agencia' => array(
                        'title' => __( 'Agency', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Agency number without digit.', 'wcboleto' ),
                    ),
                    'conta' => array(
                        'title' => __( 'Account', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Account number without digit.', 'wcboleto' ),
                    ),
                    'conta_dv' => array(
                        'title' => __( 'Account digit', 'wcboleto' ),
                        'type' => 'text'
                    ),
                    'codigo_cliente' => array(
                        'title' => __( 'Client code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Client code.', 'wcboleto' ),
                    ),
                    'carteira' => array(
                        'title' => __( 'Wallet code', 'wcboleto' ),
                        'type' => 'text',
                        'description' => __( 'Wallet code.', 'wcboleto' )
                    )
                );
                break;

            default:
                $fields = array();
                break;
        }

        return $fields;
    }

    /**
     * Process the payment and return the result.
     *
     * @param int    $order_id Order ID.
     *
     * @return array           Redirect.
     */
    public function process_payment( $order_id ) {
        global $woocommerce;

        $order = new WC_Order( $order_id );

        // Mark as on-hold (we're awaiting the boleto).
        $order->update_status( 'on-hold', __( 'Awaiting boleto payment', 'wcboleto' ) );

        // Generates boleto data.
        $this->generate_boleto_data( $order );

        // Reduce stock levels.
        $order->reduce_order_stock();

        // Remove cart.
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect.
        return array(
            'result'    => 'success',
            'redirect'  => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) )
        );
    }

    /**
     * Output for the order received page.
     *
     * @return string Thank You message.
     */
    public function thankyou_page() {
        $html = '<div class="woocommerce-message">';
        $html .= sprintf( '<a class="button" href="%s" target="_blank">%s</a>', add_query_arg( 'ref', $_GET['key'], get_permalink( get_page_by_path( 'boleto' ) ) ), __( 'Pay the Boleto &rarr;', 'wcboleto' ) );

        $message = sprintf( __( '%sAttention!%s You will not get the ticket by Correios.', 'wcboleto' ), '<strong>', '</strong>' ) . '<br />';
        $message .= __( 'Please click the following button and pay the Boleto in your Internet Banking.', 'wcboleto' ) . '<br />';
        $message .= __( 'If you prefer, print and pay at any bank branch or home lottery.', 'wcboleto' ) . '<br />';

        $html .= apply_filters( 'wcboleto_thankyou_page_message', $message );

        $html .= '<strong style="display: block; margin-top: 15px; font-size: 0.8em">' . sprintf( __( 'Validity of the Boleto: %s.', 'wcboleto' ), date( 'd/m/Y', time() + ( $this->boleto_time * 86400 ) ) ) . '</strong>';

        $html .= '</div>';

        echo $html;
    }

    /**
     * Generate boleto data.
     *
     * @param  object $order Order object.
     *
     * @return void
     */
    public function generate_boleto_data( $order ) {
        // Boleto data.
        $data['nosso_numero']       = apply_filters( 'wcboleto_our_number', $order->id );
        $data['numero_documento']   = apply_filters( 'wcboleto_document_number', $order->id );
        $data['data_vencimento']    = date( 'd/m/Y', time() + ( $this->boleto_time * 86400 ) );
        $data['data_documento']     = date( 'd/m/Y' );
        $data['data_processamento'] = date( 'd/m/Y' );
        $data['valor_boleto']       = number_format( $order->order_total, 2, ',', '' );

        // Client data.
        $data['sacado']    = $order->billing_first_name . ' ' . $order->billing_last_name;
        $data['endereco1'] = ! empty( $order->billing_address_2 ) ? $order->billing_address_1 . ', ' . $order->billing_address_2 : $order->billing_address_1;
        $data['endereco2'] = sprintf( __( '%s - %s - Zip Code: %s', 'wcboleto' ), $order->billing_city, $order->billing_state, $order->billing_postcode );

        update_post_meta( $order->id, 'wc_boleto_data', $data );
    }

    /**
     * Add content to the WC emails.
     *
     * @param  object $order         Order object.
     * @param  bool   $sent_to_admin Send to admin.
     *
     * @return string                Billet instructions.
     */
    function email_instructions( $order, $sent_to_admin ) {

        if ( $sent_to_admin )
            return;

        if ( 'on-hold' !== $order->status )
            return;

        if ( 'boleto' !== $order->payment_method )
            return;

        $html = '<h2>' . __( 'Payment', 'wcboleto' ) . '</h2>';

        $html .= '<p class="order_details">';

        $message = sprintf( __( '%sAttention!%s You will not get the ticket by Correios.', 'wcboleto' ), '<strong>', '</strong>' ) . '<br />';
        $message .= __( 'Please click the following button and pay the Boleto in your Internet Banking.', 'wcboleto' ) . '<br />';
        $message .= __( 'If you prefer, print and pay at any bank branch or home lottery.', 'wcboleto' ) . '<br />';

        $html .= apply_filters( 'wcboleto_email_instructions', $message );

        $html .= '<br />' . sprintf( '<a class="button" href="%s" target="_blank">%s</a>', add_query_arg( 'ref', $order->order_custom_fields['_order_key'][0], get_permalink( get_page_by_path( 'boleto' ) ) ), __( 'Pay the Boleto &rarr;', 'wcboleto' ) ) . '<br />';

        $html .= '<strong style="font-size: 0.8em">' . sprintf( __( 'Validity of the Boleto: %s.', 'wcboleto' ), date( 'd/m/Y', time() + ( $this->boleto_time * 86400 ) ) ) . '</strong>';

        $html .= '</p>';

        echo $html;
    }

}
