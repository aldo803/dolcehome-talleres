<?php
/**
 * DH Admin v1.6
 * + Editar inscripción: permite cambiar taller y materiales
 * + Botón reenviar email (con confirmación) en lista de alumnos
 * + Gráfico Señas / Total / Cortesía en alumnos inscriptos
 * + Papelera de reciclaje: talleres y alumnos eliminados
 * + BUG FIX: formulario de edición corregido (envío por string serializado)
 */

defined( 'ABSPATH' ) || exit;

class DH_Admin {

    public function __construct() {
        add_action( 'admin_menu',               array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts',    array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_dh_export_csv',       array( $this, 'export_csv' ) );
        add_action( 'admin_post_dh_registro_manual',  array( $this, 'guardar_registro_manual' ) );

        // AJAX inscripciones
        add_action( 'wp_ajax_dh_get_inscripcion',       array( $this, 'ajax_get_inscripcion' ) );
        add_action( 'wp_ajax_dh_editar_inscripcion',    array( $this, 'ajax_editar_inscripcion' ) );
        add_action( 'wp_ajax_dh_eliminar_inscripcion',  array( $this, 'ajax_eliminar_inscripcion' ) );
        add_action( 'wp_ajax_dh_estado_inscripcion',    array( $this, 'ajax_estado_inscripcion' ) );
        add_action( 'wp_ajax_dh_reenviar_email',        array( $this, 'ajax_reenviar_email' ) );
        add_action( 'wp_ajax_dh_restaurar_inscripcion', array( $this, 'ajax_restaurar_inscripcion' ) );
        add_action( 'wp_ajax_dh_purgar_inscripcion',    array( $this, 'ajax_purgar_inscripcion' ) );

        // AJAX talleres
        add_action( 'wp_ajax_dh_eliminar_taller',  array( $this, 'ajax_eliminar_taller' ) );
        add_action( 'wp_ajax_dh_duplicar_taller',  array( $this, 'ajax_duplicar_taller' ) );
        add_action( 'wp_ajax_dh_restaurar_taller', array( $this, 'ajax_restaurar_taller' ) );
    }

    public function register_menus() {
        add_menu_page( 'Dolce Home Talleres', 'Talleres', 'manage_woocommerce',
            'dh-talleres', array( $this, 'page_dashboard' ), 'dashicons-calendar-alt', 56 );
        add_submenu_page( 'dh-talleres', 'Todos los Talleres', 'Todos los talleres',
            'manage_woocommerce', 'dh-talleres', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'dh-talleres', 'Agregar Taller', '+ Agregar taller',
            'manage_woocommerce', 'post-new.php?post_type=dh_taller' );
        add_submenu_page( 'dh-talleres', 'Alumnos Inscriptos', 'Alumnos inscriptos',
            'manage_woocommerce', 'dh-alumnos', array( $this, 'page_alumnos' ) );
        add_submenu_page( 'dh-talleres', 'Registro Manual', 'Registro manual',
            'manage_woocommerce', 'dh-registro-manual', array( $this, 'page_registro_manual' ) );
        add_submenu_page( 'dh-talleres', 'Configuración', '⚙️ Configuración',
            'manage_woocommerce', 'dh-configuracion', array( 'DH_Settings', 'render_page' ) );
        add_submenu_page( 'dh-talleres', 'Informes', '📊 Informes',
            'manage_woocommerce', 'dh-informes', array( $this, 'page_informes' ) );
        add_submenu_page( 'dh-talleres', 'Papelera', '🗑️ Papelera',
            'manage_woocommerce', 'dh-papelera', array( $this, 'page_papelera' ) );
    }

