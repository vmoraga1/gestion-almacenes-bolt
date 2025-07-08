<?php
/**
 * Clase para manejar mensajes y notificaciones del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Messages {
    
    /**
     * Instancia única de la clase
     */
    private static $instance = null;
    
    /**
     * Clave para almacenar mensajes en sesión
     */
    private $session_key = 'modulo_ventas_messages';
    
    /**
     * Tipos de mensajes permitidos
     */
    private $message_types = array('success', 'error', 'warning', 'info');
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Iniciar sesión si no está iniciada
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // Hooks
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('modulo_ventas_messages', array($this, 'display_messages'));
        
        // AJAX para dismiss de mensajes
        add_action('wp_ajax_mv_dismiss_message', array($this, 'ajax_dismiss_message'));
    }
    
    /**
     * Agregar un mensaje
     */
    public function add_message($message, $type = 'info', $context = 'general', $dismissible = true) {
        // Validar tipo
        if (!in_array($type, $this->message_types)) {
            $type = 'info';
        }
        
        // Crear estructura del mensaje
        $message_data = array(
            'id' => uniqid('mv_msg_'),
            'message' => $message,
            'type' => $type,
            'context' => $context,
            'dismissible' => $dismissible,
            'timestamp' => current_time('timestamp'),
            'user_id' => get_current_user_id()
        );
        
        // Obtener mensajes existentes
        $messages = $this->get_messages();
        
        // Agregar nuevo mensaje
        $messages[] = $message_data;
        
        // Guardar en sesión
        $this->save_messages($messages);
        
        // Hook para permitir acciones adicionales
        do_action('modulo_ventas_message_added', $message_data);
        
        return $message_data['id'];
    }
    
    /**
     * Agregar mensaje de éxito
     */
    public function success($message, $context = 'general', $dismissible = true) {
        return $this->add_message($message, 'success', $context, $dismissible);
    }
    
    /**
     * Agregar mensaje de error
     */
    public function error($message, $context = 'general', $dismissible = true) {
        return $this->add_message($message, 'error', $context, $dismissible);
    }
    
    /**
     * Agregar mensaje de advertencia
     */
    public function warning($message, $context = 'general', $dismissible = true) {
        return $this->add_message($message, 'warning', $context, $dismissible);
    }
    
    /**
     * Agregar mensaje informativo
     */
    public function info($message, $context = 'general', $dismissible = true) {
        return $this->add_message($message, 'info', $context, $dismissible);
    }
    
    /**
     * Obtener mensajes
     */
    public function get_messages($context = null) {
        $messages = isset($_SESSION[$this->session_key]) ? $_SESSION[$this->session_key] : array();
        
        // Filtrar por contexto si se especifica
        if ($context !== null) {
            $messages = array_filter($messages, function($msg) use ($context) {
                return $msg['context'] === $context;
            });
        }
        
        return $messages;
    }
    
    /**
     * Obtener y limpiar mensajes
     */
    public function get_and_clear_messages($context = null) {
        $messages = $this->get_messages($context);
        
        if ($context === null) {
            // Limpiar todos los mensajes
            $this->clear_messages();
        } else {
            // Limpiar solo mensajes del contexto especificado
            $remaining_messages = array_filter($this->get_messages(), function($msg) use ($context) {
                return $msg['context'] !== $context;
            });
            $this->save_messages($remaining_messages);
        }
        
        return $messages;
    }
    
    /**
     * Guardar mensajes en sesión
     */
    private function save_messages($messages) {
        $_SESSION[$this->session_key] = $messages;
    }
    
    /**
     * Limpiar todos los mensajes
     */
    public function clear_messages() {
        unset($_SESSION[$this->session_key]);
    }
    
    /**
     * Eliminar un mensaje específico
     */
    public function remove_message($message_id) {
        $messages = $this->get_messages();
        
        $messages = array_filter($messages, function($msg) use ($message_id) {
            return $msg['id'] !== $message_id;
        });
        
        $this->save_messages(array_values($messages));
    }
    
    /**
     * Mostrar mensajes
     */
    public function display_messages($context = null, $echo = true) {
        $messages = $this->get_and_clear_messages($context);
        
        if (empty($messages)) {
            return '';
        }
        
        // Agrupar mensajes por tipo
        $grouped_messages = array();
        foreach ($messages as $message) {
            $grouped_messages[$message['type']][] = $message;
        }
        
        ob_start();
        ?>
        <div class="mv-messages-container">
            <?php foreach ($grouped_messages as $type => $type_messages) : ?>
                <?php foreach ($type_messages as $message) : ?>
                    <div class="mv-message notice notice-<?php echo esc_attr($type); ?> <?php echo $message['dismissible'] ? 'is-dismissible' : ''; ?>" 
                         data-message-id="<?php echo esc_attr($message['id']); ?>">
                        <p><?php echo wp_kses_post($message['message']); ?></p>
                        <?php if ($message['dismissible']) : ?>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text"><?php _e('Descartar este aviso', 'modulo-ventas'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Manejar dismiss de mensajes
            $('.mv-message .notice-dismiss').on('click', function() {
                var $message = $(this).closest('.mv-message');
                var messageId = $message.data('message-id');
                
                // Ocultar mensaje
                $message.fadeOut();
                
                // Notificar al servidor si es necesario
                if (messageId) {
                    $.post(ajaxurl, {
                        action: 'mv_dismiss_message',
                        message_id: messageId,
                        nonce: '<?php echo wp_create_nonce('mv_dismiss_message'); ?>'
                    });
                }
            });
            
            // Auto-ocultar mensajes de éxito después de 5 segundos
            setTimeout(function() {
                $('.mv-message.notice-success').fadeOut();
            }, 5000);
        });
        </script>
        
        <style type="text/css">
        .mv-messages-container {
            margin: 15px 0;
        }
        .mv-message {
            margin: 5px 0 5px 0 !important;
        }
        .mv-message p {
            margin: 0.5em 0;
            padding: 2px;
        }
        </style>
        <?php
        
        $output = ob_get_clean();
        
        if ($echo) {
            echo $output;
        }
        
        return $output;
    }
    
    /**
     * Mostrar avisos en admin
     */
    public function display_admin_notices() {
        // Solo mostrar en páginas del plugin
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'modulo-ventas') === false) {
            return;
        }
        
        $this->display_messages('admin');
    }
    
    /**
     * AJAX: Descartar mensaje
     */
    public function ajax_dismiss_message() {
        check_ajax_referer('mv_dismiss_message', 'nonce');
        
        $message_id = isset($_POST['message_id']) ? sanitize_text_field($_POST['message_id']) : '';
        
        if ($message_id) {
            $this->remove_message($message_id);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Métodos para mensajes transitorios (usando transients)
     */
    
    /**
     * Agregar mensaje transitorio
     */
    public function add_transient_message($message, $type = 'info', $user_id = null, $expiration = 300) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $transient_key = 'mv_transient_msg_' . $user_id;
        $messages = get_transient($transient_key) ?: array();
        
        $messages[] = array(
            'message' => $message,
            'type' => $type,
            'time' => current_time('timestamp')
        );
        
        set_transient($transient_key, $messages, $expiration);
    }
    
    /**
     * Obtener y limpiar mensajes transitorios
     */
    public function get_transient_messages($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $transient_key = 'mv_transient_msg_' . $user_id;
        $messages = get_transient($transient_key) ?: array();
        
        // Limpiar después de obtener
        delete_transient($transient_key);
        
        return $messages;
    }
    
    /**
     * Métodos para notificaciones persistentes
     */
    
    /**
     * Crear notificación persistente
     */
    public function create_notification($title, $message, $type = 'info', $args = array()) {
        $defaults = array(
            'user_id' => get_current_user_id(),
            'link' => '',
            'link_text' => __('Ver más', 'modulo-ventas'),
            'icon' => $this->get_icon_for_type($type),
            'persistent' => true,
            'email' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Guardar en base de datos
        global $wpdb;
        $table = $wpdb->prefix . 'mv_notifications';
        
        $data = array(
            'user_id' => $args['user_id'],
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $args['link'],
            'icon' => $args['icon'],
            'is_read' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $data);
        
        if ($result && $args['email']) {
            $this->send_notification_email($args['user_id'], $title, $message, $args['link']);
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Obtener notificaciones no leídas
     */
    public function get_unread_notifications($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'mv_notifications';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE user_id = %d AND is_read = 0 
             ORDER BY created_at DESC",
            $user_id
        ));
    }
    
    /**
     * Marcar notificación como leída
     */
    public function mark_notification_read($notification_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'mv_notifications';
        
        return $wpdb->update(
            $table,
            array('is_read' => 1, 'read_at' => current_time('mysql')),
            array('id' => $notification_id, 'user_id' => $user_id)
        );
    }
    
    /**
     * Obtener icono según tipo
     */
    private function get_icon_for_type($type) {
        $icons = array(
            'success' => 'dashicons-yes-alt',
            'error' => 'dashicons-dismiss',
            'warning' => 'dashicons-warning',
            'info' => 'dashicons-info'
        );
        
        return isset($icons[$type]) ? $icons[$type] : 'dashicons-info';
    }
    
    /**
     * Enviar notificación por email
     */
    private function send_notification_email($user_id, $title, $message, $link = '') {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $subject = sprintf('[%s] %s', get_bloginfo('name'), $title);
        
        $body = $message;
        if ($link) {
            $body .= "\n\n" . __('Para más información, visite:', 'modulo-ventas') . "\n" . $link;
        }
        
        return wp_mail($user->user_email, $subject, $body);
    }
    
    /**
     * Sistema de alertas para eventos importantes
     */
    
    /**
     * Alertar sobre cotización próxima a expirar
     */
    public function alert_cotizacion_expiring($cotizacion) {
        $dias_restantes = ceil((strtotime($cotizacion->fecha_expiracion) - current_time('timestamp')) / 86400);
        
        $message = sprintf(
            __('La cotización %s expirará en %d días', 'modulo-ventas'),
            $cotizacion->folio,
            $dias_restantes
        );
        
        $this->create_notification(
            __('Cotización próxima a expirar', 'modulo-ventas'),
            $message,
            'warning',
            array(
                'user_id' => $cotizacion->vendedor_id,
                'link' => mv_admin_url('ver-cotizacion', array('id' => $cotizacion->id)),
                'link_text' => __('Ver cotización', 'modulo-ventas'),
                'email' => true
            )
        );
    }
    
    /**
     * Alertar sobre nueva cotización creada
     */
    public function alert_nueva_cotizacion($cotizacion_id) {
        $cotizacion = mv_db()->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return;
        }
        
        // Notificar a administradores
        $admins = get_users(array('role' => 'administrator'));
        
        foreach ($admins as $admin) {
            $this->create_notification(
                __('Nueva cotización creada', 'modulo-ventas'),
                sprintf(
                    __('Se ha creado la cotización %s por un valor de %s', 'modulo-ventas'),
                    $cotizacion->folio,
                    mv_formato_precio($cotizacion->total)
                ),
                'info',
                array(
                    'user_id' => $admin->ID,
                    'link' => mv_admin_url('ver-cotizacion', array('id' => $cotizacion_id)),
                    'email' => get_option('modulo_ventas_notificar_nueva_cotizacion', 'yes') === 'yes'
                )
            );
        }
    }
    
    /**
     * Alertar sobre cotización convertida
     */
    public function alert_cotizacion_convertida($cotizacion_id, $pedido_id) {
        $cotizacion = mv_db()->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return;
        }
        
        $message = sprintf(
            __('La cotización %s ha sido convertida en el pedido #%d', 'modulo-ventas'),
            $cotizacion->folio,
            $pedido_id
        );
        
        // Notificar al vendedor
        if ($cotizacion->vendedor_id) {
            $this->create_notification(
                __('Cotización convertida en venta', 'modulo-ventas'),
                $message,
                'success',
                array(
                    'user_id' => $cotizacion->vendedor_id,
                    'link' => admin_url('post.php?post=' . $pedido_id . '&action=edit'),
                    'link_text' => __('Ver pedido', 'modulo-ventas'),
                    'email' => true
                )
            );
        }
    }
    
    /**
     * Sistema de toasts (notificaciones flotantes)
     */
    
    /**
     * Agregar toast
     */
    public function add_toast($message, $type = 'info', $duration = 3000) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            mvShowToast('<?php echo esc_js($message); ?>', '<?php echo esc_js($type); ?>', <?php echo intval($duration); ?>);
        });
        </script>
        <?php
    }
    
    /**
     * Incluir sistema de toasts
     */
    public function enqueue_toast_system() {
        ?>
        <div id="mv-toast-container"></div>
        
        <style type="text/css">
        #mv-toast-container {
            position: fixed;
            top: 50px;
            right: 20px;
            z-index: 9999;
        }
        
        .mv-toast {
            background: #fff;
            border-left: 4px solid #00a0d2;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            margin-bottom: 10px;
            min-width: 300px;
            padding: 15px;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }
        
        .mv-toast.success { border-left-color: #46b450; }
        .mv-toast.error { border-left-color: #dc3232; }
        .mv-toast.warning { border-left-color: #ffb900; }
        .mv-toast.info { border-left-color: #00a0d2; }
        
        .mv-toast-close {
            position: absolute;
            top: 5px;
            right: 10px;
            cursor: pointer;
            font-size: 20px;
            line-height: 20px;
            color: #666;
        }
        
        .mv-toast-close:hover {
            color: #000;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        </style>
        
        <script type="text/javascript">
        function mvShowToast(message, type, duration) {
            type = type || 'info';
            duration = duration || 3000;
            
            var toastId = 'toast_' + Date.now();
            var toastHtml = '<div id="' + toastId + '" class="mv-toast ' + type + '">' +
                           '<span class="mv-toast-close">&times;</span>' +
                           '<div class="mv-toast-message">' + message + '</div>' +
                           '</div>';
            
            jQuery('#mv-toast-container').append(toastHtml);
            
            var $toast = jQuery('#' + toastId);
            
            // Click para cerrar
            $toast.find('.mv-toast-close').on('click', function() {
                mvRemoveToast(toastId);
            });
            
            // Auto cerrar
            if (duration > 0) {
                setTimeout(function() {
                    mvRemoveToast(toastId);
                }, duration);
            }
        }
        
        function mvRemoveToast(toastId) {
            var $toast = jQuery('#' + toastId);
            $toast.css('animation', 'slideOut 0.3s ease-in');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }
        </script>
        <?php
    }
    
    /**
     * Sistema de confirmación
     */
    
    /**
     * Crear diálogo de confirmación
     */
    public function confirm_dialog($id, $title, $message, $confirm_text = null, $cancel_text = null) {
        if (!$confirm_text) {
            $confirm_text = __('Confirmar', 'modulo-ventas');
        }
        if (!$cancel_text) {
            $cancel_text = __('Cancelar', 'modulo-ventas');
        }
        
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="mv-confirm-dialog" style="display:none;">
            <p><?php echo wp_kses_post($message); ?></p>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            window.mvConfirm<?php echo esc_js(str_replace('-', '', $id)); ?> = function(callback) {
                $('#<?php echo esc_js($id); ?>').dialog({
                    title: '<?php echo esc_js($title); ?>',
                    dialogClass: 'wp-dialog',
                    autoOpen: true,
                    draggable: false,
                    width: 'auto',
                    modal: true,
                    resizable: false,
                    closeOnEscape: true,
                    position: {
                        my: "center",
                        at: "center",
                        of: window
                    },
                    buttons: {
                        "<?php echo esc_js($confirm_text); ?>": function() {
                            $(this).dialog('close');
                            if (typeof callback === 'function') {
                                callback(true);
                            }
                        },
                        "<?php echo esc_js($cancel_text); ?>": function() {
                            $(this).dialog('close');
                            if (typeof callback === 'function') {
                                callback(false);
                            }
                        }
                    }
                });
            };
        });
        </script>
        <?php
    }
    
    /**
     * Sistema de progreso
     */
    
    /**
     * Mostrar barra de progreso
     */
    public function progress_bar($id, $progress = 0, $text = '', $animated = true) {
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="mv-progress-wrapper">
            <?php if ($text) : ?>
                <div class="mv-progress-text"><?php echo esc_html($text); ?></div>
            <?php endif; ?>
            <div class="mv-progress-bar">
                <div class="mv-progress-bar-fill <?php echo $animated ? 'animated' : ''; ?>" 
                    style="width: <?php echo intval($progress); ?>%;">
                    <span class="mv-progress-percent"><?php echo intval($progress); ?>%</span>
                </div>
            </div>
        </div>
        
        <style type="text/css">
        .mv-progress-wrapper {
            margin: 20px 0;
        }
        
        .mv-progress-text {
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .mv-progress-bar {
            background: #f0f0f1;
            border-radius: 3px;
            height: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .mv-progress-bar-fill {
            background: #2271b1;
            height: 100%;
            position: relative;
            transition: width 0.3s ease;
        }
        
        .mv-progress-bar-fill.animated {
            animation: progress-stripes 1s linear infinite;
            background-image: linear-gradient(
                45deg,
                rgba(255,255,255,.15) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255,255,255,.15) 50%,
                rgba(255,255,255,.15) 75%,
                transparent 75%,
                transparent
            );
            background-size: 1rem 1rem;
        }
        
        .mv-progress-percent {
            color: #fff;
            font-size: 12px;
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        @keyframes progress-stripes {
            0% { background-position: 1rem 0; }
            100% { background-position: 0 0; }
        }
        </style>
        
        <script type="text/javascript">
        function mvUpdateProgress(id, progress, text) {
            var $wrapper = jQuery('#' + id);
            var $fill = $wrapper.find('.mv-progress-bar-fill');
            var $percent = $wrapper.find('.mv-progress-percent');
            var $text = $wrapper.find('.mv-progress-text');
            
            $fill.css('width', progress + '%');
            $percent.text(progress + '%');
            
            if (text && $text.length) {
                $text.text(text);
            }
        }
        </script>
        <?php
    }
    
    /**
     * Sistema de tips/tooltips
     */
    
    /**
     * Agregar tooltip
     */
    public function tooltip($content, $text, $position = 'top') {
        static $tooltip_init = false;
        
        if (!$tooltip_init) {
            $this->init_tooltip_system();
            $tooltip_init = true;
        }
        
        return sprintf(
            '<span class="mv-tooltip" data-tip="%s" data-position="%s">%s</span>',
            esc_attr($text),
            esc_attr($position),
            $content
        );
    }
    
    /**
     * Inicializar sistema de tooltips
     */
    private function init_tooltip_system() {
        ?>
        <style type="text/css">
        .mv-tooltip {
            position: relative;
            cursor: help;
            border-bottom: 1px dashed #666;
        }
        
        .mv-tooltip:before,
        .mv-tooltip:after {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .mv-tooltip:before {
            content: attr(data-tip);
            background: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 3px;
            white-space: nowrap;
            font-size: 12px;
        }
        
        .mv-tooltip:after {
            content: '';
            border: 5px solid transparent;
        }
        
        /* Posición top */
        .mv-tooltip[data-position="top"]:before {
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .mv-tooltip[data-position="top"]:after {
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%);
            border-top-color: #333;
        }
        
        /* Posición bottom */
        .mv-tooltip[data-position="bottom"]:before {
            top: 125%;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .mv-tooltip[data-position="bottom"]:after {
            top: 115%;
            left: 50%;
            transform: translateX(-50%);
            border-bottom-color: #333;
        }
        
        /* Posición left */
        .mv-tooltip[data-position="left"]:before {
            right: 125%;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .mv-tooltip[data-position="left"]:after {
            right: 115%;
            top: 50%;
            transform: translateY(-50%);
            border-left-color: #333;
        }
        
        /* Posición right */
        .mv-tooltip[data-position="right"]:before {
            left: 125%;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .mv-tooltip[data-position="right"]:after {
            left: 115%;
            top: 50%;
            transform: translateY(-50%);
            border-right-color: #333;
        }
        
        .mv-tooltip:hover:before,
        .mv-tooltip:hover:after {
            opacity: 1;
        }
        </style>
        <?php
    }
    
    /**
     * Crear tabla de notificaciones si no existe
     */
    public static function create_notifications_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mv_notifications';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            type varchar(20) DEFAULT 'info',
            link varchar(255) DEFAULT '',
            icon varchar(50) DEFAULT '',
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}