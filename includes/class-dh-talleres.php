<?php
/**
 * DH_Talleres v1.6
 * – Columna deleted_at para papelera de reciclaje de inscripciones
 * – maybe_add_columns() explícito para migraciones seguras
 * – DB fix: TEXT NULL (compatible MySQL 5.7+), columna tipo_producto
 * – tipo_producto en meta box + shortcode per-taller
 * – precios_medidas: helper para precio variable por medida
 */

defined( 'ABSPATH' ) || exit;

class DH_Talleres {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        new DH_Settings();
        new DH_Admin();
        new DH_WooCommerce();
        new DH_Frontend();
    }

    private function register_hooks() {
        add_action( 'init',                array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes',      array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post_dh_taller', array( $this, 'save_meta_boxes' ), 10, 2 );
        add_action( 'wp_ajax_dh_download_ics',        array( $this, 'download_ics' ) );
        add_action( 'wp_ajax_nopriv_dh_download_ics', array( $this, 'download_ics' ) );
    }

    /* ─────────────────────────────────────────────
       ACTIVACIÓN
    ───────────────────────────────────────────── */
    public static function activate() {
        self::create_table();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'dh_inscripciones';
        $charset = $wpdb->get_charset_collate();

        // IMPORTANTE: usar CREATE TABLE (sin IF NOT EXISTS) para que dbDelta
        // detecte y agregue columnas faltantes en upgrades.
        // TEXT NULL es compatible con todas las versiones de MySQL.
        $sql = "CREATE TABLE {$table} (
  id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  taller_id       BIGINT(20) UNSIGNED NOT NULL,
  order_id        BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  turno           VARCHAR(20) NOT NULL DEFAULT '',
  tipo_pago       VARCHAR(20) NOT NULL DEFAULT '',
  tipo_producto   VARCHAR(100) NOT NULL DEFAULT '',
  nombre          VARCHAR(150) NOT NULL DEFAULT '',
  email           VARCHAR(150) NOT NULL DEFAULT '',
  telefono        VARCHAR(50) NOT NULL DEFAULT '',
  variantes       TEXT NULL,
  notas           TEXT NULL,
  estado          VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY taller_id (taller_id),
  KEY order_id (order_id),
  KEY estado (estado)
) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( 'dh_talleres_db_version', DH_TALLERES_DB_VERSION );
    }

    /**
     * ALTER TABLE explícito para agregar columnas que dbDelta no detecta en algunas versiones.
     */
    public static function maybe_add_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'dh_inscripciones';
        $cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        if ( ! in_array( 'tipo_producto', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN tipo_producto VARCHAR(100) NOT NULL DEFAULT ''" );
        }
        if ( ! in_array( 'variantes', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN variantes TEXT NULL" );
        }
        if ( ! in_array( 'notas', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN notas TEXT NULL" );
        }
        if ( ! in_array( 'deleted_at', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL" );
        }
        update_option( 'dh_talleres_db_version', DH_TALLERES_DB_VERSION );
    }

    /* ─────────────────────────────────────────────
       CPT
    ───────────────────────────────────────────── */
    public function register_post_type() {
        register_post_type( 'dh_taller', array(
            'label'           => 'Talleres',
            'labels'          => array(
                'name'               => 'Talleres',
                'singular_name'      => 'Taller',
                'add_new'            => 'Agregar taller',
                'add_new_item'       => 'Agregar nuevo taller',
                'edit_item'          => 'Editar taller',
                'not_found'          => 'No se encontraron talleres',
                'not_found_in_trash' => 'No hay talleres en la papelera',
            ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'supports'        => array( 'title' ),
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ) );
    }

    /* ─────────────────────────────────────────────
       META BOX
    ───────────────────────────────────────────── */
    public function register_meta_boxes() {
        add_meta_box( 'dh_taller_detalles', 'Configuración del Taller',
            array( $this, 'render_meta_box' ), 'dh_taller', 'normal', 'high' );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'dh_save_taller_meta', 'dh_taller_nonce' );
        $meta  = self::get_taller_meta( $post->ID );
        $tipos = DH_Settings::get_tipos_producto();
        ?>
        <style>
            .dh-meta-tabs{display:flex;border-bottom:2px solid #e8e0d8;margin-bottom:0}
            .dh-meta-tab{padding:10px 18px;cursor:pointer;font-size:13px;font-weight:600;color:#7a7a7a;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
            .dh-meta-tab.active{color:#8B5E3C;border-bottom-color:#8B5E3C}
            .dh-meta-panel{display:none;padding:20px}
            .dh-meta-panel.active{display:block}
            .dh-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
            .dh-meta-group{display:flex;flex-direction:column;gap:5px}
            .dh-meta-group label{font-weight:600;font-size:13px;color:#1d2327}
            .dh-meta-group input,.dh-meta-group select,.dh-meta-group textarea{padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px}
            .dh-meta-group textarea{resize:vertical;min-height:70px}
            .dh-meta-section{grid-column:1/-1;margin:14px 0 0;padding:8px 12px;background:#f0f6fc;border-left:4px solid #2271b1;font-weight:700;font-size:13px;color:#2271b1;border-radius:0 4px 4px 0}
            .dh-meta-full{grid-column:1/-1}
            .dh-cupo-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
            .dh-cupo-section{background:#fafafa;border:1px solid #e0e0e0;border-radius:6px;padding:14px}
            .dh-cupo-section h4{margin:0 0 10px;font-size:13px}
            .dh-shortcode-box{grid-column:1/-1;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
            .dh-shortcode-box code{background:#dcfce7;padding:4px 10px;border-radius:4px;font-size:13px;color:#166534;cursor:pointer}
            .dh-maps-preview{margin-top:8px}
            .dh-maps-preview iframe{border:0;border-radius:6px;width:100%;height:180px}
        </style>

        <div class="dh-meta-tabs">
            <div class="dh-meta-tab active" onclick="dhTab('general',this)">📋 General</div>
            <div class="dh-meta-tab" onclick="dhTab('ubicacion',this)">📍 Ubicación</div>
            <div class="dh-meta-tab" onclick="dhTab('cupos',this)">👥 Cupos</div>
            <div class="dh-meta-tab" onclick="dhTab('variantes',this)">🧶 Material</div>
        </div>

        <!-- PANEL: General -->
        <div id="dh-panel-general" class="dh-meta-panel active">
            <div class="dh-meta-grid">
                <?php if ( $post->post_status === 'publish' ) : ?>
                <div class="dh-shortcode-box">
                    <div>
                        <strong>📌 Shortcode de este taller:</strong>
                        <code onclick="dhCopyShortcode(this)">[dh_taller id="<?php echo $post->ID; ?>"]</code>
                        <span style="font-size:11px;color:#888;margin-left:8px;">Click para copiar</span>
                    </div>
                    <a href="<?php echo get_admin_url().'post-new.php?post_type=page'; ?>" target="_blank" class="button button-small">Nueva página</a>
                </div>
                <?php endif; ?>
                <div class="dh-meta-group">
                    <label for="dh_fecha">📅 Fecha del taller *</label>
                    <input type="date" id="dh_fecha" name="dh_fecha" value="<?php echo esc_attr($meta['fecha']); ?>">
                </div>
                <div class="dh-meta-group">
                    <label for="dh_tipo_producto">🧶 Tipo de producto</label>
                    <select id="dh_tipo_producto" name="dh_tipo_producto">
                        <option value="">— Sin especificar —</option>
                        <?php foreach ( $tipos as $tipo ) : ?>
                        <option value="<?php echo esc_attr($tipo['slug']); ?>" <?php selected($meta['tipo_producto'], $tipo['slug']); ?>>
                            <?php echo esc_html($tipo['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dh-meta-group dh-meta-full">
                    <label for="dh_descripcion">Descripción breve</label>
                    <textarea id="dh_descripcion" name="dh_descripcion"><?php echo esc_textarea($meta['descripcion']); ?></textarea>
                </div>
                <div class="dh-meta-group">
                    <label for="dh_tiempo_estimado">⏱ Duración estimada</label>
                    <input type="text" id="dh_tiempo_estimado" name="dh_tiempo_estimado" placeholder="Ej: 3 horas" value="<?php echo esc_attr($meta['tiempo_estimado'] ?? ''); ?>">
                </div>
                <div class="dh-meta-group">
                    <label for="dh_nivel">🎓 Nivel de conocimiento</label>
                    <select id="dh_nivel" name="dh_nivel">
                        <option value="">— Sin especificar —</option>
                        <?php foreach ( array('Principiante','Intermedio','Avanzado','Todos los niveles') as $nv ) : ?>
                        <option value="<?php echo esc_attr($nv); ?>" <?php selected($meta['nivel'] ?? '', $nv); ?>><?php echo esc_html($nv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dh-meta-group dh-meta-full">
                    <label for="dh_requisitos">📋 Requisitos previos / Qué incluye</label>
                    <textarea id="dh_requisitos" name="dh_requisitos" placeholder="Ej: No se requiere experiencia previa. Incluye materiales y refrigerio."><?php echo esc_textarea($meta['requisitos'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- PANEL: Ubicación -->
        <div id="dh-panel-ubicacion" class="dh-meta-panel">
            <div class="dh-meta-grid">
                <div class="dh-meta-group">
                    <label for="dh_ubicacion">📍 Zona / Barrio</label>
                    <input type="text" id="dh_ubicacion" name="dh_ubicacion" placeholder="Ej: Pocitos, Montevideo" value="<?php echo esc_attr($meta['ubicacion']); ?>">
                </div>
                <div class="dh-meta-group">
                    <label for="dh_direccion">🏠 Dirección exacta</label>
                    <input type="text" id="dh_direccion" name="dh_direccion" placeholder="Ej: Av. Brasil 2345 apto 3" value="<?php echo esc_attr($meta['direccion']); ?>">
                </div>
                <div class="dh-meta-group dh-meta-full">
                    <label for="dh_maps_url">
                        🗺️ URL de Google Maps
                        <small style="font-weight:400;color:#888;">(Google Maps → Compartir → Insertar → copiar src del iframe)</small>
                    </label>
                    <input type="text" id="dh_maps_url" name="dh_maps_url"
                           placeholder="https://www.google.com/maps/embed?pb=…"
                           value="<?php echo esc_attr($meta['maps_url']); ?>">
                    <div id="dh-maps-preview-wrap" style="<?php echo $meta['maps_url'] ? '' : 'display:none;'; ?>">
                        <?php if ($meta['maps_url']) : ?>
                        <div class="dh-maps-preview">
                            <iframe src="<?php echo esc_url($meta['maps_url']); ?>" allowfullscreen loading="lazy"></iframe>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANEL: Cupos -->
        <div id="dh-panel-cupos" class="dh-meta-panel">
            <div class="dh-meta-grid">
                <div class="dh-meta-section">💰 Precios base del taller</div>
                <div class="dh-meta-group">
                    <label for="dh_precio_sena">Precio Seña ($)</label>
                    <input type="number" id="dh_precio_sena" name="dh_precio_sena" min="0" value="<?php echo esc_attr($meta['precio_sena']); ?>" placeholder="1600">
                </div>
                <div class="dh-meta-group">
                    <label for="dh_precio_total">Precio Total ($)</label>
                    <input type="number" id="dh_precio_total" name="dh_precio_total" min="0" value="<?php echo esc_attr($meta['precio_total']); ?>" placeholder="3500">
                </div>
                <div class="dh-meta-section">👥 Cupos por turno</div>
                <div class="dh-cupo-section">
                    <h4>☀️ Turno Matutino</h4>
                    <div class="dh-meta-group" style="margin-bottom:8px">
                        <label>Cupos disponibles</label>
                        <input type="number" name="dh_cupos_matutino" min="0" value="<?php echo esc_attr($meta['cupos_matutino']); ?>">
                    </div>
                    <div class="dh-meta-group">
                        <label>Capacidad máxima</label>
                        <input type="number" name="dh_cupos_matutino_total" min="0" value="<?php echo esc_attr($meta['cupos_matutino_total']); ?>">
                    </div>
                </div>
                <div class="dh-cupo-section">
                    <h4>🌇 Turno Vespertino</h4>
                    <div class="dh-meta-group" style="margin-bottom:8px">
                        <label>Cupos disponibles</label>
                        <input type="number" name="dh_cupos_vespertino" min="0" value="<?php echo esc_attr($meta['cupos_vespertino']); ?>">
                    </div>
                    <div class="dh-meta-group">
                        <label>Capacidad máxima</label>
                        <input type="number" name="dh_cupos_vespertino_total" min="0" value="<?php echo esc_attr($meta['cupos_vespertino_total']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- PANEL: Material -->
        <div id="dh-panel-variantes" class="dh-meta-panel">
            <p style="color:#666;font-size:13px;margin:0 0 16px;">
                Activá las opciones que el alumno podrá seleccionar al inscribirse.
                Las opciones disponibles se configuran en
                <a href="<?php echo admin_url('admin.php?page=dh-configuracion'); ?>" target="_blank">Configuración de material</a>.
            </p>
            <div class="dh-check-grid">
                <?php
                $checks = array(
                    'dh_mostrar_color'     => '🎨 Mostrar selector de color',
                    'dh_mostrar_tipo_lana' => '🧶 Mostrar tipo de lana',
                    'dh_mostrar_micras'    => '🔬 Mostrar micras',
                    'dh_mostrar_medida'    => '📏 Mostrar medida (con precio variable)',
                );
                foreach ( $checks as $key => $label ) :
                    $checked = get_post_meta( $post->ID, '_' . $key, true ) === '1';
                ?>
                <label class="dh-check-item">
                    <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked($checked, true); ?>>
                    <?php echo $label; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php if ( $meta['mostrar_medida'] ) : ?>
            <div style="background:#fef9ec;border:1px solid #fde68a;border-radius:6px;padding:12px;margin-top:14px;font-size:13px;color:#92400e;">
                ⚠️ Las medidas y sus precios se configuran en
                <a href="<?php echo admin_url('admin.php?page=dh-configuracion&variante=medidas'); ?>" target="_blank">Configuración → Medidas</a>.
                Al seleccionar una medida, el precio se actualiza automáticamente en el formulario.
            </div>
            <?php endif; ?>
        </div>

        <script>
        function dhTab(id, el) {
            document.querySelectorAll('.dh-meta-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.dh-meta-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('dh-panel-' + id).classList.add('active');
            el.classList.add('active');
        }
        function dhCopyShortcode(el) {
            var text = el.textContent;
            navigator.clipboard.writeText(text).then(function(){
                el.textContent = '¡Copiado!';
                setTimeout(() => el.textContent = text, 1500);
            });
        }
        // Vista previa maps en tiempo real
        document.getElementById('dh_maps_url')?.addEventListener('input', function(){
            var url = this.value.trim();
            var wrap = document.getElementById('dh-maps-preview-wrap');
            if (!url) { wrap.style.display='none'; return; }
            if (url.indexOf('/embed') !== -1) {
                wrap.style.display='block';
                wrap.innerHTML='<div class="dh-maps-preview"><iframe src="'+url+'" allowfullscreen loading="lazy"></iframe></div>';
            } else {
                wrap.style.display='block';
                wrap.innerHTML='<a href="'+url+'" target="_blank" class="button button-small" style="margin-top:6px">🗺️ Ver en Google Maps</a><small style="display:block;color:#888;margin-top:4px;font-size:11px">Para el embed, usá la URL del iframe de "Insertar mapa"</small>';
            }
        });
        </script>
        <?php
    }

    public function save_meta_boxes( $post_id, $post ) {
        if ( ! isset( $_POST['dh_taller_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['dh_taller_nonce'], 'dh_save_taller_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $text_fields = array(
            'dh_fecha', 'dh_tipo_producto', 'dh_ubicacion', 'dh_direccion', 'dh_maps_url',
            'dh_precio_sena', 'dh_precio_total',
            'dh_cupos_matutino', 'dh_cupos_matutino_total',
            'dh_cupos_vespertino', 'dh_cupos_vespertino_total',
            'dh_tiempo_estimado', 'dh_nivel',
        );
        foreach ( $text_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, '_' . $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }
        if ( isset( $_POST['dh_descripcion'] ) ) {
            update_post_meta( $post_id, '_dh_descripcion', sanitize_textarea_field( $_POST['dh_descripcion'] ) );
        }
        if ( isset( $_POST['dh_requisitos'] ) ) {
            update_post_meta( $post_id, '_dh_requisitos', sanitize_textarea_field( $_POST['dh_requisitos'] ) );
        }
        foreach ( array( 'dh_mostrar_color', 'dh_mostrar_tipo_lana', 'dh_mostrar_micras', 'dh_mostrar_medida' ) as $chk ) {
            update_post_meta( $post_id, '_' . $chk, isset( $_POST[ $chk ] ) ? '1' : '0' );
        }
        DH_WooCommerce::sync_product( $post_id );
    }

    /* ─────────────────────────────────────────────
       DESCARGAR ICS
    ───────────────────────────────────────────── */
    public function download_ics() {
        $taller_id = absint( $_GET['taller_id'] ?? 0 );
        $turno     = sanitize_text_field( $_GET['turno'] ?? 'matutino' );
        if ( ! $taller_id ) wp_die( 'Taller no encontrado' );
        $ics = DH_Email::generar_ics( $taller_id, $turno );
        if ( ! $ics ) wp_die( 'No se pudo generar el archivo' );
        $filename = sanitize_title( get_the_title( $taller_id ) ) . '-' . $turno . '.ics';
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo $ics;
        exit;
    }

    /* ─────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────── */
    public static function get_taller_meta( $post_id ) {
        return array(
            'fecha'                  => get_post_meta( $post_id, '_dh_fecha', true ),
            'tipo_producto'          => get_post_meta( $post_id, '_dh_tipo_producto', true ) ?: '',
            'ubicacion'              => get_post_meta( $post_id, '_dh_ubicacion', true ),
            'direccion'              => get_post_meta( $post_id, '_dh_direccion', true ),
            'maps_url'               => get_post_meta( $post_id, '_dh_maps_url', true ),
            'descripcion'            => get_post_meta( $post_id, '_dh_descripcion', true ),
            'tiempo_estimado'        => get_post_meta( $post_id, '_dh_tiempo_estimado', true ),
            'nivel'                  => get_post_meta( $post_id, '_dh_nivel', true ),
            'requisitos'             => get_post_meta( $post_id, '_dh_requisitos', true ),
            'precio_sena'            => get_post_meta( $post_id, '_dh_precio_sena', true ) ?: 1600,
            'precio_total'           => get_post_meta( $post_id, '_dh_precio_total', true ) ?: 3500,
            'cupos_matutino'         => (int) ( get_post_meta( $post_id, '_dh_cupos_matutino', true ) ?: 0 ),
            'cupos_matutino_total'   => (int) ( get_post_meta( $post_id, '_dh_cupos_matutino_total', true ) ?: 0 ),
            'cupos_vespertino'       => (int) ( get_post_meta( $post_id, '_dh_cupos_vespertino', true ) ?: 0 ),
            'cupos_vespertino_total' => (int) ( get_post_meta( $post_id, '_dh_cupos_vespertino_total', true ) ?: 0 ),
            'product_id'             => (int) get_post_meta( $post_id, '_dh_product_id', true ),
            'mostrar_color'          => get_post_meta( $post_id, '_dh_mostrar_color', true ) === '1',
            'mostrar_tipo_lana'      => get_post_meta( $post_id, '_dh_mostrar_tipo_lana', true ) === '1',
            'mostrar_micras'         => get_post_meta( $post_id, '_dh_mostrar_micras', true ) === '1',
            'mostrar_medida'         => get_post_meta( $post_id, '_dh_mostrar_medida', true ) === '1',
        );
    }

    /**
     * Devuelve las medidas con precios del tipo de producto del taller.
     * Si el taller no tiene tipo, devuelve las medidas del primer tipo disponible.
     */
    public static function get_medidas_con_precios( $taller_id ) {
        $meta         = self::get_taller_meta( $taller_id );
        $tipo_slug    = $meta['tipo_producto'];
        $tipo         = $tipo_slug ? DH_Settings::get_tipo_by_slug( $tipo_slug ) : null;
        if ( ! $tipo ) {
            $tipos = DH_Settings::get_tipos_producto();
            $tipo  = $tipos[0] ?? null;
        }
        return $tipo['medidas'] ?? array();
    }

    public static function reducir_cupo( $taller_id, $turno ) {
        $key    = ( 'matutino' === $turno ) ? '_dh_cupos_matutino' : '_dh_cupos_vespertino';
        $actual = (int) get_post_meta( $taller_id, $key, true );
        if ( $actual <= 0 ) return false;
        update_post_meta( $taller_id, $key, $actual - 1 );
        return true;
    }

    public static function reponer_cupo( $taller_id, $turno ) {
        $key_c  = ( 'matutino' === $turno ) ? '_dh_cupos_matutino' : '_dh_cupos_vespertino';
        $key_t  = ( 'matutino' === $turno ) ? '_dh_cupos_matutino_total' : '_dh_cupos_vespertino_total';
        $actual = (int) get_post_meta( $taller_id, $key_c, true );
        $total  = (int) get_post_meta( $taller_id, $key_t, true );
        update_post_meta( $taller_id, $key_c, min( $actual + 1, $total ) );
    }

    public static function get_talleres( $args = array() ) {
        return get_posts( wp_parse_args( $args, array(
            'post_type'      => 'dh_taller',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_dh_fecha',
            'order'          => 'ASC',
        ) ) );
    }

    public static function guardar_inscripcion( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dh_inscripciones';

        // Columnas base (siempre presentes desde v1.0)
        $insert  = array(
            'taller_id' => absint( $data['taller_id'] ),
            'order_id'  => absint( $data['order_id'] ?? 0 ),
            'turno'     => sanitize_text_field( $data['turno'] ),
            'tipo_pago' => sanitize_text_field( $data['tipo_pago'] ),
            'nombre'    => sanitize_text_field( $data['nombre'] ),
            'email'     => sanitize_email( $data['email'] ),
            'telefono'  => sanitize_text_field( $data['telefono'] ?? '' ),
            'estado'    => sanitize_text_field( $data['estado'] ?? 'pendiente' ),
        );
        $formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

        // Columnas opcionales — sólo insertar si existen en la tabla actual
        $cols_cache = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        if ( in_array( 'tipo_producto', $cols_cache, true ) ) {
            $insert['tipo_producto'] = sanitize_text_field( $data['tipo_producto'] ?? '' );
            $formats[] = '%s';
        }
        if ( in_array( 'variantes', $cols_cache, true ) ) {
            $insert['variantes'] = ! empty( $data['variantes'] ) ? wp_json_encode( $data['variantes'] ) : null;
            $formats[] = '%s';
        }
        if ( in_array( 'notas', $cols_cache, true ) ) {
            $insert['notas'] = ! empty( $data['notas'] ) ? sanitize_textarea_field( $data['notas'] ) : null;
            $formats[] = '%s';
        }

        $result = $wpdb->insert( $table, $insert, $formats );

        if ( false === $result ) {
            error_log( 'DH Talleres – DB insert error: ' . $wpdb->last_error );
            return 0;
        }
        return $wpdb->insert_id;
    }

    public static function actualizar_estado_inscripcion( $order_id, $estado ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'dh_inscripciones',
            array( 'estado' => $estado ),
            array( 'order_id' => absint( $order_id ) ),
            array( '%s' ), array( '%d' )
        );
    }

    public static function get_inscripciones( $taller_id = null, $turno = null, $extra_where = '', $include_deleted = false ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'dh_inscripciones';
        $where  = $include_deleted ? '1=1' : '(deleted_at IS NULL)';
        $params = array();
        if ( $taller_id ) { $where .= ' AND taller_id = %d'; $params[] = absint( $taller_id ); }
        if ( $turno )     { $where .= ' AND turno = %s';     $params[] = $turno; }
        if ( $extra_where ) $where .= ' ' . $extra_where;
        if ( ! empty( $params ) ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC", ...$params ) );
        }
        return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC" );
    }

    /**
     * Devuelve las inscripciones en la papelera (soft-deleted).
     */
    public static function get_inscripciones_eliminadas() {
        global $wpdb;
        $table = $wpdb->prefix . 'dh_inscripciones';
        return $wpdb->get_results( "SELECT * FROM {$table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC" );
    }
}