    public function enqueue_assets( $hook ) {
        $pages = array( 'toplevel_page_dh-talleres', 'talleres_page_dh-alumnos',
                        'talleres_page_dh-configuracion', 'talleres_page_dh-registro-manual',
                        'talleres_page_dh-informes', 'talleres_page_dh-papelera',
                        'post.php', 'post-new.php' );
        if ( ! in_array( $hook, $pages ) ) return;
        wp_enqueue_style( 'dh-admin-style', DH_TALLERES_URL . 'admin/css/dh-admin.css', array(), DH_TALLERES_VERSION );
        wp_enqueue_script( 'dh-admin-script', DH_TALLERES_URL . 'admin/js/dh-admin.js',
            array( 'jquery' ), DH_TALLERES_VERSION, true );
        wp_localize_script( 'dh-admin-script', 'dhAdmin', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'dh_admin_nonce' ),
            'admin_url' => admin_url(),
            'tipos'     => DH_Settings::get_tipos_producto(),
        ) );
    }

    // ═══════════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════════
    public function page_dashboard() {
        $talleres = DH_Talleres::get_talleres();
        ?>
        <div class="wrap dh-admin-wrap">
            <div class="dh-admin-header">
                <div class="dh-admin-header-left">
                    <div>
                        <h1>🧶 Talleres de Mantas XXL</h1>
                        <p class="dh-subtitle">Gestión de talleres y cupos · Dolce Home</p>
                    </div>
                </div>
                <a href="<?php echo admin_url( 'post-new.php?post_type=dh_taller' ); ?>" class="dh-btn dh-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span> Nuevo taller
                </a>
            </div>
            <?php if ( empty( $talleres ) ) : ?>
            <div class="dh-empty-state">
                <span class="dashicons dashicons-calendar-alt" style="font-size:64px;color:#ccc;width:64px;height:64px;"></span>
                <h2>No hay talleres creados</h2>
                <p>Creá tu primer taller para comenzar a recibir inscripciones.</p>
                <a href="<?php echo admin_url( 'post-new.php?post_type=dh_taller' ); ?>" class="dh-btn dh-btn-primary">Crear primer taller</a>
            </div>
            <?php else : ?>
            <div class="dh-toolbar">
                <div class="dh-toolbar-left">
                    <span class="dh-toolbar-label">Ordenar:</span>
                    <button class="dh-sort-btn active" data-sort="fecha"  onclick="dhOrdenar('fecha')">📅 Fecha</button>
                    <button class="dh-sort-btn"        data-sort="tipo"   onclick="dhOrdenar('tipo')">🏷️ Tipo</button>
                    <button class="dh-sort-btn"        data-sort="titulo" onclick="dhOrdenar('titulo')">🔤 Nombre</button>
                </div>
                <div class="dh-toolbar-right">
                    <span class="dh-toolbar-label">Vista:</span>
                    <button class="dh-view-btn active" data-view="grid"  onclick="dhSetVista('grid')"  title="Cuadrícula"><span class="dashicons dashicons-grid-view"></span></button>
                    <button class="dh-view-btn"        data-view="lista" onclick="dhSetVista('lista')" title="Lista"><span class="dashicons dashicons-list-view"></span></button>
                </div>
            </div>
            <div class="dh-talleres-grid dh-vista-grid" id="dh-talleres-grid">
                <?php foreach ( $talleres as $taller ) :
                    $meta    = DH_Talleres::get_taller_meta( $taller->ID );
                    $fecha   = $meta['fecha'] ? date_i18n( 'd/m/Y', strtotime( $meta['fecha'] ) ) : '—';
                    $insc    = DH_Talleres::get_inscripciones( $taller->ID );
                    $activos = array_filter( $insc, function($i) { return $i->estado !== 'cancelado'; });
                    $dis_mat = $meta['cupos_matutino'];   $tot_mat = $meta['cupos_matutino_total'];
                    $dis_ves = $meta['cupos_vespertino']; $tot_ves = $meta['cupos_vespertino_total'];
                    $pct_mat = $tot_mat > 0 ? round( ( ( $tot_mat - $dis_mat ) / $tot_mat ) * 100 ) : 0;
                    $pct_ves = $tot_ves > 0 ? round( ( ( $tot_ves - $dis_ves ) / $tot_ves ) * 100 ) : 0;
                ?>
                <div class="dh-taller-card" id="dh-taller-card-<?php echo $taller->ID; ?>"
                     data-fecha="<?php echo esc_attr( $meta['fecha'] ); ?>"
                     data-tipo="<?php echo esc_attr( $meta['tipo_producto'] ); ?>"
                     data-titulo="<?php echo esc_attr( get_the_title( $taller->ID ) ); ?>">
                    <div class="dh-taller-card-header">
                        <div class="dh-taller-date"><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html( $fecha ); ?></div>
                        <div class="dh-taller-actions">
                            <a href="<?php echo get_edit_post_link( $taller->ID ); ?>" class="dh-btn-icon" title="Editar taller"><span class="dashicons dashicons-edit"></span></a>
                            <button class="dh-btn-icon dh-btn-icon-dup" title="Duplicar taller" onclick="dhDuplicarTaller(<?php echo $taller->ID; ?>)"><span class="dashicons dashicons-admin-page"></span></button>
                            <button class="dh-btn-icon dh-btn-icon-del" title="Eliminar taller" onclick="dhEliminarTallerDashboard(<?php echo $taller->ID; ?>, '<?php echo esc_js( get_the_title($taller->ID) ); ?>')"><span class="dashicons dashicons-trash"></span></button>
                            <a href="<?php echo admin_url( 'admin.php?page=dh-alumnos&taller_id=' . $taller->ID ); ?>" class="dh-btn-icon" title="Ver alumnos"><span class="dashicons dashicons-groups"></span></a>
                        </div>
                    </div>
                    <h3 class="dh-taller-title"><?php echo esc_html( get_the_title( $taller->ID ) ); ?></h3>
                    <?php if ( $meta['nivel'] || $meta['tiempo_estimado'] ) : ?>
                    <div class="dh-taller-tags">
                        <?php if ($meta['nivel']) echo '<span class="dh-tag">'.esc_html($meta['nivel']).'</span>'; ?>
                        <?php if ($meta['tiempo_estimado']) echo '<span class="dh-tag dh-tag-time">⏱ '.esc_html($meta['tiempo_estimado']).'</span>'; ?>
                    </div>
                    <?php endif; ?>
                    <div class="dh-taller-meta"><span class="dashicons dashicons-location"></span>
                        <?php echo $meta['ubicacion'] ? esc_html( $meta['ubicacion'] ) : '<em>Sin ubicación</em>'; ?>
                    </div>
                    <div class="dh-taller-prices">
                        <span class="dh-price-badge dh-price-sena">Seña $<?php echo number_format( $meta['precio_sena'], 0, ',', '.' ); ?></span>
                        <span class="dh-price-badge dh-price-total">Total $<?php echo number_format( $meta['precio_total'], 0, ',', '.' ); ?></span>
                    </div>
                    <div class="dh-cupos-section">
                        <div class="dh-cupo-row">
                            <span class="dh-turno-label mat">☀️ Matutino</span>
                            <div class="dh-progress-wrap">
                                <div class="dh-progress-bar"><div class="dh-progress-fill mat" style="width:<?php echo $pct_mat; ?>%"></div></div>
                                <span class="dh-cupo-count <?php echo $dis_mat <= 0 ? 'full' : ''; ?>"><?php echo $dis_mat <= 0 ? '✓ Completo' : "{$dis_mat}/{$tot_mat} libres"; ?></span>
                            </div>
                        </div>
                        <div class="dh-cupo-row">
                            <span class="dh-turno-label ves">🌇 Vespertino</span>
                            <div class="dh-progress-wrap">
                                <div class="dh-progress-bar"><div class="dh-progress-fill ves" style="width:<?php echo $pct_ves; ?>%"></div></div>
                                <span class="dh-cupo-count <?php echo $dis_ves <= 0 ? 'full' : ''; ?>"><?php echo $dis_ves <= 0 ? '✓ Completo' : "{$dis_ves}/{$tot_ves} libres"; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="dh-taller-footer">
                        <a href="<?php echo admin_url( 'admin.php?page=dh-alumnos&taller_id=' . $taller->ID ); ?>" class="dh-btn dh-btn-outline dh-btn-sm">
                            <span class="dashicons dashicons-groups"></span> <?php echo count( $activos ); ?> inscripto<?php echo count( $activos ) !== 1 ? 's' : ''; ?>
                        </a>
                        <a href="<?php echo get_edit_post_link( $taller->ID ); ?>" class="dh-btn dh-btn-sm">Editar</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ═══════════════════════════════════════════════
    // ALUMNOS INSCRIPTOS
    // ═══════════════════════════════════════════════
    public function page_alumnos() {
        $taller_id_filter = isset( $_GET['taller_id'] ) ? absint( $_GET['taller_id'] ) : 0;
        $turno_filter     = isset( $_GET['turno'] )    ? sanitize_text_field( $_GET['turno'] ) : '';
        $estado_filter    = isset( $_GET['estado'] )   ? sanitize_text_field( $_GET['estado'] ) : '';
        $talleres         = DH_Talleres::get_talleres();
        $todas            = DH_Talleres::get_inscripciones( $taller_id_filter ?: null, $turno_filter ?: null );

        $inscripciones = $estado_filter
            ? array_values( array_filter( $todas, function($i) use ($estado_filter) { return $i->estado === $estado_filter; }) )
            : $todas;

        $total       = count( $todas );
        $pendientes  = count( array_filter( $todas, function($i) { return $i->estado === 'pendiente'; }) );
        $confirmados = count( array_filter( $todas, function($i) { return $i->estado === 'confirmado'; }) );
        $cancelados  = count( array_filter( $todas, function($i) { return $i->estado === 'cancelado'; }) );

        // Conteo por tipo de pago (excluye cancelados) — para gráfico
        $pago_sena     = count( array_filter( $todas, function($i) { return $i->tipo_pago === 'sena'     && $i->estado !== 'cancelado'; }) );
        $pago_total    = count( array_filter( $todas, function($i) { return $i->tipo_pago === 'total'    && $i->estado !== 'cancelado'; }) );
        $pago_cortesia = count( array_filter( $todas, function($i) { return $i->tipo_pago === 'cortesia' && $i->estado !== 'cancelado'; }) );

        $base_url = admin_url( 'admin.php?page=dh-alumnos' )
            . ( $taller_id_filter ? '&taller_id=' . $taller_id_filter : '' )
            . ( $turno_filter     ? '&turno=' . $turno_filter : '' );
        ?>
        <div class="wrap dh-admin-wrap">
            <div class="dh-admin-header">
                <div class="dh-admin-header-left">
                    <a href="<?php echo admin_url( 'admin.php?page=dh-talleres' ); ?>" class="dh-back-btn"><span class="dashicons dashicons-arrow-left-alt"></span></a>
                    <div><h1>Alumnos Inscriptos</h1><p class="dh-subtitle">Registro completo de inscripciones</p></div>
                </div>
                <div style="display:flex;gap:10px;">
                    <a href="<?php echo admin_url( 'admin.php?page=dh-registro-manual' . ( $taller_id_filter ? '&taller_id='.$taller_id_filter : '' ) ); ?>" class="dh-btn dh-btn-primary">
                        <span class="dashicons dashicons-plus-alt"></span> Registro manual
                    </a>
                    <?php if ( ! empty( $todas ) ) : ?>
                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=dh_export_csv&taller_id='.$taller_id_filter.'&turno='.$turno_filter ), 'dh_export_csv' ); ?>" class="dh-btn dh-btn-outline">
                        <span class="dashicons dashicons-download"></span> CSV
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filtros -->
            <div class="dh-filters-bar">
                <form method="get" action="<?php echo admin_url( 'admin.php' ); ?>" class="dh-filters-form">
                    <input type="hidden" name="page" value="dh-alumnos">
                    <div class="dh-filter-group">
                        <label>Taller</label>
                        <select name="taller_id">
                            <option value="">Todos</option>
                            <?php foreach ( $talleres as $t ) :
                                $fl  = get_post_meta( $t->ID, '_dh_fecha', true );
                                $lbl = get_the_title( $t->ID ) . ( $fl ? ' · ' . date_i18n( 'd/m/Y', strtotime($fl) ) : '' );
                            ?>
                            <option value="<?php echo $t->ID; ?>" <?php selected( $taller_id_filter, $t->ID ); ?>><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="dh-filter-group">
                        <label>Turno</label>
                        <select name="turno">
                            <option value="">Todos</option>
                            <option value="matutino"   <?php selected( $turno_filter, 'matutino' ); ?>>☀️ Matutino</option>
                            <option value="vespertino" <?php selected( $turno_filter, 'vespertino' ); ?>>🌇 Vespertino</option>
                        </select>
                    </div>
                    <div class="dh-filter-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="pendiente"  <?php selected( $estado_filter, 'pendiente' ); ?>>🕐 En espera</option>
                            <option value="confirmado" <?php selected( $estado_filter, 'confirmado' ); ?>>✅ Confirmado</option>
                            <option value="cancelado"  <?php selected( $estado_filter, 'cancelado' ); ?>>❌ Cancelado</option>
                        </select>
                    </div>
                    <button type="submit" class="dh-btn dh-btn-primary">Filtrar</button>
                    <a href="<?php echo admin_url( 'admin.php?page=dh-alumnos' ); ?>" class="dh-btn dh-btn-ghost">Limpiar</a>
                </form>
            </div>

            <!-- Stats clickeables -->
            <div class="dh-stats-row">
                <a href="<?php echo $base_url; ?>" class="dh-stat-box <?php echo ! $estado_filter ? 'active' : ''; ?>">
                    <span class="dh-stat-number"><?php echo $total; ?></span>
                    <span class="dh-stat-label">Total</span>
                </a>
                <a href="<?php echo $base_url . '&estado=pendiente'; ?>" class="dh-stat-box pending <?php echo $estado_filter === 'pendiente' ? 'active' : ''; ?>">
                    <span class="dh-stat-number"><?php echo $pendientes; ?></span>
                    <span class="dh-stat-label">En espera</span>
                </a>
                <a href="<?php echo $base_url . '&estado=confirmado'; ?>" class="dh-stat-box confirmed <?php echo $estado_filter === 'confirmado' ? 'active' : ''; ?>">
                    <span class="dh-stat-number"><?php echo $confirmados; ?></span>
                    <span class="dh-stat-label">Confirmados</span>
                </a>
                <a href="<?php echo $base_url . '&estado=cancelado'; ?>" class="dh-stat-box cancelled <?php echo $estado_filter === 'cancelado' ? 'active' : ''; ?>">
                    <span class="dh-stat-number"><?php echo $cancelados; ?></span>
                    <span class="dh-stat-label">Cancelados</span>
                </a>
            </div>

            <!-- Gráficos -->
            <?php if ( $total > 0 ) : ?>
            <div class="dh-chart-wrap">
                <!-- Gráfico 1: Distribución por estado -->
                <div class="dh-chart-card">
                    <h3 class="dh-chart-title">Distribución de inscripciones</h3>
                    <div class="dh-chart-inner">
                        <canvas id="dh-chart-inscripciones"
                            data-confirmados="<?php echo $confirmados; ?>"
                            data-pendientes="<?php echo $pendientes; ?>"
                            data-cancelados="<?php echo $cancelados; ?>">
                        </canvas>
                        <div class="dh-chart-legend">
                            <div class="dh-legend-item"><span class="dh-legend-dot" style="background:#3a9966;"></span> Confirmados <strong><?php echo $confirmados; ?></strong></div>
                            <div class="dh-legend-item"><span class="dh-legend-dot" style="background:#d97706;"></span> En espera <strong><?php echo $pendientes; ?></strong></div>
                            <div class="dh-legend-item"><span class="dh-legend-dot" style="background:#c0392b;"></span> Cancelados <strong><?php echo $cancelados; ?></strong></div>
                        </div>
                    </div>
                </div>
                <!-- Gráfico 2: Seña / Total / Cortesía -->
                <?php if ( ( $pago_sena + $pago_total + $pago_cortesia ) > 0 ) : ?>
                <div class="dh-chart-card">
                    <h3 class="dh-chart-title">Distribución por tipo de pago</h3>
                    <div class="dh-chart-inner">
                        <canvas id="dh-chart-pagos"
                            data-sena="<?php echo $pago_sena; ?>"
                            data-total="<?php echo $pago_total; ?>"
                            data-cortesia="<?php echo $pago_cortesia; ?>">
                        </canvas>
                        <div class="dh-chart-legend">
                            <div class="dh-legend-item"><span class="dh-legend-dot" style="background:#8B5E3C;"></span> Seña <strong><?php echo $pago_sena; ?></strong></div>
                            <div class="dh-legend-item"><span class="dh-legend-dot" style="background:#2271b1;"></span> Total <strong><?php echo $pago_total; ?></strong></div>
                            <div class="dh-legend-item"><span class="dh-legend-dot" style="background:#6d3fc0;"></span> Cortesía <strong><?php echo $pago_cortesia; ?></strong></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( empty( $inscripciones ) ) : ?>
            <div class="dh-empty-state">
                <span class="dashicons dashicons-groups" style="font-size:64px;color:#ccc;width:64px;height:64px;"></span>
                <h2>Sin inscripciones</h2>
                <p>No se encontraron inscripciones con los filtros aplicados.</p>
            </div>
            <?php else : ?>
            <table class="dh-alumnos-table wp-list-table widefat">
                <thead>
                    <tr>
                        <th>#</th><th>Nombre</th><th>Email / Tel.</th>
                        <th>Taller</th><th>Turno</th><th>Pago</th>
                        <th>Material</th><th>Estado</th><th>Pedido</th><th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $inscripciones as $i ) :
                        $fecha_t   = get_post_meta( $i->taller_id, '_dh_fecha', true );
                        $orden_url = $i->order_id ? get_edit_post_link( $i->order_id ) : '';
                        $variantes = $i->variantes ? json_decode( $i->variantes, true ) : array();
                        $var_text  = array();
                        foreach ( array( 'color'=>'🎨','tipo_lana'=>'🧶','micras'=>'🔬','medida'=>'📏' ) as $vk => $vi ) {
                            if ( ! empty( $variantes[$vk] ) ) $var_text[] = $vi . ' ' . $variantes[$vk];
                        }
                        $tipo_pago_label = array( 'sena'=>'Seña','total'=>'Total','cortesia'=>'Cortesía' )[$i->tipo_pago] ?? ucfirst($i->tipo_pago);
                    ?>
                    <tr class="dh-row-<?php echo esc_attr( $i->estado ); ?>" id="dh-insc-row-<?php echo $i->id; ?>">
                        <td><?php echo $i->id; ?></td>
                        <td>
                            <strong><?php echo esc_html( $i->nombre ); ?></strong>
                            <?php if ( $i->notas ) echo '<br><small style="color:#888;">' . esc_html( $i->notas ) . '</small>'; ?>
                        </td>
                        <td>
                            <a href="mailto:<?php echo esc_attr( $i->email ); ?>"><?php echo esc_html( $i->email ); ?></a>
                            <?php if ( $i->telefono ) echo '<br><small>' . esc_html( $i->telefono ) . '</small>'; ?>
                        </td>
                        <td>
                            <?php echo esc_html( get_the_title( $i->taller_id ) ); ?>
                            <?php if ( $fecha_t ) echo '<br><small style="color:#888;">' . date_i18n( 'd/m/Y', strtotime( $fecha_t ) ) . '</small>'; ?>
                        </td>
                        <td><?php echo 'matutino' === $i->turno ? '<span class="dh-badge mat">☀️ Mat.</span>' : '<span class="dh-badge ves">🌇 Ves.</span>'; ?></td>
                        <td>
                            <?php
                            $pago_cls = $i->tipo_pago === 'sena' ? 'pago-sena' : ( $i->tipo_pago === 'cortesia' ? 'pago-cortesia' : 'pago-total' );
                            echo '<span class="dh-badge '.$pago_cls.'">'.$tipo_pago_label.'</span>';
                            ?>
                        </td>
                        <td><small><?php echo $var_text ? implode('<br>', $var_text) : '—'; ?></small></td>
                        <td>
                            <?php
                            $el = array( 'pendiente'=>'🕐 En espera','confirmado'=>'✅ Confirmado','cancelado'=>'❌ Cancelado' );
                            echo '<span class="dh-estado '.esc_attr($i->estado).'">' . ( $el[$i->estado] ?? ucfirst($i->estado) ) . '</span>';
                            ?>
                        </td>
                        <td><?php echo $orden_url ? '<a href="'.$orden_url.'" target="_blank" class="dh-order-link">#'.$i->order_id.'</a>' : '<em>Manual</em>'; ?></td>
                        <td><?php echo date_i18n( 'd/m/Y H:i', strtotime( $i->created_at ) ); ?></td>
                        <td class="dh-row-actions">
                            <button class="dh-action-btn dh-action-edit"  onclick="dhOpenEditModal(<?php echo $i->id; ?>)" title="Editar inscripción"><span class="dashicons dashicons-edit"></span></button>
                            <button class="dh-action-btn dh-action-email" onclick="dhReenviarEmail(<?php echo $i->id; ?>, '<?php echo esc_js($i->email); ?>')" title="Reenviar email"><span class="dashicons dashicons-email-alt"></span></button>
                            <?php if ( $i->estado !== 'confirmado' ) : ?>
                            <button class="dh-action-btn dh-action-confirm" onclick="dhCambiarEstado(<?php echo $i->id; ?>, 'confirmado')" title="Confirmar"><span class="dashicons dashicons-yes-alt"></span></button>
                            <?php endif; ?>
                            <?php if ( $i->estado !== 'cancelado' ) : ?>
                            <button class="dh-action-btn dh-action-cancel" onclick="dhCambiarEstado(<?php echo $i->id; ?>, 'cancelado')" title="Cancelar"><span class="dashicons dashicons-no-alt"></span></button>
                            <?php endif; ?>
                            <button class="dh-action-btn dh-action-delete" onclick="dhEliminarInscripcion(<?php echo $i->id; ?>)" title="Mover a papelera"><span class="dashicons dashicons-trash"></span></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- MODAL DE EDICIÓN -->
        <div id="dh-edit-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;">
            <div class="dh-edit-modal" role="dialog">
                <div class="dh-edit-modal-header">
                    <h2>✏️ Editar inscripción <span id="dh-edit-modal-id"></span></h2>
                    <button class="dh-modal-close-x" onclick="dhCloseEditModal()">✕</button>
                </div>
                <div id="dh-edit-modal-body"></div>
            </div>
        </div>
        <?php
    }

    // ═══════════════════════════════════════════════
    // AJAX: GET inscripcion (retorna talleres + tipos para modal)
    // ═══════════════════════════════════════════════
    public function ajax_get_inscripcion() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        global $wpdb;
        $id  = absint( $_POST['id'] ?? 0 );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dh_inscripciones WHERE id=%d", $id ) );
        if ( ! $row ) wp_send_json_error( array( 'msg' => 'Inscripción no encontrada.' ) );

        $talleres = DH_Talleres::get_talleres();
        $talleres_data = array_map( function( $t ) {
            $f = get_post_meta( $t->ID, '_dh_fecha', true );
            return array(
                'id'    => $t->ID,
                'title' => get_the_title( $t->ID ) . ( $f ? ' · ' . date_i18n('d/m/Y', strtotime($f)) : '' ),
            );
        }, $talleres );

        // Mapa taller_id → tipo_slug para cargar variantes dinámicamente
        $taller_tipos_map = array();
        foreach ( $talleres as $t ) {
            $m = DH_Talleres::get_taller_meta( $t->ID );
            $taller_tipos_map[ $t->ID ] = $m['tipo_producto'] ?: '';
        }

        wp_send_json_success( array(
            'id'               => $row->id,
            'taller_id'        => $row->taller_id,
            'order_id'         => $row->order_id,
            'nombre'           => $row->nombre,
            'email'            => $row->email,
            'telefono'         => $row->telefono,
            'turno'            => $row->turno,
            'tipo_pago'        => $row->tipo_pago,
            'estado'           => $row->estado,
            'notas'            => $row->notas ?? '',
            'variantes'        => $row->variantes ? json_decode( $row->variantes, true ) : array(),
            'talleres'         => $talleres_data,
            'taller_tipos_map' => $taller_tipos_map,
            'tipos_producto'   => DH_Settings::get_tipos_producto(),
        ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: EDITAR inscripcion — BUG FIX + taller + materiales
    // ═══════════════════════════════════════════════
    public function ajax_editar_inscripcion() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        global $wpdb;
        $id           = absint( $_POST['id']         ?? 0 );
        $nombre       = sanitize_text_field( $_POST['nombre']    ?? '' );
        $email        = sanitize_email( $_POST['email']          ?? '' );
        $telefono     = sanitize_text_field( $_POST['telefono']  ?? '' );
        $turno_nuevo  = sanitize_text_field( $_POST['turno']     ?? '' );
        $tipo_pago    = sanitize_text_field( $_POST['tipo_pago'] ?? '' );
        $notas        = sanitize_textarea_field( $_POST['notas'] ?? '' );
        $taller_nuevo = absint( $_POST['taller_id']  ?? 0 );

        // Variantes de material
        $variantes = array();
        foreach ( array( 'color','tipo_lana','micras','medida' ) as $vk ) {
            $v = sanitize_text_field( $_POST[ 'variante_' . $vk ] ?? '' );
            if ( $v ) $variantes[ $vk ] = $v;
        }

        if ( ! $id || ! $nombre || ! is_email( $email ) ) {
            wp_send_json_error( array( 'msg' => 'Completá nombre y email correctamente.' ) );
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dh_inscripciones WHERE id=%d", $id ) );
        if ( ! $row ) wp_send_json_error( array( 'msg' => 'Inscripción no encontrada.' ) );

        $taller_actual = (int) $row->taller_id;
        $turno_actual  = $row->turno;
        if ( ! $taller_nuevo ) $taller_nuevo = $taller_actual;

        $cambia_taller = ( $taller_nuevo !== $taller_actual );
        $cambia_turno  = ( $turno_nuevo && $turno_nuevo !== $turno_actual );

        // Gestión de cupos si cambia taller o turno
        if ( $row->estado !== 'cancelado' && ( $cambia_taller || $cambia_turno ) ) {
            DH_Talleres::reponer_cupo( $taller_actual, $turno_actual );
            $turno_dest = $turno_nuevo ?: $turno_actual;
            $ok = DH_Talleres::reducir_cupo( $taller_nuevo, $turno_dest );
            if ( ! $ok ) {
                // Revertir la reposición
                DH_Talleres::reducir_cupo( $taller_actual, $turno_actual );
                wp_send_json_error( array( 'msg' => 'No hay cupos disponibles en el taller/turno seleccionado.' ) );
            }
        }

        $update = array(
            'nombre'    => $nombre,
            'email'     => $email,
            'telefono'  => $telefono,
            'notas'     => $notas,
            'taller_id' => $taller_nuevo,
            'variantes' => ! empty( $variantes ) ? wp_json_encode( $variantes ) : null,
        );
        if ( $turno_nuevo ) $update['turno']     = $turno_nuevo;
        if ( $tipo_pago )   $update['tipo_pago'] = $tipo_pago;

        $wpdb->update( $wpdb->prefix . 'dh_inscripciones', $update, array( 'id' => $id ) );

        // Sincronizar billing en WC
        if ( $row->order_id ) {
            $order = wc_get_order( $row->order_id );
            if ( $order ) {
                $parts = explode( ' ', $nombre, 2 );
                $order->set_billing_first_name( $parts[0] );
                $order->set_billing_last_name( $parts[1] ?? '' );
                $order->set_billing_email( $email );
                $order->set_billing_phone( $telefono );
                $order->save();
            }
        }

        wp_send_json_success( array( 'msg' => 'Inscripción actualizada.' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: ELIMINAR inscripcion (soft delete → papelera)
    // ═══════════════════════════════════════════════
    public function ajax_eliminar_inscripcion() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        global $wpdb;
        $id  = absint( $_POST['id'] ?? 0 );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dh_inscripciones WHERE id=%d", $id ) );
        if ( ! $row ) wp_send_json_error( array( 'msg' => 'No encontrado.' ) );

        if ( $row->estado !== 'cancelado' ) {
            DH_Talleres::reponer_cupo( $row->taller_id, $row->turno );
        }
        if ( $row->order_id ) {
            $order = wc_get_order( $row->order_id );
            if ( $order && ! in_array( $order->get_status(), array( 'cancelled','refunded' ), true ) ) {
                $order->update_status( 'cancelled', 'Inscripción movida a la papelera.' );
            }
        }

        $wpdb->update(
            $wpdb->prefix . 'dh_inscripciones',
            array( 'deleted_at' => current_time( 'mysql' ), 'estado' => 'cancelado' ),
            array( 'id' => $id )
        );
        wp_send_json_success( array( 'msg' => 'Registro movido a la papelera.' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: CAMBIAR ESTADO
    // ═══════════════════════════════════════════════
    public function ajax_estado_inscripcion() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        global $wpdb;
        $id     = absint( $_POST['id'] ?? 0 );
        $estado = sanitize_text_field( $_POST['estado'] ?? '' );
        if ( ! in_array( $estado, array( 'pendiente','confirmado','cancelado' ), true ) ) {
            wp_send_json_error( array( 'msg' => 'Estado inválido.' ) );
        }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dh_inscripciones WHERE id=%d", $id ) );
        if ( ! $row ) wp_send_json_error( array( 'msg' => 'No encontrado.' ) );

        $anterior = $row->estado;
        if ( $estado === 'cancelado' && $anterior !== 'cancelado' ) {
            DH_Talleres::reponer_cupo( $row->taller_id, $row->turno );
        } elseif ( $estado !== 'cancelado' && $anterior === 'cancelado' ) {
            $ok = DH_Talleres::reducir_cupo( $row->taller_id, $row->turno );
            if ( ! $ok ) wp_send_json_error( array( 'msg' => 'No hay cupos disponibles para reactivar.' ) );
        }

        $wpdb->update( $wpdb->prefix . 'dh_inscripciones', array( 'estado' => $estado ), array( 'id' => $id ) );

        if ( $row->order_id ) {
            $order = wc_get_order( $row->order_id );
            if ( $order ) {
                $map = array( 'confirmado'=>'processing','cancelado'=>'cancelled','pendiente'=>'on-hold' );
                $notes = array( 'confirmado'=>'Confirmado manualmente.','cancelado'=>'Cancelado manualmente.','pendiente'=>'Reactivado manualmente.' );
                $order->update_status( $map[$estado], $notes[$estado] ?? '' );
            }
        }
        wp_send_json_success( array( 'msg' => 'Estado actualizado.' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: REENVIAR EMAIL
    // ═══════════════════════════════════════════════
    public function ajax_reenviar_email() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'msg' => 'ID inválido.' ) );
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dh_inscripciones WHERE id=%d", $id ) );
        if ( ! $row ) wp_send_json_error( array( 'msg' => 'Inscripción no encontrada.' ) );
        DH_Email::enviar_confirmacion( $id, (int) $row->order_id );
        wp_send_json_success( array( 'msg' => 'Email reenviado a ' . $row->email ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: RESTAURAR inscripcion desde papelera
    // ═══════════════════════════════════════════════
    public function ajax_restaurar_inscripcion() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->update(
            $wpdb->prefix . 'dh_inscripciones',
            array( 'deleted_at' => null, 'estado' => 'cancelado' ),
            array( 'id' => $id )
        );
        wp_send_json_success( array( 'msg' => 'Inscripción restaurada (estado: cancelado).' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: PURGAR inscripcion (eliminación definitiva)
    // ═══════════════════════════════════════════════
    public function ajax_purgar_inscripcion() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->delete( $wpdb->prefix . 'dh_inscripciones', array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'msg' => 'Eliminado definitivamente.' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: ELIMINAR TALLER
    // ═══════════════════════════════════════════════
    public function ajax_eliminar_taller() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id || 'dh_taller' !== get_post_type( $id ) ) wp_send_json_error( array( 'msg' => 'Taller no válido.' ) );
        wp_trash_post( $id );
        wp_send_json_success( array( 'msg' => 'Taller movido a la papelera.' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: RESTAURAR TALLER
    // ═══════════════════════════════════════════════
    public function ajax_restaurar_taller() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'msg' => 'ID inválido.' ) );
        wp_untrash_post( $id );
        wp_send_json_success( array( 'msg' => 'Taller restaurado.' ) );
    }

    // ═══════════════════════════════════════════════
    // AJAX: DUPLICAR TALLER
    // ═══════════════════════════════════════════════
    public function ajax_duplicar_taller() {
        check_ajax_referer( 'dh_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $id       = absint( $_POST['id'] ?? 0 );
        $original = get_post( $id );
        if ( ! $original || 'dh_taller' !== $original->post_type ) {
            wp_send_json_error( array( 'msg' => 'Taller no válido.' ) );
        }
        $nuevo_id = wp_insert_post( array(
            'post_title'  => $original->post_title . ' (copia)',
            'post_type'   => 'dh_taller',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ) );
        if ( is_wp_error( $nuevo_id ) ) wp_send_json_error( array( 'msg' => 'Error al duplicar.' ) );
        $skip = array( '_dh_product_id' );
        foreach ( get_post_meta( $id ) as $key => $values ) {
            if ( in_array( $key, $skip, true ) ) continue;
            if ( $key === '_dh_cupos_matutino' ) {
                update_post_meta( $nuevo_id, $key, get_post_meta( $id, '_dh_cupos_matutino_total', true ) );
            } elseif ( $key === '_dh_cupos_vespertino' ) {
                update_post_meta( $nuevo_id, $key, get_post_meta( $id, '_dh_cupos_vespertino_total', true ) );
            } else {
                update_post_meta( $nuevo_id, $key, $values[0] );
            }
        }
        wp_send_json_success( array( 'edit_url' => get_edit_post_link( $nuevo_id, 'raw' ), 'msg' => 'Taller duplicado.' ) );
    }

    // ═══════════════════════════════════════════════
    // PAPELERA DE RECICLAJE
    // ═══════════════════════════════════════════════
    public function page_papelera() {
        $talleres_trash = get_posts( array(
            'post_type'      => 'dh_taller',
            'post_status'    => 'trash',
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );
        $insc_trash = DH_Talleres::get_inscripciones_eliminadas();
        ?>
        <div class="wrap dh-admin-wrap">
            <div class="dh-admin-header">
                <div class="dh-admin-header-left">
                    <a href="<?php echo admin_url( 'admin.php?page=dh-talleres' ); ?>" class="dh-back-btn"><span class="dashicons dashicons-arrow-left-alt"></span></a>
                    <div><h1>🗑️ Papelera de reciclaje</h1><p class="dh-subtitle">Talleres y alumnos eliminados · Podés restaurarlos o eliminarlos definitivamente</p></div>
                </div>
            </div>

            <!-- ── TALLERES ELIMINADOS ── -->
            <h2 style="margin:28px 0 12px;font-size:15px;font-weight:700;color:#1d2327;display:flex;align-items:center;gap:8px;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                <span class="dashicons dashicons-calendar-alt" style="color:#8B5E3C;"></span>
                Talleres eliminados
                <span style="background:#f3e8de;color:#8B5E3C;font-size:12px;padding:2px 9px;border-radius:20px;font-weight:700;"><?php echo count($talleres_trash); ?></span>
            </h2>

            <?php if ( empty( $talleres_trash ) ) : ?>
            <div class="dh-empty-state" style="padding:28px 20px;">
                <span class="dashicons dashicons-calendar-alt" style="font-size:40px;color:#ddd;width:40px;height:40px;margin:0 auto;display:block;"></span>
                <p style="color:#aaa;margin:10px 0 0;text-align:center;">La papelera de talleres está vacía.</p>
            </div>
            <?php else : ?>
            <table class="dh-alumnos-table wp-list-table widefat" style="margin-bottom:36px;">
                <thead><tr>
                    <th>ID</th><th>Nombre del taller</th><th>Fecha</th><th>Tipo</th><th>Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $talleres_trash as $t ) :
                    $meta  = DH_Talleres::get_taller_meta( $t->ID );
                    $fecha = $meta['fecha'] ? date_i18n( 'd/m/Y', strtotime($meta['fecha']) ) : '—';
                ?>
                <tr id="dh-trash-taller-<?php echo $t->ID; ?>">
                    <td style="color:#aaa;"><?php echo $t->ID; ?></td>
                    <td><strong><?php echo esc_html( $t->post_title ); ?></strong></td>
                    <td><?php echo esc_html($fecha); ?></td>
                    <td><?php echo $meta['tipo_producto'] ? '<span class="dh-tag">'.esc_html($meta['tipo_producto']).'</span>' : '—'; ?></td>
                    <td class="dh-row-actions">
                        <button class="dh-action-btn dh-action-confirm" onclick="dhRestaurarTaller(<?php echo $t->ID; ?>)" title="Restaurar taller">
                            <span class="dashicons dashicons-undo"></span> Restaurar
                        </button>
                        <a href="<?php echo esc_url( get_delete_post_link( $t->ID, '', true ) ); ?>"
                           class="dh-action-btn dh-action-delete"
                           onclick="return confirm('¿Eliminar PERMANENTEMENTE este taller? Esta acción no se puede deshacer.')"
                           title="Eliminar definitivamente">
                            <span class="dashicons dashicons-trash"></span> Eliminar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- ── ALUMNOS ELIMINADOS ── -->
            <h2 style="margin:28px 0 12px;font-size:15px;font-weight:700;color:#1d2327;display:flex;align-items:center;gap:8px;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                <span class="dashicons dashicons-groups" style="color:#8B5E3C;"></span>
                Alumnos inscriptos eliminados
                <span style="background:#f3e8de;color:#8B5E3C;font-size:12px;padding:2px 9px;border-radius:20px;font-weight:700;"><?php echo count($insc_trash); ?></span>
            </h2>

            <?php if ( empty( $insc_trash ) ) : ?>
            <div class="dh-empty-state" style="padding:28px 20px;">
                <span class="dashicons dashicons-groups" style="font-size:40px;color:#ddd;width:40px;height:40px;margin:0 auto;display:block;"></span>
                <p style="color:#aaa;margin:10px 0 0;text-align:center;">La papelera de inscripciones está vacía.</p>
            </div>
            <?php else : ?>
            <table class="dh-alumnos-table wp-list-table widefat">
                <thead><tr>
                    <th>#</th><th>Nombre</th><th>Email</th><th>Taller</th>
                    <th>Turno</th><th>Pago</th><th>Eliminado el</th><th>Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $insc_trash as $i ) :
                    $tipo_pago_label = array( 'sena'=>'Seña','total'=>'Total','cortesia'=>'Cortesía' )[$i->tipo_pago] ?? ucfirst($i->tipo_pago);
                    $pago_cls = $i->tipo_pago === 'sena' ? 'pago-sena' : ( $i->tipo_pago === 'cortesia' ? 'pago-cortesia' : 'pago-total' );
                ?>
                <tr id="dh-trash-insc-<?php echo $i->id; ?>">
                    <td style="color:#aaa;"><?php echo $i->id; ?></td>
                    <td><strong><?php echo esc_html( $i->nombre ); ?></strong></td>
                    <td><?php echo esc_html( $i->email ); ?></td>
                    <td><?php echo esc_html( get_the_title( $i->taller_id ) ); ?></td>
                    <td><?php echo 'matutino' === $i->turno ? '<span class="dh-badge mat">☀️ Mat.</span>' : '<span class="dh-badge ves">🌇 Ves.</span>'; ?></td>
                    <td><span class="dh-badge <?php echo $pago_cls; ?>"><?php echo $tipo_pago_label; ?></span></td>
                    <td><small style="color:#999;"><?php echo date_i18n( 'd/m/Y H:i', strtotime( $i->deleted_at ) ); ?></small></td>
                    <td class="dh-row-actions">
                        <button class="dh-action-btn dh-action-confirm" onclick="dhRestaurarInscripcion(<?php echo $i->id; ?>)" title="Restaurar inscripción">
                            <span class="dashicons dashicons-undo"></span> Restaurar
                        </button>
                        <button class="dh-action-btn dh-action-delete" onclick="dhPurgarInscripcion(<?php echo $i->id; ?>)" title="Eliminar definitivamente">
                            <span class="dashicons dashicons-trash"></span> Eliminar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ═══════════════════════════════════════════════
    // REGISTRO MANUAL
    // ═══════════════════════════════════════════════
    public function page_registro_manual() {
        $talleres         = DH_Talleres::get_talleres();
        $tipos            = DH_Settings::get_tipos_producto();
        $opciones_default = ! empty( $tipos ) ? $tipos[0] : array( 'colores'=>array(),'tipos_lana'=>array(),'micras'=>array(),'medidas'=>array() );
        $taller_presel    = isset( $_GET['taller_id'] ) ? absint( $_GET['taller_id'] ) : 0;
        $saved            = isset( $_GET['saved'] );
        $error            = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
        $taller_tipos_map = array();
        foreach ( $talleres as $t ) {
            $m = DH_Talleres::get_taller_meta( $t->ID );
            $taller_tipos_map[ $t->ID ] = $m['tipo_producto'] ?: ( ! empty( $tipos ) ? $tipos[0]['slug'] : '' );
        }
        ?>
        <div class="wrap dh-admin-wrap">
            <div class="dh-admin-header">
                <div class="dh-admin-header-left">
                    <a href="<?php echo admin_url( 'admin.php?page=dh-alumnos' ); ?>" class="dh-back-btn"><span class="dashicons dashicons-arrow-left-alt"></span></a>
                    <div><h1>📝 Registro Manual</h1><p class="dh-subtitle">Inscribir un alumno sin pasar por el proceso de pago</p></div>
                </div>
            </div>
            <script>
            var dhTallerTiposMap = <?php echo wp_json_encode( $taller_tipos_map ); ?>;
            var dhTiposProducto  = <?php echo wp_json_encode( $tipos ); ?>;
            function dhLoadTallerInfo(tallerId) {
                var tipoSlug = dhTallerTiposMap[tallerId] || (dhTiposProducto.length ? dhTiposProducto[0].slug : '');
                var tipo = dhTiposProducto.find ? dhTiposProducto.find(function(t){return t.slug===tipoSlug;}) : null;
                if (!tipo && dhTiposProducto.length) tipo = dhTiposProducto[0];
                if (!tipo) return;
                ['color','tipo_lana','micras','medida'].forEach(function(k){
                    var $sel = jQuery('[name="variante_'+k+'"]');
                    if (!$sel.length) return;
                    var campo = k==='tipo_lana'?'tipos_lana':(k==='medida'?'medidas':(k==='micras'?'micras':'colores'));
                    var lista = tipo[campo]||[];
                    $sel.find('option:not(:first)').remove();
                    lista.forEach(function(item){var val=typeof item==='object'?item.nombre:item;$sel.append(new Option(val,val));});
                });
            }
            jQuery(function(){if(<?php echo $taller_presel; ?>)dhLoadTallerInfo(<?php echo $taller_presel; ?>);});
            </script>
            <?php if ($saved): ?><div class="notice notice-success is-dismissible"><p>✅ Alumno inscripto correctamente.</p></div><?php endif; ?>
            <?php if ($error):  ?><div class="notice notice-error  is-dismissible"><p>❌ Error: <?php echo esc_html( urldecode($error) ); ?></p></div><?php endif; ?>
            <div class="dh-form-card">
                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="dh-manual-form">
                    <?php wp_nonce_field( 'dh_registro_manual', 'dh_manual_nonce' ); ?>
                    <input type="hidden" name="action" value="dh_registro_manual">
                    <div class="dh-form-section-title">📋 Datos del taller</div>
                    <div class="dh-form-row-3">
                        <div class="dh-form-group">
                            <label>Taller <span class="req">*</span></label>
                            <select name="taller_id" required onchange="dhLoadTallerInfo(this.value)">
                                <option value="">— Seleccioná un taller —</option>
                                <?php foreach ( $talleres as $t ) :
                                    $f   = get_post_meta( $t->ID, '_dh_fecha', true );
                                    $lbl = get_the_title($t->ID) . ($f ? ' · ' . date_i18n('d/m/Y',strtotime($f)) : '');
                                ?>
                                <option value="<?php echo $t->ID; ?>" <?php selected($taller_presel,$t->ID); ?>><?php echo esc_html($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dh-form-group">
                            <label>Turno <span class="req">*</span></label>
                            <select name="turno" required>
                                <option value="">— Seleccioná —</option>
                                <option value="matutino">☀️ Matutino</option>
                                <option value="vespertino">🌇 Vespertino</option>
                            </select>
                        </div>
                        <div class="dh-form-group">
                            <label>Tipo de pago <span class="req">*</span></label>
                            <select name="tipo_pago" required>
                                <option value="">— Seleccioná —</option>
                                <option value="sena">💰 Seña</option>
                                <option value="total">✅ Total</option>
                                <option value="cortesia">🎁 Cortesía (sin cupo)</option>
                            </select>
                        </div>
                    </div>
                    <div class="dh-form-section-title" style="margin-top:20px;">👤 Datos del alumno</div>
                    <div class="dh-form-row-3">
                        <div class="dh-form-group">
                            <label>Nombre completo <span class="req">*</span></label>
                            <input type="text" name="nombre" required placeholder="Ej: María González">
                        </div>
                        <div class="dh-form-group">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" required placeholder="alumno@email.com">
                        </div>
                        <div class="dh-form-group">
                            <label>Teléfono</label>
                            <input type="tel" name="telefono" placeholder="099 000 000">
                        </div>
                    </div>
                    <div class="dh-form-section-title" style="margin-top:20px;">🎨 Material (opcional)</div>
                    <div class="dh-form-row-4">
                        <div class="dh-form-group"><label>🎨 Color</label>
                            <select name="variante_color"><option value="">— Sin especificar —</option>
                            <?php foreach ($opciones_default['colores']??array() as $c): ?><option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="dh-form-group"><label>🧶 Tipo de lana</label>
                            <select name="variante_tipo_lana"><option value="">— Sin especificar —</option>
                            <?php foreach ($opciones_default['tipos_lana']??array() as $t): ?><option value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="dh-form-group"><label>🔬 Micras</label>
                            <select name="variante_micras"><option value="">— Sin especificar —</option>
                            <?php foreach ($opciones_default['micras']??array() as $m): ?><option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="dh-form-group"><label>📏 Medida</label>
                            <select name="variante_medida"><option value="">— Sin especificar —</option>
                            <?php foreach ($opciones_default['medidas']??array() as $m): $nombre=is_array($m)?$m['nombre']:$m; ?>
                            <option value="<?php echo esc_attr($nombre); ?>"><?php echo esc_html($nombre); ?></option><?php endforeach; ?>
                            </select></div>
                    </div>
                    <div class="dh-form-group" style="margin-top:18px;">
                        <label>📝 Notas internas</label>
                        <textarea name="notas" rows="3" placeholder="Observaciones, acuerdos especiales…"></textarea>
                    </div>
                    <div class="dh-form-group" style="margin-top:12px;">
                        <label><input type="checkbox" name="enviar_email" value="1" checked> Enviar email de confirmación al alumno</label>
                    </div>
                    <div style="margin-top:24px;display:flex;gap:12px;">
                        <button type="submit" class="dh-btn dh-btn-primary" style="padding:12px 32px;font-size:15px;">
                            <span class="dashicons dashicons-yes-alt"></span> Inscribir alumno
                        </button>
                        <a href="<?php echo admin_url( 'admin.php?page=dh-alumnos' ); ?>" class="dh-btn dh-btn-ghost">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public function guardar_registro_manual() {
        if ( ! wp_verify_nonce( $_POST['dh_manual_nonce'] ?? '', 'dh_registro_manual' ) ) wp_die( 'No autorizado' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Sin permisos' );
        $taller_id = absint( $_POST['taller_id'] ?? 0 );
        $turno     = sanitize_text_field( $_POST['turno']     ?? '' );
        $tipo_pago = sanitize_text_field( $_POST['tipo_pago'] ?? '' );
        $nombre    = sanitize_text_field( $_POST['nombre']    ?? '' );
        $email     = sanitize_email( $_POST['email']          ?? '' );
        if ( ! $taller_id || ! $turno || ! $tipo_pago || ! $nombre || ! $email ) {
            wp_safe_redirect( admin_url( 'admin.php?page=dh-registro-manual&error=' . urlencode('Todos los campos obligatorios son necesarios.') ) );
            exit;
        }
        if ( 'cortesia' !== $tipo_pago ) {
            $meta     = DH_Talleres::get_taller_meta( $taller_id );
            $cupo_key = 'matutino' === $turno ? 'cupos_matutino' : 'cupos_vespertino';
            if ( $meta[$cupo_key] <= 0 ) {
                wp_safe_redirect( admin_url( 'admin.php?page=dh-registro-manual&error=' . urlencode('El turno seleccionado no tiene cupos disponibles.') ) );
                exit;
            }
            DH_Talleres::reducir_cupo( $taller_id, $turno );
        }
        $variantes = array();
        foreach ( array('color','tipo_lana','micras','medida') as $vk ) {
            $v = sanitize_text_field( $_POST['variante_'.$vk] ?? '' );
            if ($v) $variantes[$vk] = $v;
        }
        $id = DH_Talleres::guardar_inscripcion( array(
            'taller_id' => $taller_id, 'order_id'  => 0,
            'turno'     => $turno,     'tipo_pago'  => $tipo_pago,
            'nombre'    => $nombre,    'email'      => $email,
            'telefono'  => sanitize_text_field( $_POST['telefono'] ?? '' ),
            'variantes' => $variantes,
            'notas'     => sanitize_textarea_field( $_POST['notas'] ?? '' ),
            'estado'    => 'confirmado',
        ) );
        if ( ! empty( $_POST['enviar_email'] ) && $id ) {
            DH_Email::enviar_confirmacion( $id, 0 );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=dh-alumnos&saved=1&taller_id=' . $taller_id ) );
        exit;
    }

    // ═══════════════════════════════════════════════
    // INFORMES
    // ═══════════════════════════════════════════════
    public function page_informes() {
        global $wpdb;
        $talleres    = DH_Talleres::get_talleres( array( 'posts_per_page' => -1 ) );
        $fecha_desde = isset( $_GET['desde'] ) ? sanitize_text_field( $_GET['desde'] ) : '';
        $fecha_hasta = isset( $_GET['hasta'] ) ? sanitize_text_field( $_GET['hasta'] ) : '';
        $resumen = array();
        $totales = array( 'inscriptos'=>0,'confirmados'=>0,'pendientes'=>0,'cancelados'=>0,
                          'ingresos_sena'=>0,'ingresos_total'=>0 );

        foreach ( $talleres as $t ) {
            $meta  = DH_Talleres::get_taller_meta( $t->ID );
            $fecha = $meta['fecha'];
            if ( $fecha_desde && $fecha && $fecha < $fecha_desde ) continue;
            if ( $fecha_hasta && $fecha && $fecha > $fecha_hasta ) continue;
            $insc  = DH_Talleres::get_inscripciones( $t->ID );
            $conf  = array_filter( $insc, function($i){ return $i->estado==='confirmado'; });
            $pend  = array_filter( $insc, function($i){ return $i->estado==='pendiente'; });
            $canc  = array_filter( $insc, function($i){ return $i->estado==='cancelado'; });
            $ing_sena = $ing_total = 0;
            foreach ( $insc as $i ) {
                if ( $i->estado === 'cancelado' ) continue;
                if ( $i->tipo_pago === 'sena'  ) $ing_sena  += floatval($meta['precio_sena']);
                if ( $i->tipo_pago === 'total' ) $ing_total += floatval($meta['precio_total']);
            }
            $row = array(
                'id'=>$t->ID, 'titulo'=>get_the_title($t->ID), 'fecha'=>$fecha,
                'tipo_producto'=>$meta['tipo_producto'],
                'total_insc'=>count($insc), 'confirmados'=>count($conf),
                'pendientes'=>count($pend), 'cancelados'=>count($canc),
                'ingresos_sena'=>$ing_sena, 'ingresos_total'=>$ing_total,
                'ingresos_real'=>$ing_sena+$ing_total,
                'cortesias'=>count(array_filter($insc,function($i){return $i->tipo_pago==='cortesia'&&$i->estado!=='cancelado';})),
            );
            $resumen[] = $row;
            $totales['inscriptos']     += $row['total_insc'];
            $totales['confirmados']    += $row['confirmados'];
            $totales['pendientes']     += $row['pendientes'];
            $totales['cancelados']     += $row['cancelados'];
            $totales['ingresos_sena']  += $ing_sena;
            $totales['ingresos_total'] += $ing_total;
        }
        $totales['ingresos_real'] = $totales['ingresos_sena'] + $totales['ingresos_total'];
        ?>
        <div class="wrap dh-admin-wrap">
            <div class="dh-admin-header">
                <div class="dh-admin-header-left">
                    <div><h1>📊 Informes</h1><p class="dh-subtitle">Resumen de ingresos y alumnos por taller</p></div>
                </div>
                <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=dh_export_csv_informes&desde='.$fecha_desde.'&hasta='.$fecha_hasta),'dh_export_csv_informes'); ?>" class="dh-btn dh-btn-outline">
                    <span class="dashicons dashicons-download"></span> Exportar CSV
                </a>
            </div>
            <div class="dh-filters-bar">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>" class="dh-filters-form">
                    <input type="hidden" name="page" value="dh-informes">
                    <div class="dh-filter-group"><label>Desde</label><input type="date" name="desde" value="<?php echo esc_attr($fecha_desde); ?>"></div>
                    <div class="dh-filter-group"><label>Hasta</label><input type="date" name="hasta" value="<?php echo esc_attr($fecha_hasta); ?>"></div>
                    <button type="submit" class="dh-btn dh-btn-primary">Filtrar</button>
                    <a href="<?php echo admin_url('admin.php?page=dh-informes'); ?>" class="dh-btn dh-btn-ghost">Limpiar</a>
                </form>
            </div>
            <div class="dh-stats-row" style="margin-bottom:24px;">
                <div class="dh-stat-box"><span class="dh-stat-number"><?php echo $totales['inscriptos']; ?></span><span class="dh-stat-label">Total inscriptos</span></div>
                <div class="dh-stat-box confirmed"><span class="dh-stat-number"><?php echo $totales['confirmados']; ?></span><span class="dh-stat-label">Confirmados</span></div>
                <div class="dh-stat-box pending"><span class="dh-stat-number"><?php echo $totales['pendientes']; ?></span><span class="dh-stat-label">En espera</span></div>
                <div class="dh-stat-box cancelled"><span class="dh-stat-number"><?php echo $totales['cancelados']; ?></span><span class="dh-stat-label">Cancelados</span></div>
                <div class="dh-stat-box" style="border-left:4px solid #8B5E3C;"><span class="dh-stat-number" style="color:#8B5E3C;">$<?php echo number_format($totales['ingresos_sena'],0,',','.'); ?></span><span class="dh-stat-label">Ingresos seña</span></div>
                <div class="dh-stat-box" style="border-left:4px solid #3a9966;"><span class="dh-stat-number" style="color:#3a9966;">$<?php echo number_format($totales['ingresos_total'],0,',','.'); ?></span><span class="dh-stat-label">Ingresos total</span></div>
                <div class="dh-stat-box" style="border-left:4px solid #2271b1;"><span class="dh-stat-number" style="color:#2271b1;">$<?php echo number_format($totales['ingresos_real'],0,',','.'); ?></span><span class="dh-stat-label">Total generado</span></div>
            </div>
            <?php if ( empty($resumen) ) : ?>
            <div class="dh-empty-state">
                <span class="dashicons dashicons-chart-pie" style="font-size:64px;color:#ccc;width:64px;height:64px;"></span>
                <h2>Sin datos para el período seleccionado</h2>
            </div>
            <?php else : ?>
            <table class="dh-alumnos-table wp-list-table widefat">
                <thead><tr>
                    <th>Taller</th><th>Fecha</th><th>Tipo</th>
                    <th>Inscriptos</th><th>Confirmados</th><th>En espera</th><th>Cancelados</th>
                    <th>Ing. Seña</th><th>Ing. Total</th><th>Total generado</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $resumen as $r ) : ?>
                <tr>
                    <td><strong><?php echo esc_html($r['titulo']); ?></strong></td>
                    <td><?php echo $r['fecha'] ? date_i18n('d/m/Y',strtotime($r['fecha'])) : '—'; ?></td>
                    <td><?php echo $r['tipo_producto'] ? '<span class="dh-tag">'.esc_html($r['tipo_producto']).'</span>' : '—'; ?></td>
                    <td><strong><?php echo $r['total_insc']; ?></strong></td>
                    <td><span class="dh-estado confirmado"><?php echo $r['confirmados']; ?></span></td>
                    <td><span class="dh-estado pendiente"><?php echo $r['pendientes']; ?></span></td>
                    <td><span class="dh-estado cancelado"><?php echo $r['cancelados']; ?></span></td>
                    <td>$<?php echo number_format($r['ingresos_sena'],0,',','.'); ?></td>
                    <td>$<?php echo number_format($r['ingresos_total'],0,',','.'); ?></td>
                    <td><strong style="color:#8B5E3C;">$<?php echo number_format($r['ingresos_real'],0,',','.'); ?></strong></td>
                    <td><a href="<?php echo admin_url('admin.php?page=dh-alumnos&taller_id='.$r['id']); ?>" class="dh-btn dh-btn-sm dh-btn-outline">Ver alumnos</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ═══════════════════════════════════════════════
    // EXPORT CSV
    // ═══════════════════════════════════════════════
    public function export_csv() {
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dh_export_csv' ) ) wp_die( 'No autorizado' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Sin permisos' );
        $taller_id = isset($_GET['taller_id']) ? absint($_GET['taller_id']) : 0;
        $turno     = isset($_GET['turno'])     ? sanitize_text_field($_GET['turno']) : '';
        $rows      = DH_Talleres::get_inscripciones( $taller_id ?: null, $turno ?: null );
        $filename  = 'inscripciones-talleres-' . date('Y-m-d') . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="'.$filename.'"' );
        $out = fopen( 'php://output', 'w' );
        fputs( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array('ID','Nombre','Email','Teléfono','Taller','Fecha Taller','Turno','Tipo Pago','Color','Tipo Lana','Micras','Medida','Estado','Pedido WC','Notas','Fecha Registro') );
        foreach ( $rows as $r ) {
            $ft = get_post_meta( $r->taller_id, '_dh_fecha', true );
            $vv = $r->variantes ? json_decode($r->variantes,true) : array();
            fputcsv( $out, array(
                $r->id,$r->nombre,$r->email,$r->telefono,
                get_the_title($r->taller_id),
                $ft ? date_i18n('d/m/Y',strtotime($ft)) : '',
                ucfirst($r->turno),
                array('sena'=>'Seña','total'=>'Total','cortesia'=>'Cortesía')[$r->tipo_pago]??$r->tipo_pago,
                $vv['color']??'',$vv['tipo_lana']??'',$vv['micras']??'',$vv['medida']??'',
                ucfirst($r->estado),$r->order_id?:'', $r->notas??'',
                date_i18n('d/m/Y H:i',strtotime($r->created_at)),
            ) );
        }
        fclose($out);
        exit;
    }
}
