<?php
/**
 * DH_Settings v1.3
 * – Tipos de producto (Manta, Puff Banquito, …) independientes
 * – Cada tipo tiene: colores, tipos_lana, micras, medidas (con precio_sena y precio_total)
 * – Página de configuración rediseñada con navegación por tipo
 */

defined( 'ABSPATH' ) || exit;

class DH_Settings {

    /* ─────────────────────────────────────────────
       DEFAULTS
    ───────────────────────────────────────────── */
    private static function get_defaults() {
        return array(
            array(
                'slug'       => 'manta',
                'nombre'     => 'Manta',
                'colores'    => array( 'Chocolate', 'Bisón', 'Marmolada', 'Crema' ),
                'tipos_lana' => array( 'Corriedale', 'Merino' ),
                'micras'     => array( '28', '26', '24', '23', '22', '21', '19' ),
                'medidas'    => array(
                    array( 'nombre' => '1.20 x 0.60 m', 'precio_sena' => 1600, 'precio_total' => 3500 ),
                ),
            ),
            array(
                'slug'       => 'puff_banquito',
                'nombre'     => 'Puff Banquito',
                'colores'    => array( 'Chocolate', 'Bisón', 'Marmolada', 'Crema' ),
                'tipos_lana' => array(),
                'micras'     => array(),
                'medidas'    => array(
                    array( 'nombre' => 'Estándar', 'precio_sena' => 1600, 'precio_total' => 3500 ),
                ),
            ),
        );
    }

    public function __construct() {
        add_action( 'wp_ajax_dh_get_tipo_opciones',     array( $this, 'ajax_get_tipo_opciones' ) );
        add_action( 'wp_ajax_dh_save_tipo_variante',    array( $this, 'ajax_save_tipo_variante' ) );
        add_action( 'wp_ajax_dh_delete_tipo_variante',  array( $this, 'ajax_delete_tipo_variante' ) );
        add_action( 'wp_ajax_dh_add_tipo_producto',     array( $this, 'ajax_add_tipo_producto' ) );
        add_action( 'wp_ajax_dh_delete_tipo_producto',  array( $this, 'ajax_delete_tipo_producto' ) );
        // BC: mantener aliases viejos por si hay código que los use
        add_action( 'wp_ajax_dh_save_variante',         array( $this, 'ajax_save_tipo_variante' ) );
        add_action( 'wp_ajax_dh_delete_variante',       array( $this, 'ajax_delete_tipo_variante' ) );
    }

    /* ─────────────────────────────────────────────
       GETTERS
    ───────────────────────────────────────────── */
    public static function get_tipos_producto() {
        $saved = get_option( 'dh_tipos_producto' );
        if ( $saved && is_array( $saved ) && ! empty( $saved ) ) return $saved;
        return self::get_defaults();
    }

    public static function get_tipo_by_slug( $slug ) {
        foreach ( self::get_tipos_producto() as $tipo ) {
            if ( $tipo['slug'] === $slug ) return $tipo;
        }
        return null;
    }

    /** BC: get_opciones() devuelve el primer tipo (Manta) para compatibilidad */
    public static function get_opciones() {
        $tipos = self::get_tipos_producto();
        if ( empty( $tipos ) ) {
            return array( 'colores' => array(), 'tipos_lana' => array(), 'micras' => array(), 'medidas' => array() );
        }
        $t = $tipos[0];
        return array(
            'colores'    => $t['colores']    ?? array(),
            'tipos_lana' => $t['tipos_lana'] ?? array(),
            'micras'     => $t['micras']     ?? array(),
            'medidas'    => array_column( $t['medidas'] ?? array(), 'nombre' ),
        );
    }

