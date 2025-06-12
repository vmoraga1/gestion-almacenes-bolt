<?php
if (!defined('ABSPATH')) {
    exit;
}

ventas_check_permissions();

$logger = Ventas_Logger::get_instance();
$current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : current_time('Y-m-d');
$available_dates = $logger->get_available_dates();
$log_content = $logger->get_log_content($current_date);
$log_types = $logger->get_log_types();
?>

<div class="wrap">
    <h1>Registros del Sistema</h1>

    <div class="log-viewer-controls">
        <form method="get" id="log-filters">
            <input type="hidden" name="page" value="ventas-logs">
            
            <div class="filters-group">
                <select name="date" id="log-date">
                    <?php foreach ($available_dates as $date): ?>
                        <option value="<?php echo esc_attr($date); ?>" 
                                <?php selected($date, $current_date); ?>>
                            <?php echo esc_html($date); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="type" id="log-type">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($log_types as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>">
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" id="log-search" placeholder="Buscar en logs...">
                
                <button type="button" class="button" id="apply-filters">Aplicar Filtros</button>
            </div>
        </form>

        <div class="log-actions">
            <button type="button" class="button" id="refresh-logs">Actualizar</button>
            <button type="button" class="button" id="clear-logs">Limpiar Logs</button>
        </div>
    </div>

    <div class="log-viewer">
        <pre id="log-content"><?php echo esc_html($log_content); ?></pre>
    </div>
</div>

<style>
.filters-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.log-viewer-controls {
    background: #fff;
    padding: 15px;
    border: 1px solid #ccd0d4;
    margin: 20px 0;
}

#log-search {
    min-width: 200px;
}
</style>

<script>
jQuery(document).ready(function($) {
    function refreshLogs() {
        const data = {
            action: 'refresh_logs',
            nonce: ventasAjax.nonce,
            date: $('#log-date').val(),
            type: $('#log-type').val(),
            search: $('#log-search').val()
        };

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#log-content').html(response.data.content);
            }
        });
    }

    $('#apply-filters, #refresh-logs').on('click', refreshLogs);
    
    $('#log-search').on('keyup', _.debounce(refreshLogs, 500));

    $('#clear-logs').on('click', function() {
        if (!confirm('¿Estás seguro de que deseas limpiar los logs?')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_logs',
                nonce: ventasAjax.nonce,
                date: '<?php echo esc_js($current_date); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#log-content').html('');
                    location.reload();
                }
            }
        });
    });
});
</script>