<?php

/*
Plugin Name: Enlaces Rotos Reporter
Description: Permite a los usuarios reportar enlaces rotos al final de cada entrada.
Version: 0.0.1
Author: Samuel Soriano
Text Domain: enlaces-rotos-reporter
*/


// Eliminar tablas al desinstalar el plugin
register_uninstall_hook(__FILE__, 'enlaces_rotos_eliminar_tablas_multisite');
function enlaces_rotos_eliminar_tablas_multisite() {
    if (is_multisite()) {
        global $wpdb;
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            enlaces_rotos_eliminar_tabla();
            restore_current_blog();
        }
    } else {
        enlaces_rotos_eliminar_tabla();
    }
}

function enlaces_rotos_eliminar_tabla() {
    global $wpdb;
    $table = $wpdb->prefix . 'enlaces_rotos_reportes';
    $wpdb->query("DROP TABLE IF EXISTS $table");
}



if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Cargar archivos de idioma del plugin
add_action('plugins_loaded', function() {
    load_plugin_textdomain('enlaces-rotos-reporter', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Incluir archivos necesarios
// require_once plugin_dir_path(__FILE__) . 'includes/functions.php';

// Mostrar botón al final de cada entrada
add_filter('the_content', 'enlaces_rotos_agregar_boton');
function enlaces_rotos_agregar_boton($content) {
    if (is_single()) {
        $content .= '<style>
        #enlaces-rotos-reportar { margin-top: 30px; }
        #enlaces-rotos-reportar button { background: #d32f2f; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; }
        #enlaces-rotos-form { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-top: 10px; border-radius: 4px; max-width: 400px; }
        #enlaces-rotos-form label { display: block; margin-bottom: 8px; font-weight: bold; }
        #enlaces-rotos-form input[type="text"] { width: 100%; padding: 8px; margin-bottom: 10px; border-radius: 3px; border: 1px solid #ccc; }
        #enlaces-rotos-form button { background: #388e3c; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        </style>';
        $nonce = wp_create_nonce('enlaces_rotos_nonce');
        $content .= '<div id="enlaces-rotos-reportar"><button onclick="document.getElementById(\'enlaces-rotos-form\').style.display=\'block\'">'.esc_html(__('Report broken link', 'enlaces-rotos-reporter')).'</button></div>';
        $content .= '<div id="enlaces-rotos-form" style="display:none;"><form method="post"><input type="hidden" name="enlaces_rotos_post_id" value="'.esc_attr(get_the_ID()).'" /><input type="hidden" name="enlaces_rotos_nonce" value="'.esc_attr($nonce).'" /><label>'.esc_html(__('Which link is broken?', 'enlaces-rotos-reporter')).'</label><input type="text" name="enlace_roto" required maxlength="255" /><button type="submit">'.esc_html(__('Send report', 'enlaces-rotos-reporter')).'</button></form></div>';
    }
    return $content;
}

// Procesar reporte
add_action('init', 'enlaces_rotos_procesar_reporte');
function enlaces_rotos_procesar_reporte() {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['enlaces_rotos_post_id']) &&
        isset($_POST['enlace_roto']) &&
        isset($_POST['enlaces_rotos_nonce']) &&
        wp_verify_nonce($_POST['enlaces_rotos_nonce'], 'enlaces_rotos_nonce')
    ) {
        global $wpdb;
        $table = $wpdb->prefix . 'enlaces_rotos_reportes';
        $post_id = intval($_POST['enlaces_rotos_post_id']);
        $enlace = sanitize_text_field($_POST['enlace_roto']);
        $wpdb->insert($table, [
            'post_id' => $post_id,
            'enlace' => $enlace,
            'fecha' => current_time('mysql')
        ]);
        // Notificar al administrador
        $admin_email = get_option('admin_email');
        $post_url = get_permalink($post_id);
        $subject = __('New broken link report', 'enlaces-rotos-reporter');
        $message = __('A broken link has been reported on the site:', 'enlaces-rotos-reporter')."\n\n";
        $message .= sprintf(__('Post: %s (ID: %d)', 'enlaces-rotos-reporter'), $post_url, $post_id)."\n";
        $message .= sprintf(__('Reported link: %s', 'enlaces-rotos-reporter'), $enlace)."\n";
        $message .= sprintf(__('Date: %s', 'enlaces-rotos-reporter'), current_time('mysql'))."\n";
        wp_mail($admin_email, $subject, $message);
        // Mensaje de confirmación (puedes mejorar esto con JS)
        add_action('wp_footer', function() {
            echo '<script>alert("'.esc_js(__('Report sent! Thank you for collaborating.', 'enlaces-rotos-reporter')).'");</script>';
        });
    }
}

// Crear tabla en la activación
register_activation_hook(__FILE__, 'enlaces_rotos_crear_tabla_multisite');
function enlaces_rotos_crear_tabla_multisite($network_wide) {
    if (is_multisite() && $network_wide) {
        // Obtener todos los blogs/sites
        global $wpdb;
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            enlaces_rotos_crear_tabla();
            restore_current_blog();
        }
    } else {
        enlaces_rotos_crear_tabla();
    }
}

