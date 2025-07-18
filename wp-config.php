<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'bolt' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         't|PC:I!&mzPCP49^7fnsrgzjHhlCL&y1(K3:,w&(p[M7Z+nT=gN<31)I.d!&oYB$' );
define( 'SECURE_AUTH_KEY',  '^%*?|=A5{m=4MpB!US&&+`YcW_*xuP0CnWUia8{2=J4?EdYsa|ry nd[+Yc@<(C4' );
define( 'LOGGED_IN_KEY',    'f4~!L)CpvL<A@)o%~PUbV^L8PqC;2ZY9]PxFocIv94e{{SIn^Pz@8J4mjg3`0xNb' );
define( 'NONCE_KEY',        ':I>=tSl50&#+@]|UY(q*v.g|sVD#`fxK/U$Nxa.QA,s*G7(2pLIiPg|}T9Qu)th?' );
define( 'AUTH_SALT',        'w/6yl~v$!AbH}EyE5AJ[qt`mkAb]t>tJDCF*59v$U~t{tS0c#RFoB~og!tl6xz2^' );
define( 'SECURE_AUTH_SALT', 'LTWpZ_lg8{hDO[<=[K+gs/5sNR[nl(U0fa?W~n5* Kmgw$_k U=FVfjTP%BMVuK[' );
define( 'LOGGED_IN_SALT',   '!Vmxe;tFu9WUe;sz3?j?q!!@Q~Zhu@FS>g>k#_44~v^&N1H}deJU<3aIq&Wm]f3?' );
define( 'NONCE_SALT',       '@D+Sc/Y8S8p$8b|xQ~@eEqB#0-35)WpqBacA,qh#>,tlU3LmRXXPvjjBugaJg.k[' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
