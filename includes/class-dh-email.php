<?php
/**
 * Clase de emails:
 * – Correo al alumno al registrarse (confirmación de inscripción + datos de pago)
 * – Correo al admin al registrarse un nuevo alumno
 */

defined( 'ABSPATH' ) || exit;

class DH_Email {

    /**
     * Envía email de confirmación al alumno y notificación al admin.
     */
    public static function enviar_confirmacion( $inscripcion_id, $order_id = 0 ) {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dh_inscripciones WHERE id = %d",
            $inscripcion_id
        ) );

        if ( ! $row ) return;

        $taller_id  = $row->taller_id;
        $meta       = DH_Talleres::get_taller_meta( $taller_id );
        $taller_titulo = get_the_title( $taller_id );

        $fecha_raw  = $meta['fecha'];
        $fecha      = $fecha_raw ? date_i18n( 'l j \d\e F \d\e Y', strtotime( $fecha_raw ) ) : 'A confirmar';
        $turno_label = 'matutino' === $row->turno ? '☀️ Matutino' : '🌇 Vespertino';
        $pago_label  = 'sena'    === $row->tipo_pago ? 'Seña' : 'Total';
        $precio      = 'sena'   === $row->tipo_pago ? $meta['precio_sena'] : $meta['precio_total'];

        $variantes = array();
        if ( ! empty( $row->variantes ) ) {
            $variantes = json_decode( $row->variantes, true ) ?: array();
        }

        // ── Email al alumno ─────────────────────────────
        $asunto_alumno = "✅ Inscripción confirmada — {$taller_titulo}";
        $cuerpo_alumno = self::template_alumno( array(
            'nombre'        => $row->nombre,
            'email'         => $row->email,
            'taller_titulo' => $taller_titulo,
            'fecha'         => ucfirst( $fecha ),
            'turno'         => $turno_label,
            'pago_label'    => $pago_label,
            'precio'        => $precio,
            'ubicacion'     => $meta['ubicacion'],
            'direccion'     => $meta['direccion'] ?? '',
            'maps_url'      => $meta['maps_url'] ?? '',
            'variantes'     => $variantes,
            'order_id'      => $order_id,
            'pay_url'       => $order_id ? wc_get_order( $order_id )->get_checkout_payment_url() : '',
            'tipo_registro' => $order_id ? 'woocommerce' : 'manual',
        ) );

        self::send( $row->email, $asunto_alumno, $cuerpo_alumno );

        // ── Email al admin ──────────────────────────────
        $admin_email  = get_option( 'admin_email' );
        $asunto_admin = "🆕 Nueva inscripción — {$taller_titulo} ({$row->nombre})";
        $cuerpo_admin = self::template_admin( array(
            'nombre'        => $row->nombre,
            'email'         => $row->email,
            'telefono'      => $row->telefono,
            'taller_titulo' => $taller_titulo,
            'fecha'         => ucfirst( $fecha ),
            'turno'         => $turno_label,
            'pago_label'    => $pago_label,
            'precio'        => $precio,
            'variantes'     => $variantes,
            'order_id'      => $order_id,
            'inscripcion_id' => $inscripcion_id,
        ) );

        self::send( $admin_email, $asunto_admin, $cuerpo_admin );
    }

    /**
     * Envía el email usando wp_mail con Content-Type HTML.
     */
    private static function send( $to, $subject, $body ) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Dolce Home Talleres <' . get_option( 'admin_email' ) . '>',
        );
        wp_mail( $to, $subject, $body, $headers );
    }

    // ──────────────────────────────────────────────
    // TEMPLATE: Email al alumno
    // ──────────────────────────────────────────────
    private static function template_alumno( $d ) {
        $pay_btn = '';
        if ( ! empty( $d['pay_url'] ) ) {
            $pay_btn = '
            <div style="text-align:center;margin:28px 0;">
                <a href="' . esc_url( $d['pay_url'] ) . '"
                   style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#8B5E3C,#D4A96A);
                          color:#fff;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;
                          box-shadow:0 4px 16px rgba(139,94,60,.3);">
                    💳 Completar el pago
                </a>
            </div>';
        }

        $variantes_html = self::render_variantes( $d['variantes'] );
        $ubicacion_html = self::render_ubicacion( $d );

        $monto = number_format( (float) $d['precio'], 0, ',', '.' );

        return self::layout( '
            <h2 style="color:#8B5E3C;margin:0 0 6px;">¡Inscripción registrada! 🎉</h2>
            <p style="color:#666;margin:0 0 24px;">Hola <strong>' . esc_html( $d['nombre'] ) . '</strong>, tu lugar en el taller está reservado.</p>

            ' . self::card_taller( $d ) . '

            <div style="background:#fdf5ea;border:1px solid #f0dfc0;border-radius:10px;padding:20px;margin:20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;width:40%;">👤 Nombre</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['nombre'] ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">📧 Email</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['email'] ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">⏰ Turno</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['turno'] ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">💰 Tipo de pago</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['pago_label'] ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">💵 Monto</td>
                        <td style="padding:6px 0;font-weight:700;color:#8B5E3C;font-size:18px;">$' . $monto . '</td></tr>
                </table>
            </div>

            ' . $variantes_html . '
            ' . $ubicacion_html . '
            ' . $pay_btn . '

            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin-top:20px;">
                <p style="margin:0;font-size:13px;color:#0369a1;">
                    <span style="font-size:13px;">ℹ️</span> <strong>¿Dudas?</strong> Respondé este correo o contactanos por WhatsApp.<br>
                    Tu inscripción quedará confirmada una vez que acreditemos el pago.
                </p>
            </div>
        ' );
    }

    // ──────────────────────────────────────────────
    // TEMPLATE: Email al admin
    // ──────────────────────────────────────────────
    private static function template_admin( $d ) {
        $variantes_html = self::render_variantes( $d['variantes'] );
        $order_link     = $d['order_id']
            ? '<a href="' . get_edit_post_link( $d['order_id'] ) . '" style="color:#8B5E3C;">Ver pedido #' . $d['order_id'] . '</a>'
            : 'Registro manual';
        $alumnos_link = admin_url( 'admin.php?page=dh-alumnos' );
        $monto = number_format( (float) $d['precio'], 0, ',', '.' );

        return self::layout( '
            <h2 style="color:#8B5E3C;margin:0 0 6px;">Nueva inscripción recibida 🆕</h2>
            <p style="color:#666;margin:0 0 24px;">Se registró un nuevo alumno en <strong>' . esc_html( $d['taller_titulo'] ) . '</strong></p>

            ' . self::card_taller( $d ) . '

            <div style="background:#fdf5ea;border:1px solid #f0dfc0;border-radius:10px;padding:20px;margin:20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;width:40%;">👤 Nombre</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['nombre'] ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">📧 Email</td>
                        <td style="padding:6px 0;"><a href="mailto:' . esc_attr( $d['email'] ) . '" style="color:#8B5E3C;">' . esc_html( $d['email'] ) . '</a></td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">📱 Teléfono</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['telefono'] ?: '—' ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">⏰ Turno</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['turno'] ) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">💰 Pago</td>
                        <td style="padding:6px 0;font-weight:600;">' . esc_html( $d['pago_label'] ) . ' — $' . $monto . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">🛒 Pedido</td>
                        <td style="padding:6px 0;">' . $order_link . '</td></tr>
                    <tr><td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;">#️⃣ Inscripción</td>
                        <td style="padding:6px 0;font-weight:600;">#' . $d['inscripcion_id'] . '</td></tr>
                </table>
            </div>

            ' . $variantes_html . '

            <div style="text-align:center;margin:24px 0;">
                <a href="' . esc_url( $alumnos_link ) . '"
                   style="display:inline-block;padding:12px 28px;background:#8B5E3C;color:#fff;
                          text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                    Ver panel de alumnos
                </a>
            </div>
        ' );
    }

    // ──────────────────────────────────────────────
    // Helpers de plantilla
    // ──────────────────────────────────────────────

    private static function card_taller( $d ) {
        return '
        <div style="background:#8B5E3C;border-radius:10px;padding:20px;color:#fff;margin-bottom:0;">
            <div style="font-size:20px;line-height:1;margin-bottom:8px;">🧶</div>
            <div style="font-size:18px;font-weight:800;margin-bottom:10px;">' . esc_html( $d['taller_titulo'] ) . '</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-size:13px;opacity:.8;padding:3px 0;">📅 Fecha</td>
                    <td style="font-size:13px;font-weight:600;padding:3px 0;">' . esc_html( $d['fecha'] ) . '</td>
                </tr>
                ' . ( $d['ubicacion'] ? '<tr><td style="font-size:13px;opacity:.8;padding:3px 0;">📍 Lugar</td>
                    <td style="font-size:13px;font-weight:600;padding:3px 0;">' . esc_html( $d['ubicacion'] ) . '</td></tr>' : '' ) . '
            </table>
        </div>';
    }

    private static function render_variantes( $variantes ) {
        if ( empty( $variantes ) ) return '';
        // Labels con HTML interno de confianza — NO usar esc_html sobre ellos
        $labels = array(
            'color'     => '<span style="font-size:16px;line-height:1;vertical-align:middle;">🎨</span> Color',
            'tipo_lana' => '<span style="font-size:16px;line-height:1;vertical-align:middle;">🧶</span> Tipo de lana',
            'micras'    => '<span style="font-size:16px;line-height:1;vertical-align:middle;">🔬</span> Micras',
            'medida'    => '<span style="font-size:16px;line-height:1;vertical-align:middle;">📏</span> Medida',
        );
        $rows = '';
        foreach ( $variantes as $k => $v ) {
            if ( empty( $v ) ) continue;
            // $label es HTML interno seguro; $v es el valor del usuario → sí escapar
            $label = isset( $labels[ $k ] ) ? $labels[ $k ] : esc_html( ucfirst( $k ) );
            $rows .= '<tr>
                <td style="padding:6px 0;color:#666;font-size:13px;line-height:1.5;width:40%;">' . $label . '</td>
                <td style="padding:6px 0;font-weight:600;">' . esc_html( $v ) . '</td>
            </tr>';
        }
        if ( ! $rows ) return '';
        return '
        <div style="background:#fafafa;border:1px solid #e8e0d8;border-radius:10px;padding:20px;margin:16px 0;">
            <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#8B5E3C;margin-bottom:10px;">
                Selecciones de material
            </div>
            <table width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>
        </div>';
    }

    private static function render_ubicacion( $d ) {
        if ( empty( $d['ubicacion'] ) && empty( $d['direccion'] ) ) return '';
        $maps_link = '';
        if ( ! empty( $d['maps_url'] ) ) {
            $maps_link = '<br><a href="' . esc_url( $d['maps_url'] ) . '" target="_blank"
                            style="color:#8B5E3C;font-weight:600;">📌 Ver en Google Maps</a>';
        }
        return '
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin-top:16px;">
            <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:6px;">📍 Ubicación del taller</div>
            <div style="font-size:14px;color:#333;">' . esc_html( $d['ubicacion'] ) . '</div>
            ' . ( $d['direccion'] ? '<div style="font-size:13px;color:#555;margin-top:4px;">' . esc_html( $d['direccion'] ) . '</div>' : '' ) . '
            ' . $maps_link . '
        </div>';
    }

    // ──────────────────────────────────────────────
    // Layout base del email
    // ──────────────────────────────────────────────
    private static function layout( $content ) {
        $site_name = get_bloginfo( 'name' );
        $year      = date( 'Y' );
        return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dolce Home Talleres</title></head>
<body style="margin:0;padding:0;background:#f5f0eb;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0eb;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:600px;width:100%;">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#8B5E3C,#D4A96A);padding:28px 32px;text-align:center;">
        <div style="font-size:28px;line-height:1;margin-bottom:8px;">🧶</div>
        <div style="color:#fff;font-size:22px;font-weight:800;letter-spacing:1px;">Dolce Home</div>
        <div style="color:rgba(255,255,255,.8);font-size:13px;margin-top:4px;">Talleres de Mantas XXL</div>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:32px;">
        ' . $content . '
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#fdf9f5;border-top:1px solid #f0e8de;padding:20px 32px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#999;">
          © ' . $year . ' ' . esc_html( $site_name ) . ' · Talleres de Mantas XXL<br>
          <a href="https://dolcehome.uy" style="color:#8B5E3C;">dolcehome.uy</a>
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }

    // ──────────────────────────────────────────────
    // Generar archivo ICS para calendario
    // ──────────────────────────────────────────────
    public static function generar_ics( $taller_id, $turno ) {
        $meta          = DH_Talleres::get_taller_meta( $taller_id );
        $titulo        = get_the_title( $taller_id );
        $fecha_raw     = $meta['fecha'];

        if ( ! $fecha_raw ) return '';

        // Horarios por turno (ajustables)
        $horarios = array(
            'matutino'   => array( 'inicio' => '09:00', 'fin' => '13:00' ),
            'vespertino' => array( 'inicio' => '14:00', 'fin' => '18:00' ),
        );
        $h = $horarios[ $turno ] ?? $horarios['matutino'];

        $dtstart = date( 'Ymd\THis', strtotime( $fecha_raw . ' ' . $h['inicio'] ) );
        $dtend   = date( 'Ymd\THis', strtotime( $fecha_raw . ' ' . $h['fin'] ) );
        $uid     = 'dh-taller-' . $taller_id . '-' . $turno . '@dolcehome.uy';
        $ubicacion = addcslashes( $meta['ubicacion'] . ( $meta['direccion'] ? ', ' . $meta['direccion'] : '' ), ',' );
        $descripcion = 'Taller de Mantas XXL Dolce Home · Turno: ' . ucfirst( $turno );

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Dolce Home//Talleres//ES\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTART:{$dtstart}\r\nDTEND:{$dtend}\r\nSUMMARY:{$titulo}\r\nDESCRIPTION:{$descripcion}\r\nLOCATION:{$ubicacion}\r\nSTATUS:CONFIRMED\r\nEND:VEVENT\r\nEND:VCALENDAR";
    }

    /**
     * Genera la URL de Google Calendar para agregar el evento.
     */
    public static function google_calendar_url( $taller_id, $turno ) {
        $meta      = DH_Talleres::get_taller_meta( $taller_id );
        $titulo    = get_the_title( $taller_id );
        $fecha_raw = $meta['fecha'];
        if ( ! $fecha_raw ) return '';

        $horarios = array(
            'matutino'   => array( '09:00', '13:00' ),
            'vespertino' => array( '14:00', '18:00' ),
        );
        $h = $horarios[ $turno ] ?? $horarios['matutino'];

        $start    = date( 'Ymd\THis', strtotime( $fecha_raw . ' ' . $h[0] ) );
        $end      = date( 'Ymd\THis', strtotime( $fecha_raw . ' ' . $h[1] ) );
        $location = $meta['ubicacion'] . ( $meta['direccion'] ? ', ' . $meta['direccion'] : '' );
        $details  = 'Taller de Mantas XXL Dolce Home · Turno: ' . ucfirst( $turno );

        return 'https://calendar.google.com/calendar/render?' . http_build_query( array(
            'action'   => 'TEMPLATE',
            'text'     => $titulo,
            'dates'    => $start . '/' . $end,
            'details'  => $details,
            'location' => $location,
        ) );
    }
}
