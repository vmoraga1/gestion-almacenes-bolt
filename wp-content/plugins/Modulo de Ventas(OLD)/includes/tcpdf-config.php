<?php
if (!defined('ABSPATH')) {
    exit;
}

// Configuración TCPDF
define('K_TCPDF_EXTERNAL_CONFIG', true);
define('K_PATH_MAIN', VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/');
define('K_PATH_URL', VENTAS_PLUGIN_URL . 'vendor/tecnickcom/tcpdf/');
define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
define('K_PATH_CACHE', WP_CONTENT_DIR . '/uploads/tcpdf-cache/');
define('K_PATH_IMAGES', K_PATH_MAIN . 'images/');
define('PDF_PAGE_FORMAT', 'A4');
define('PDF_PAGE_ORIENTATION', 'P');
define('PDF_CREATOR', get_bloginfo('name'));
define('PDF_AUTHOR', get_bloginfo('name'));
define('PDF_UNIT', 'mm');
define('PDF_MARGIN_HEADER', 5);
define('PDF_MARGIN_FOOTER', 10);
define('PDF_MARGIN_TOP', 27);
define('PDF_MARGIN_BOTTOM', 25);
define('PDF_MARGIN_LEFT', 15);
define('PDF_MARGIN_RIGHT', 15);
define('PDF_FONT_NAME_MAIN', 'helvetica');
define('PDF_FONT_SIZE_MAIN', 10);
define('PDF_FONT_NAME_DATA', 'helvetica');
define('PDF_FONT_SIZE_DATA', 8);
define('PDF_IMAGE_SCALE_RATIO', 1.25);
define('HEAD_MAGNIFICATION', 1.1);
define('K_CELL_HEIGHT_RATIO', 1.25);
define('K_TITLE_MAGNIFICATION', 1.3);