function enlaces_rotos_crear_tabla() {
    global $wpdb;
    $table = $wpdb->prefix . 'enlaces_rotos_reportes';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        enlace text NOT NULL,
        fecha datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Panel de administración para ver reportes
add_action('admin_menu', 'enlaces_rotos_admin_menu');
function enlaces_rotos_admin_menu() {
    add_menu_page(__('Broken Links Reports', 'enlaces-rotos-reporter'), __('Broken Links', 'enlaces-rotos-reporter'), 'manage_options', 'enlaces-rotos', 'enlaces_rotos_admin_page');
}
function enlaces_rotos_admin_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    global $wpdb;
    $table = $wpdb->prefix . 'enlaces_rotos_reportes';
    $por_pagina = 20;
    $pagina = isset($_GET['paginacion']) ? max(1, intval($_GET['paginacion'])) : 1;
    $offset = ($pagina - 1) * $por_pagina;
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $reportes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY fecha DESC LIMIT %d OFFSET %d", $por_pagina, $offset));
    $total_paginas = ceil($total / $por_pagina);
    echo '<div class="wrap"><h1 style="margin-bottom:20px;">'.__('Broken Links Reports', 'enlaces-rotos-reporter').'</h1>';
    echo '<table class="widefat" style="background:#fff; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05);">';
    echo '<thead><tr style="background:#f44336; color:#fff;"><th>ID</th><th>'.__('Post', 'enlaces-rotos-reporter').'</th><th>'.__('Link', 'enlaces-rotos-reporter').'</th><th>'.__('Date', 'enlaces-rotos-reporter').'</th></tr></thead><tbody>';
    foreach ($reportes as $r) {
        echo '<tr style="border-bottom:1px solid #eee;">'
            .'<td>'.esc_html($r->id).'</td>'
            .'<td><a href="'.esc_url(get_permalink($r->post_id)).'" target="_blank">'.esc_html($r->post_id).'</a></td>'
            .'<td style="word-break:break-all;">'.esc_html($r->enlace).'</td>'
            .'<td>'.esc_html($r->fecha).'</td>'
            .'</tr>';
    }
    echo '</tbody></table>';
    // Paginación
    if ($total_paginas > 1) {
        echo '<div style="margin:20px 0; text-align:center;">';
        for ($i = 1; $i <= $total_paginas; $i++) {
            if ($i == $pagina) {
                echo '<span style="padding:6px 12px; background:#f44336; color:#fff; border-radius:3px; margin:0 2px;">'.$i.'</span>';
            } else {
                echo '<a style="padding:6px 12px; background:#eee; color:#333; border-radius:3px; margin:0 2px; text-decoration:none;" href="?page=enlaces-rotos&paginacion='.$i.'">'.$i.'</a>';
            }
        }
        echo '</div>';
    }
    echo '</div>';
}
