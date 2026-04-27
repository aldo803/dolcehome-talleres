<?php
/**
 * DH_Frontend v1.3
 * – [dh_talleres] → todos los talleres
 * – [dh_taller id="X"] → shortcode específico por taller
 * – Precio variable por medida (medidas con precio desde DH_Settings)
 */

defined( 'ABSPATH' ) || exit;

class DH_Frontend {

    public function __construct() {
        add_shortcode( 'dh_talleres',  array( $this, 'shortcode_todos' ) );
        add_shortcode( 'dh_taller',    array( $this, 'shortcode_uno' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'dh-public-style',
            DH_TALLERES_URL . 'public/css/dh-public.css',
            array(), DH_TALLERES_VERSION
        );
        wp_enqueue_script(
            'dh-public-script',
            DH_TALLERES_URL . 'public/js/dh-public.js',
            array( 'jquery' ), DH_TALLERES_VERSION, true
        );

        // Pasar opciones de todos los tipos de producto al JS
        $tipos_data = array();
        foreach ( DH_Settings::get_tipos_producto() as $tipo ) {
            $tipos_data[ $tipo['slug'] ] = $tipo;
        }

        wp_localize_script( 'dh-public-script', 'dhPublic', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'dh_registrar_nonce' ),
            'wc_checkout'   => wc_get_checkout_url(),
            'tipos_producto' => $tipos_data,
        ) );
    }

    /* ─────────────────────────────────────────────
       SHORTCODE: [dh_talleres mostrar="proximos"]
    ───────────────────────────────────────────── */
    public function shortcode_todos( $atts ) {
        $atts     = shortcode_atts( array( 'mostrar' => 'proximos' ), $atts, 'dh_talleres' );
        $talleres = DH_Talleres::get_talleres();

        if ( 'proximos' === $atts['mostrar'] ) {
            $hoy      = date( 'Y-m-d' );
            $talleres = array_filter( $talleres, function( $t ) use ( $hoy ) {
                $f = get_post_meta( $t->ID, '_dh_fecha', true );
                return ! $f || $f >= $hoy;
            } );
        }

        return $this->render_lista( $talleres );
    }

    /* ─────────────────────────────────────────────
       SHORTCODE: [dh_taller id="123"]
    ───────────────────────────────────────────── */
    public function shortcode_uno( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'dh_taller' );
        $id   = absint( $atts['id'] );
        if ( ! $id ) return '<p style="color:#c00">Shortcode inválido: falta el atributo <code>id</code>.</p>';

        $taller = get_post( $id );
        if ( ! $taller || 'dh_taller' !== $taller->post_type || 'publish' !== $taller->post_status ) {
            return '<p style="color:#c00">Taller no encontrado o no publicado.</p>';
        }

        return $this->render_lista( array( $taller ) );
    }

    /* ─────────────────────────────────────────────
       RENDER: LISTA + MODAL
    ───────────────────────────────────────────── */
    private function render_lista( $talleres ) {
        ob_start();
        if ( empty( $talleres ) ) {
            echo '<div class="dh-no-talleres"><p>No hay talleres disponibles en este momento. ¡Volvé pronto!</p></div>';
        } else {
            echo '<div class="dh-talleres-container">';
            foreach ( $talleres as $taller ) {
                echo $this->render_taller_card( $taller );
            }
            echo '</div>';
        }
        echo $this->render_modal();
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
       CARD DEL TALLER
    ───────────────────────────────────────────── */
    private function render_taller_card( $taller ) {
        $meta         = DH_Talleres::get_taller_meta( $taller->ID );
        $fecha_raw    = $meta['fecha'];
        $fecha        = $fecha_raw ? date_i18n( 'l j \d\e F \d\e Y', strtotime( $fecha_raw ) ) : '';
        $cupos_mat    = $meta['cupos_matutino'];
        $cupos_ves    = $meta['cupos_vespertino'];
        $sin_mat      = $cupos_mat <= 0;
        $sin_ves      = $cupos_ves <= 0;
        $lleno        = $sin_mat && $sin_ves;

        // Datos de variantes habilitadas para este taller
        $variantes_json = json_encode( array(
            'color'     => $meta['mostrar_color'],
            'tipo_lana' => $meta['mostrar_tipo_lana'],
            'micras'    => $meta['mostrar_micras'],
            'medida'    => $meta['mostrar_medida'],
        ) );

        // Medidas con precios (para precio dinámico en JS)
        $medidas_json = json_encode( DH_Talleres::get_medidas_con_precios( $taller->ID ) );

        ob_start();
        ?>
        <div class="dh-taller-card-public <?php echo $lleno ? 'dh-card-full' : ''; ?>">
            <?php if ( $lleno ) echo '<div class="dh-ribbon dh-ribbon-full">Cupos completos</div>'; ?>

            <div class="dh-card-body">
                <div class="dh-card-icon">🧶</div>
                <h2 class="dh-card-title"><?php echo esc_html( get_the_title( $taller->ID ) ); ?></h2>

                <?php if ( $meta['descripcion'] ) : ?>
                <p class="dh-card-desc"><?php echo nl2br( esc_html( $meta['descripcion'] ) ); ?></p>
                <?php endif; ?>

                <?php if ( $meta['nivel'] || $meta['tiempo_estimado'] ) : ?>
                <div class="dh-card-tags">
                    <?php if ( $meta['nivel'] ) echo '<span class="dh-card-tag dh-tag-nivel">🎓 ' . esc_html( $meta['nivel'] ) . '</span>'; ?>
                    <?php if ( $meta['tiempo_estimado'] ) echo '<span class="dh-card-tag dh-tag-tiempo">⏱ ' . esc_html( $meta['tiempo_estimado'] ) . '</span>'; ?>
                </div>
                <?php endif; ?>

                <?php if ( $fecha ) : ?>
                <div class="dh-card-info"><span class="dh-info-icon">📅</span><span><?php echo esc_html( ucfirst( $fecha ) ); ?></span></div>
                <?php endif; ?>
                <?php if ( $meta['ubicacion'] ) : ?>
                <div class="dh-card-info"><span class="dh-info-icon">📍</span><span><?php echo esc_html( $meta['ubicacion'] ); ?></span></div>
                <?php endif; ?>
                <?php if ( $meta['direccion'] ) : ?>
                <div class="dh-card-info dh-card-info-sm"><span class="dh-info-icon">🏠</span><span><?php echo esc_html( $meta['direccion'] ); ?></span></div>
                <?php endif; ?>
                <?php if ( $meta['maps_url'] ) : ?>
                <div class="dh-card-info">
                    <span class="dh-info-icon">🗺️</span>
                    <a href="<?php echo esc_url( $meta['maps_url'] ); ?>" target="_blank" rel="noopener" class="dh-maps-link">Ver en Google Maps</a>
                </div>
                <?php endif; ?>

                <?php if ( $meta['requisitos'] ) : ?>
                <div class="dh-card-requisitos">
                    <div class="dh-requisitos-title">📋 Información del taller</div>
                    <p class="dh-requisitos-text"><?php echo nl2br( esc_html( $meta['requisitos'] ) ); ?></p>
                </div>
                <?php endif; ?>

                <!-- Turnos -->
                <div class="dh-turnos-disponibles">
                    <div class="dh-turno-pill <?php echo $sin_mat ? 'dh-turno-full' : 'dh-turno-ok'; ?>">
                        <span>☀️ Turno Matutino</span>
                        <?php if ( $sin_mat ) : ?>
                            <span class="dh-pill-badge full">Sin cupos</span>
                        <?php else : ?>
                            <span class="dh-pill-badge available"><?php echo $cupos_mat; ?> cupo<?php echo $cupos_mat !== 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dh-turno-pill <?php echo $sin_ves ? 'dh-turno-full' : 'dh-turno-ok'; ?>">
                        <span>🌇 Turno Vespertino</span>
                        <?php if ( $sin_ves ) : ?>
                            <span class="dh-pill-badge full">Sin cupos</span>
                        <?php else : ?>
                            <span class="dh-pill-badge available"><?php echo $cupos_ves; ?> cupo<?php echo $cupos_ves !== 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Precios (se actualizan si hay medida con precio) -->
                <div class="dh-card-prices">
                    <div class="dh-price-option">
                        <span class="dh-price-label">Seña para reservar</span>
                        <span class="dh-price-amount">$<?php echo number_format( $meta['precio_sena'], 0, ',', '.' ); ?></span>
                    </div>
                    <div class="dh-price-divider">o</div>
                    <div class="dh-price-option dh-price-featured">
                        <span class="dh-price-label">Precio total</span>
                        <span class="dh-price-amount">$<?php echo number_format( $meta['precio_total'], 0, ',', '.' ); ?></span>
                    </div>
                </div>

                <!-- CTA -->
                <?php if ( $lleno ) : ?>
                    <button class="dh-btn-inscribirse dh-btn-disabled" disabled>Cupos completos</button>
                <?php else : ?>
                    <button
                        class="dh-btn-inscribirse"
                        data-taller-id="<?php echo $taller->ID; ?>"
                        data-taller-titulo="<?php echo esc_attr( get_the_title( $taller->ID ) ); ?>"
                        data-taller-fecha="<?php echo esc_attr( ucfirst( $fecha ) ); ?>"
                        data-taller-ubicacion="<?php echo esc_attr( $meta['ubicacion'] ); ?>"
                        data-taller-direccion="<?php echo esc_attr( $meta['direccion'] ); ?>"
                        data-maps-url="<?php echo esc_attr( $meta['maps_url'] ); ?>"
                        data-tipo-producto="<?php echo esc_attr( $meta['tipo_producto'] ); ?>"
                        data-precio-sena="<?php echo esc_attr( $meta['precio_sena'] ); ?>"
                        data-precio-total="<?php echo esc_attr( $meta['precio_total'] ); ?>"
                        data-cupos-mat="<?php echo $cupos_mat; ?>"
                        data-cupos-ves="<?php echo $cupos_ves; ?>"
                        data-variantes='<?php echo esc_attr( $variantes_json ); ?>'
                        data-medidas='<?php echo esc_attr( $medidas_json ); ?>'
                    >
                        Inscribirme al taller
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
       MODAL DE INSCRIPCIÓN
    ───────────────────────────────────────────── */
    private function render_modal() {
        ob_start();
        ?>
        <div id="dh-modal-overlay" class="dh-modal-overlay">
            <div class="dh-modal" id="dh-modal-box" role="dialog" aria-modal="true" aria-labelledby="dh-modal-title">
                <button class="dh-modal-close" id="dh-modal-close-btn" aria-label="Cerrar">✕</button>

                <!-- PASO 1: FORMULARIO -->
                <div id="dh-step-form">
                    <div class="dh-modal-header">
                        <span class="dh-modal-emoji">🧶</span>
                        <div>
                            <h2 id="dh-modal-title">Inscripción al taller</h2>
                            <p id="dh-modal-subtitle" class="dh-modal-subtitle"></p>
                        </div>
                    </div>

                    <form id="dh-form-inscripcion" novalidate>
                        <!-- Datos personales -->
                        <div class="dh-form-section">
                            <h4 class="dh-form-section-title">Tus datos</h4>
                            <div class="dh-form-row">
                                <div class="dh-form-group">
                                    <label for="dh_nombre">Nombre completo <span class="req">*</span></label>
                                    <input type="text" id="dh_nombre" name="nombre" placeholder="Ej: María González" required>
                                </div>
                                <div class="dh-form-group">
                                    <label for="dh_email">Correo electrónico <span class="req">*</span></label>
                                    <input type="email" id="dh_email" name="email" placeholder="tu@email.com" required>
                                </div>
                            </div>
                            <div class="dh-form-group">
                                <label for="dh_telefono">Teléfono / WhatsApp</label>
                                <input type="tel" id="dh_telefono" name="telefono" placeholder="Ej: 099 000 000">
                            </div>
                        </div>

                        <!-- Turno -->
                        <div class="dh-form-section">
                            <h4 class="dh-form-section-title">Elegí tu turno</h4>
                            <div class="dh-turno-selector">
                                <label class="dh-option-card" id="dh-opt-matutino">
                                    <input type="radio" name="turno" value="matutino">
                                    <div class="dh-option-content">
                                        <span class="dh-option-icon">☀️</span>
                                        <span class="dh-option-label">Matutino</span>
                                        <span class="dh-option-cupos" id="dh-cupos-mat-label"></span>
                                    </div>
                                </label>
                                <label class="dh-option-card" id="dh-opt-vespertino">
                                    <input type="radio" name="turno" value="vespertino">
                                    <div class="dh-option-content">
                                        <span class="dh-option-icon">🌇</span>
                                        <span class="dh-option-label">Vespertino</span>
                                        <span class="dh-option-cupos" id="dh-cupos-ves-label"></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Tipo de pago -->
                        <div class="dh-form-section">
                            <h4 class="dh-form-section-title">Tipo de pago</h4>
                            <div class="dh-pago-selector">
                                <label class="dh-option-card dh-pago-card" id="dh-opt-sena">
                                    <input type="radio" name="tipo_pago" value="sena">
                                    <div class="dh-option-content">
                                        <div>
                                            <span class="dh-option-label">💰 Seña</span>
                                            <span class="dh-pago-desc">Reserva tu lugar con la seña</span>
                                        </div>
                                        <span class="dh-pago-precio" id="dh-precio-sena-label"></span>
                                    </div>
                                </label>
                                <label class="dh-option-card dh-pago-card" id="dh-opt-total">
                                    <input type="radio" name="tipo_pago" value="total">
                                    <div class="dh-option-content">
                                        <div>
                                            <span class="dh-option-label">✅ Total</span>
                                            <span class="dh-pago-desc">Abona el precio completo</span>
                                        </div>
                                        <span class="dh-pago-precio" id="dh-precio-total-label"></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Material (variantes dinámicas) -->
                        <div class="dh-form-section" id="dh-variantes-section" style="display:none;">
                            <h4 class="dh-form-section-title">🧶 Selección de material</h4>
                            <div class="dh-variantes-grid" id="dh-variantes-grid"></div>
                        </div>

                        <div id="dh-form-error" class="dh-form-error" style="display:none;"></div>

                        <button type="submit" class="dh-btn-confirmar" id="dh-btn-confirmar">
                            <span class="dh-btn-text">Confirmar inscripción</span>
                            <span class="dh-btn-loader" style="display:none;"><span class="dh-spinner-sm"></span> Procesando…</span>
                        </button>
                        <p class="dh-form-disclaimer">Al confirmar serás redirigido al pago seguro. Tu cupo quedará reservado.</p>
                    </form>
                </div>

                <!-- PASO 2: CONFIRMACIÓN -->
                <div id="dh-step-confirmacion" style="display:none;">
                    <div class="dh-confirm-header">
                        <div class="dh-confirm-icon">🎉</div>
                        <h2>¡Inscripción registrada!</h2>
                        <p>Revisá tu correo con los datos de tu inscripción.</p>
                    </div>
                    <div class="dh-confirm-taller"  id="dh-confirm-taller"></div>
                    <div class="dh-confirm-detalles" id="dh-confirm-detalles"></div>
                    <div class="dh-confirm-variantes" id="dh-confirm-variantes" style="display:none;"></div>
                    <div class="dh-cal-section" id="dh-cal-section" style="display:none;">
                        <h4 class="dh-cal-title">📅 Agregá el taller a tu calendario</h4>
                        <div class="dh-cal-btns">
                            <a id="dh-btn-google-cal" href="#" target="_blank" rel="noopener" class="dh-cal-btn dh-cal-google">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
                                Google Calendar
                            </a>
                            <a id="dh-btn-ics" href="#" class="dh-cal-btn dh-cal-ics">📥 Descargar (.ics)</a>
                        </div>
                    </div>
                    <div class="dh-confirm-cta">
                        <a id="dh-btn-pagar" href="#" class="dh-btn-confirmar">💳 Ir a completar el pago</a>
                        <p class="dh-form-disclaimer" style="margin-top:10px;">Tu cupo está reservado. Completá el pago para confirmar tu lugar.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