    /* ─────────────────────────────────────────────
       AJAX HANDLERS
    ───────────────────────────────────────────── */
    public function ajax_get_tipo_opciones() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $slug = sanitize_key( $_POST['slug'] ?? '' );
        $tipo = self::get_tipo_by_slug( $slug );
        wp_send_json_success( $tipo ?: array() );
    }

    public function ajax_add_tipo_producto() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $nombre = sanitize_text_field( $_POST['nombre'] ?? '' );
        if ( ! $nombre ) wp_send_json_error( array( 'msg' => 'Nombre requerido.' ) );

        $slug = sanitize_title( $nombre );
        $tipos = self::get_tipos_producto();

        // Verificar duplicado
        foreach ( $tipos as $t ) {
            if ( $t['slug'] === $slug ) wp_send_json_error( array( 'msg' => 'Ya existe un tipo con ese nombre.' ) );
        }

        $tipos[] = array(
            'slug'       => $slug,
            'nombre'     => $nombre,
            'colores'    => array(),
            'tipos_lana' => array(),
            'micras'     => array(),
            'medidas'    => array(),
        );
        update_option( 'dh_tipos_producto', $tipos );
        wp_send_json_success( array( 'slug' => $slug, 'tipos' => $tipos ) );
    }

    public function ajax_delete_tipo_producto() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $slug = sanitize_key( $_POST['slug'] ?? '' );
        $tipos = array_values( array_filter( self::get_tipos_producto(), function($t) { return $t['slug'] !== $slug; }) );
        update_option( 'dh_tipos_producto', $tipos );
        wp_send_json_success( array( 'tipos' => $tipos ) );
    }

    public function ajax_save_tipo_variante() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $slug    = sanitize_key( $_POST['slug'] ?? $_POST['key'] ?? '' );
        $campo   = sanitize_key( $_POST['campo'] ?? 'colores' );
        $tipos   = self::get_tipos_producto();
        $idx_tipo = -1;
        foreach ( $tipos as $i => $t ) {
            if ( $t['slug'] === $slug ) { $idx_tipo = $i; break; }
        }
        if ( $idx_tipo === -1 ) wp_send_json_error( array( 'msg' => 'Tipo no encontrado.' ) );

        if ( 'medidas' === $campo ) {
            $item = array(
                'nombre'       => sanitize_text_field( $_POST['nombre']       ?? '' ),
                'precio_sena'  => (float) ( $_POST['precio_sena']  ?? 0 ),
                'precio_total' => (float) ( $_POST['precio_total'] ?? 0 ),
            );
            if ( ! $item['nombre'] ) wp_send_json_error( array( 'msg' => 'El nombre de la medida es obligatorio.' ) );
            $tipos[ $idx_tipo ]['medidas'][] = $item;
        } else {
            $valor = sanitize_text_field( $_POST['valor'] ?? '' );
            if ( ! $valor ) wp_send_json_error( array( 'msg' => 'Valor requerido.' ) );
            if ( ! in_array( $valor, $tipos[ $idx_tipo ][ $campo ] ?? array(), true ) ) {
                $tipos[ $idx_tipo ][ $campo ][] = $valor;
            }
        }

        update_option( 'dh_tipos_producto', $tipos );
        wp_send_json_success( array( 'tipo' => $tipos[ $idx_tipo ] ) );
    }

    public function ajax_delete_tipo_variante() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $slug    = sanitize_key( $_POST['slug'] ?? $_POST['key'] ?? '' );
        $campo   = sanitize_key( $_POST['campo'] ?? 'colores' );
        $index   = absint( $_POST['index'] ?? -1 );
        $tipos   = self::get_tipos_producto();
        $idx_tipo = -1;
        foreach ( $tipos as $i => $t ) {
            if ( $t['slug'] === $slug ) { $idx_tipo = $i; break; }
        }
        if ( $idx_tipo === -1 ) wp_send_json_error();

        $lista = $tipos[ $idx_tipo ][ $campo ] ?? array();
        if ( isset( $lista[ $index ] ) ) {
            array_splice( $lista, $index, 1 );
            $tipos[ $idx_tipo ][ $campo ] = array_values( $lista );
        }
        update_option( 'dh_tipos_producto', $tipos );
        wp_send_json_success( array( 'tipo' => $tipos[ $idx_tipo ] ) );
    }

    /* ─────────────────────────────────────────────
       PÁGINA DE CONFIGURACIÓN
    ───────────────────────────────────────────── */
    public static function render_page() {
        $tipos       = self::get_tipos_producto();
        $active_slug = sanitize_key( $_GET['tipo'] ?? ( $tipos[0]['slug'] ?? '' ) );
        $active_tab  = sanitize_key( $_GET['variante'] ?? 'colores' );
        $tipo_activo = self::get_tipo_by_slug( $active_slug );
        if ( ! $tipo_activo && ! empty( $tipos ) ) {
            $tipo_activo = $tipos[0];
            $active_slug = $tipo_activo['slug'];
        }
        $tabs = array(
            'colores'    => array( 'label' => '🎨 Colores',      'campo' => 'colores' ),
            'tipos_lana' => array( 'label' => '🧶 Tipos de lana', 'campo' => 'tipos_lana' ),
            'micras'     => array( 'label' => '🔬 Micras',        'campo' => 'micras' ),
            'medidas'    => array( 'label' => '📏 Medidas',       'campo' => 'medidas' ),
        );
        ?>
        <div class="wrap dh-admin-wrap">
            <div class="dh-admin-header">
                <div class="dh-admin-header-left">
                    <a href="<?php echo admin_url('admin.php?page=dh-talleres'); ?>" class="dh-back-btn">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                    </a>
                    <div>
                        <h1>⚙️ Configuración de material</h1>
                        <p class="dh-subtitle">Tipos de producto y opciones disponibles para registros</p>
                    </div>
                </div>
                <button class="dh-btn dh-btn-outline" onclick="dhShowAddTipoModal()">
                    <span class="dashicons dashicons-plus-alt"></span> Nuevo tipo de producto
                </button>
            </div>

            <?php if ( empty( $tipos ) ) : ?>
            <div class="dh-empty-state">
                <p>No hay tipos de producto configurados.</p>
                <button class="dh-btn dh-btn-primary" onclick="dhShowAddTipoModal()">Agregar primer tipo</button>
            </div>
            <?php else : ?>

            <div class="dh-settings-layout">
                <!-- Nav: tipos de producto -->
                <nav class="dh-settings-nav">
                    <div class="dh-nav-section-title">Tipos de producto</div>
                    <?php foreach ( $tipos as $tipo ) : ?>
                    <div class="dh-nav-tipo-item <?php echo $active_slug === $tipo['slug'] ? 'active' : ''; ?>">
                        <a href="<?php echo admin_url('admin.php?page=dh-configuracion&tipo=' . $tipo['slug']); ?>"
                           class="dh-nav-tipo-link">
                            🧶 <?php echo esc_html( $tipo['nombre'] ); ?>
                        </a>
                        <?php if ( count($tipos) > 1 ) : ?>
                        <button class="dh-nav-tipo-delete" onclick="dhDeleteTipo('<?php echo esc_js($tipo['slug']); ?>', '<?php echo esc_js($tipo['nombre']); ?>')" title="Eliminar">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </nav>

                <!-- Panel del tipo activo -->
                <?php if ( $tipo_activo ) : ?>
                <div class="dh-settings-panel">
                    <div class="dh-panel-header">
                        <h2>🧶 <?php echo esc_html( $tipo_activo['nombre'] ); ?></h2>
                        <p class="dh-panel-desc">Configurá las opciones disponibles para este tipo de producto</p>
                    </div>

                    <!-- Sub-tabs -->
                    <div class="dh-subtabs">
                        <?php foreach ( $tabs as $tab_key => $tab ) :
                            $count = count( $tipo_activo[ $tab['campo'] ] ?? array() );
                        ?>
                        <a href="<?php echo admin_url('admin.php?page=dh-configuracion&tipo='.$active_slug.'&variante='.$tab_key); ?>"
                           class="dh-subtab <?php echo $active_tab === $tab_key ? 'active' : ''; ?>">
                            <?php echo $tab['label']; ?>
                            <span class="dh-subtab-count"><?php echo $count; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    $current_campo = $tabs[ $active_tab ]['campo'] ?? 'colores';
                    $current_list  = $tipo_activo[ $current_campo ] ?? array();
                    ?>

                    <!-- Panel: Medidas (especial, con precios) -->
                    <?php if ( 'medidas' === $active_tab ) : ?>
                    <div class="dh-variante-panel">
                        <div class="dh-add-medida-box">
                            <h4 class="dh-add-box-title">+ Agregar medida con precio</h4>
                            <div class="dh-add-medida-row">
                                <div class="dh-form-group" style="flex:2">
                                    <label>Nombre / Tamaño</label>
                                    <input type="text" id="dh-medida-nombre" placeholder="Ej: 1.50 x 0.80 m">
                                </div>
                                <div class="dh-form-group">
                                    <label>Precio Seña ($)</label>
                                    <input type="number" id="dh-medida-precio-sena" placeholder="1600" min="0">
                                </div>
                                <div class="dh-form-group">
                                    <label>Precio Total ($)</label>
                                    <input type="number" id="dh-medida-precio-total" placeholder="3500" min="0">
                                </div>
                                <div class="dh-form-group" style="justify-content:flex-end;">
                                    <label>&nbsp;</label>
                                    <button class="dh-btn dh-btn-primary" onclick="dhAgregarMedida('<?php echo esc_js($active_slug); ?>')">
                                        <span class="dashicons dashicons-plus-alt"></span> Agregar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <table class="dh-medidas-table" id="dh-medidas-table-<?php echo $active_slug; ?>">
                            <thead>
                                <tr>
                                    <th>Medida / Tamaño</th>
                                    <th>Precio Seña ($)</th>
                                    <th>Precio Total ($)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ( empty( $current_list ) ) : ?>
                                <tr class="dh-medidas-empty"><td colspan="4">Sin medidas configuradas.</td></tr>
                            <?php else : ?>
                            <?php foreach ( $current_list as $idx => $m ) : ?>
                            <tr data-index="<?php echo $idx; ?>">
                                <td><strong><?php echo esc_html( $m['nombre'] ); ?></strong></td>
                                <td><span class="dh-price-tag">$<?php echo number_format( $m['precio_sena'], 0, ',', '.' ); ?></span></td>
                                <td><span class="dh-price-tag dh-price-total-tag">$<?php echo number_format( $m['precio_total'], 0, ',', '.' ); ?></span></td>
                                <td>
                                    <button class="dh-variante-delete" onclick="dhEliminarTipoVariante('<?php echo esc_js($active_slug); ?>', 'medidas', <?php echo $idx; ?>, this)">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php else : ?>
                    <!-- Panel: Lista simple (colores, tipos_lana, micras) -->
                    <div class="dh-variante-panel">
                        <div class="dh-add-variante-box">
                            <div class="dh-add-variante-input-wrap">
                                <input type="text" id="dh-new-variante-input"
                                       placeholder="Agregar nueva opción…" class="dh-input-variante">
                                <button class="dh-btn dh-btn-primary"
                                        onclick="dhAgregarTipoVariante('<?php echo esc_js($active_slug); ?>', '<?php echo esc_js($current_campo); ?>')">
                                    <span class="dashicons dashicons-plus-alt"></span> Agregar
                                </button>
                            </div>
                        </div>

                        <div class="dh-variante-list" id="dh-variante-list-<?php echo $active_slug . '-' . $current_campo; ?>">
                        <?php if ( empty( $current_list ) ) : ?>
                            <div class="dh-variante-empty">Sin opciones configuradas.</div>
                        <?php else : ?>
                            <?php foreach ( $current_list as $idx => $valor ) : ?>
                            <div class="dh-variante-item">
                                <span class="dh-variante-handle dashicons dashicons-menu"></span>
                                <span class="dh-variante-nombre"><?php echo esc_html( $valor ); ?></span>
                                <button class="dh-variante-delete"
                                        onclick="dhEliminarTipoVariante('<?php echo esc_js($active_slug); ?>', '<?php echo esc_js($current_campo); ?>', <?php echo $idx; ?>, this)">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <p class="dh-panel-note">💡 Los cambios se aplican en tiempo real. Las opciones eliminadas no afectan inscripciones existentes.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Modal: agregar tipo de producto -->
        <div id="dh-add-tipo-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0;transition:opacity .2s;">
            <div style="background:#fff;border-radius:12px;padding:28px;width:380px;box-shadow:0 8px 40px rgba(0,0,0,.2);">
                <h3 style="margin:0 0 16px;color:#8B5E3C;">Nuevo tipo de producto</h3>
                <div class="dh-form-group">
                    <label>Nombre <span class="req">*</span></label>
                    <input type="text" id="dh-new-tipo-nombre" placeholder="Ej: Chaleco, Bufanda…" class="dh-input-variante" style="margin-bottom:0">
                </div>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button class="dh-btn dh-btn-primary" onclick="dhConfirmAddTipo()" style="flex:1">Agregar</button>
                    <button class="dh-btn dh-btn-ghost" onclick="dhHideAddTipoModal()">Cancelar</button>
                </div>
            </div>
        </div>
        <?php
    }
}
