<?php
/**
 * DH_WooCommerce v1.3
 * BUG FIX: Orden queda en 'pending' al enviar la URL de pago.
 *   → WooCommerce sólo permite pagar órdenes 'pending' y 'failed' por defecto.
 *   → La transición a 'on-hold' la hace el gateway (BACS) automáticamente.
 * BUG FIX: Sesión de guest correctamente inicializada.
 * NUEVO: Precio variable por medida (validado server-side desde DH_Settings).
 */

defined( 'ABSPATH' ) || exit;

class DH_WooCommerce {

    public function __construct() {
        // Hooks de estado
        add_action( 'woocommerce_order_status_cancelled',  array( $this, 'on_order_cancelled' ), 10, 1 );
        add_action( 'woocommerce_order_status_refunded',   array( $this, 'on_order_cancelled' ), 10, 1 );
        add_action( 'woocommerce_order_status_failed',     array( $this, 'on_order_cancelled' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed',  array( $this, 'on_order_completed' ), 10, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_completed' ), 10, 1 );
        // AJAX
        add_action( 'wp_ajax_dh_registrar',        array( $this, 'ajax_registrar' ) );
        add_action( 'wp_ajax_nopriv_dh_registrar', array( $this, 'ajax_registrar' ) );
    }

    // ─────────────────────────────────────────────
    public static function sync_product( $taller_id ) {
        if ( ! class_exists( 'WooCommerce' ) ) return 0;
        $meta       = DH_Talleres::get_taller_meta( $taller_id );
        $product_id = $meta['product_id'];
        $product    = ( $product_id && get_post( $product_id ) ) ? wc_get_product( $product_id ) : new WC_Product_Simple();
        $product->set_name( get_the_title( $taller_id ) . ' — Inscripción' );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_price( $meta['precio_sena'] );
        $product->set_regular_price( $meta['precio_sena'] );
        $product->set_sold_individually( true );
        $product->set_virtual( true );
        $product->set_manage_stock( false );
        $product->set_downloadable( false );
        $product->set_tax_status( 'none' );
        $product->set_reviews_allowed( false );
        $product->save();
        update_post_meta( $taller_id,         '_dh_product_id', $product->get_id() );
        update_post_meta( $product->get_id(), '_dh_taller_id',  $taller_id );
        return $product->get_id();
    }

    // ─────────────────────────────────────────────
    public function on_order_cancelled( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_meta( '_dh_procesado' ) ) return;
        if ( $order->get_meta( '_dh_cupo_repuesto' ) ) return;
        foreach ( $order->get_items() as $item ) {
            $taller_id = $item->get_meta( '_dh_taller_id' );
            if ( $taller_id ) DH_Talleres::reponer_cupo( $taller_id, $item->get_meta( '_dh_turno' ) );
        }
        DH_Talleres::actualizar_estado_inscripcion( $order_id, 'cancelado' );
        $order->update_meta_data( '_dh_cupo_repuesto', '1' );
        $order->save();
    }

    public function on_order_completed( $order_id ) {
        DH_Talleres::actualizar_estado_inscripcion( $order_id, 'confirmado' );
    }

    // ─────────────────────────────────────────────
    // AJAX: REGISTRAR DESDE FRONTEND
    // ─────────────────────────────────────────────
    public function ajax_registrar() {
        if ( ! check_ajax_referer( 'dh_registrar_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'msg' => 'Sesión expirada. Recargá la página e intentá de nuevo.' ) );
        }

        $taller_id = absint( $_POST['taller_id'] ?? 0 );
        $turno     = sanitize_text_field( $_POST['turno']     ?? '' );
        $tipo_pago = sanitize_text_field( $_POST['tipo_pago'] ?? '' );
        $nombre    = sanitize_text_field( $_POST['nombre']    ?? '' );
        $email     = sanitize_email( $_POST['email']          ?? '' );
        $telefono  = sanitize_text_field( $_POST['telefono']  ?? '' );
        $medida    = sanitize_text_field( $_POST['variante_medida'] ?? '' );

        // Validaciones
        if ( ! $taller_id )                                                  wp_send_json_error( array( 'msg' => 'No se pudo identificar el taller. Recargá la página.' ) );
        if ( ! in_array( $turno, array( 'matutino', 'vespertino' ), true ) ) wp_send_json_error( array( 'msg' => 'Seleccioná un turno.' ) );
        if ( ! in_array( $tipo_pago, array( 'sena', 'total' ), true ) )      wp_send_json_error( array( 'msg' => 'Seleccioná el tipo de pago.' ) );
        if ( ! $nombre )                                                      wp_send_json_error( array( 'msg' => 'Ingresá tu nombre completo.' ) );
        if ( ! is_email( $email ) )                                           wp_send_json_error( array( 'msg' => 'Ingresá un correo electrónico válido.' ) );

        // Verificar cupos
        $meta     = DH_Talleres::get_taller_meta( $taller_id );
        $cupo_key = ( 'matutino' === $turno ) ? 'cupos_matutino' : 'cupos_vespertino';
        if ( $meta[ $cupo_key ] <= 0 ) {
            wp_send_json_error( array( 'msg' => 'Los cupos para este turno están completos.' ) );
        }

        // ── Calcular precio server-side ──────────────────────
        // 1. Precio base del taller
        $precio_sena  = (float) $meta['precio_sena'];
        $precio_total = (float) $meta['precio_total'];

        // 2. Si hay medida seleccionada, buscar su precio en la configuración
        if ( $medida ) {
            $medidas_cfg = DH_Talleres::get_medidas_con_precios( $taller_id );
            foreach ( $medidas_cfg as $m ) {
                if ( $m['nombre'] === $medida ) {
                    if ( (float) $m['precio_sena']  > 0 ) $precio_sena  = (float) $m['precio_sena'];
                    if ( (float) $m['precio_total'] > 0 ) $precio_total = (float) $m['precio_total'];
                    break;
                }
            }
        }
        $precio = ( 'sena' === $tipo_pago ) ? $precio_sena : $precio_total;

        // Recoger variantes
        $variantes = array();
        foreach ( array( 'color', 'tipo_lana', 'micras', 'medida' ) as $vk ) {
            $v = sanitize_text_field( $_POST[ 'variante_' . $vk ] ?? '' );
            if ( $v ) $variantes[ $vk ] = $v;
        }

        // Producto WC
        $product_id = $meta['product_id'] ?: self::sync_product( $taller_id );
        if ( ! $product_id ) {
            wp_send_json_error( array( 'msg' => 'Error al configurar el producto. Contactá a Dolce Home.' ) );
        }

        // ── Crear orden WooCommerce ──────────────────────────
        $order = wc_create_order();
        if ( is_wp_error( $order ) ) {
            wp_send_json_error( array( 'msg' => 'Error al crear el pedido. Intentá nuevamente.' ) );
        }

        // Billing completo (requerido por WC para validación)
        $nombre_parts = explode( ' ', $nombre, 2 );
        $order->set_billing_first_name( $nombre_parts[0] );
        $order->set_billing_last_name( $nombre_parts[1] ?? '.' );
        $order->set_billing_email( $email );
        $order->set_billing_phone( $telefono ?: '000000000' );
        $order->set_billing_address_1( $meta['direccion'] ?: ( $meta['ubicacion'] ?: 'Uruguay' ) );
        $order->set_billing_city( 'Montevideo' );
        $order->set_billing_country( 'UY' );
        $order->set_billing_postcode( '11000' );

        // Item
        $turno_label   = ucfirst( $turno );
        $tipo_label    = ( 'sena' === $tipo_pago ) ? 'Seña' : 'Total';
        $taller_titulo = get_the_title( $taller_id );

        $item = new WC_Order_Item_Product();
        $item->set_product_id( $product_id );
        $item->set_name( "{$taller_titulo} — {$tipo_label} · {$turno_label}" . ( $medida ? " · {$medida}" : '' ) );
        $item->set_quantity( 1 );
        $item->set_subtotal( $precio );
        $item->set_total( $precio );
        $item->add_meta_data( '_dh_taller_id',    $taller_id, true );
        $item->add_meta_data( '_dh_turno',        $turno,     true );
        $item->add_meta_data( '_dh_tipo_pago',    $tipo_pago, true );
        $item->add_meta_data( '_dh_nombre',       $nombre,    true );
        $item->add_meta_data( '_dh_email',        $email,     true );
        $item->add_meta_data( '_dh_telefono',     $telefono,  true );
        foreach ( $variantes as $vk => $vv ) {
            $item->add_meta_data( '_dh_' . $vk, $vv, true );
        }
        $item->save();
        $order->add_item( $item );
        $order->calculate_totals();

        // ── Estado PENDING ───────────────────────────────────
        // CRÍTICO: mantener 'pending' para que la página de pago funcione.
        // El gateway (ej. BACS/transferencia) cambia automáticamente a 'on-hold'
        // cuando el cliente confirma el pago. Cambiar a 'on-hold' acá bloquea el checkout.
        $order->set_status( 'pending', 'Pedido creado. Esperando que el cliente complete el pago.' );

        // Marcar como procesado y guardar
        $order->update_meta_data( '_dh_procesado', '1' );
        $order->save();
        $order_id_val = $order->get_id();

        // ── Reducir cupo ──────────────────────────────────────
        $ok = DH_Talleres::reducir_cupo( $taller_id, $turno );
        if ( ! $ok ) {
            $order->set_status( 'cancelled', 'Cupos agotados durante el registro.' );
            $order->save();
            wp_send_json_error( array( 'msg' => 'Los cupos se agotaron mientras completabas el formulario. Intentá con otro turno.' ) );
        }

        // ── Guardar inscripción ───────────────────────────────
        $inscripcion_id = DH_Talleres::guardar_inscripcion( array(
            'taller_id'     => $taller_id,
            'order_id'      => $order_id_val,
            'turno'         => $turno,
            'tipo_pago'     => $tipo_pago,
            'tipo_producto' => $meta['tipo_producto'],
            'nombre'        => $nombre,
            'email'         => $email,
            'telefono'      => $telefono,
            'variantes'     => $variantes,
            'estado'        => 'pendiente',
        ) );

        $order->update_meta_data( '_dh_inscripcion_id', $inscripcion_id );
        $order->save();

        // ── Sesión WC para guest ──────────────────────────────
        $this->set_guest_order_session( $order_id_val );

        // ── Email ─────────────────────────────────────────────
        if ( $inscripcion_id ) {
            DH_Email::enviar_confirmacion( $inscripcion_id, $order_id_val );
        }

        // ── Respuesta al frontend ─────────────────────────────
        $fecha_raw  = $meta['fecha'];
        $fecha      = $fecha_raw ? date_i18n( 'l j \d\e F \d\e Y', strtotime( $fecha_raw ) ) : '';

        wp_send_json_success( array(
            'order_id'      => $order_id_val,
            'pay_url'       => add_query_arg(
                array(
                    'pay_for_order' => 'true',
                    'key'           => $order->get_order_key(),
                ),
                $order->get_checkout_payment_url()
            ),
            'google_cal'    => DH_Email::google_calendar_url( $taller_id, $turno ),
            'ics_url'       => admin_url( 'admin-ajax.php?action=dh_download_ics&taller_id=' . $taller_id . '&turno=' . $turno ),
            'taller_titulo' => $taller_titulo,
            'taller_fecha'  => ucfirst( $fecha ),
            'ubicacion'     => $meta['ubicacion'],
            'direccion'     => $meta['direccion'],
            'maps_url'      => $meta['maps_url'],
            'turno_label'   => $turno_label,
            'pago_label'    => $tipo_label,
            'precio'        => $precio,
            'medida'        => $medida,
            'variantes'     => $variantes,
            'nombre'        => $nombre,
            'email'         => $email,
        ) );
    }

    /**
     * Establece la sesión WC para que un guest pueda acceder a la página de pago.
     * WooCommerce verifica 'order_awaiting_payment' en sesión para mostrar la página de pago.
     */
    private function set_guest_order_session( $order_id ) {
        try {
            // Inicializar la sesión de WC si no existe
            if ( ! WC()->session ) {
                $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
                WC()->session = new $session_class();
                WC()->session->init();
            }

            if ( ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }

            // Esto permite que guests accedan a la página de pago
            WC()->session->set( 'order_awaiting_payment', $order_id );

            // También guardar en la orden para acceso por key
            $order = wc_get_order( $order_id );
            if ( $order ) {
                // La order key permite acceso por URL: ?pay_for_order=true&key=xxx
                // Esto ya lo genera WC automáticamente al crear la orden
                $order->save();
            }
        } catch ( Exception $e ) {
            error_log( 'DH Talleres session error: ' . $e->getMessage() );
        }
    }
}
