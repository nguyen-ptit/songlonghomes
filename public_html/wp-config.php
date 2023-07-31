<?php

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u616278348_5y62F' );

/** Database username */
define( 'DB_USER', 'u616278348_sac7o' );

/** Database password */
define( 'DB_PASSWORD', 'lRaA9j0aTl' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'EHPQJ0jj~dZS/.krD[&HTJ/iGcz??BN^$?s)`{bmAe>ePQtN.t;I}PstIhI_5zwe' );
define( 'SECURE_AUTH_KEY',   'H#73u[fl.!GNKg}|sdBM3vB0i&#$O,D!C*UI_2%1`cmx1BA-;TJ7tPzugcrE[z_B' );
define( 'LOGGED_IN_KEY',     '8fCE$=4iY}-t.I!kwJjAzhF@_SlgP^<Q!9;X!sVlUMY_?A8t$fB:r<r,a#BZP`Px' );
define( 'NONCE_KEY',         'fBZ%)Y).X8C=<ew(R?@9u;)R{5RptaVwW6FC1TkSYL-A6_+j6^;(^,F}54^sw{|-' );
define( 'AUTH_SALT',         'fv*!nJj+X@r m=RaBLqu:$r6GxS2ra9@+<`5c8OK/dE7^`y 08xCqh~:N+J!1wY7' );
define( 'SECURE_AUTH_SALT',  '_efR}`$uG^@]n@NP,7 eiaH[]nQzV[Z>(|#3keX{d2NX!QkN?][dTR?QCF:d:ZS^' );
define( 'LOGGED_IN_SALT',    '/&]7i#I<%u3:zE;6[=1;z0$4k/]cOc=DD:Yptof%Fqc0p:+K;Zo])j%_njExfQs7' );
define( 'NONCE_SALT',        '{s6Hs?O~&V9j1t>x1gaFtkaG8lA;twX{aZCzR/VhYDXD4Il`2F1*2a+}iMA*&nc@' );
define( 'WP_CACHE_KEY_SALT', '3b8=?SwHg0}uof{m&nYgbFst7Nh@GS)|;yDn&joRmNNK3tb6[1/B{O,qlz223dI%' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );


/* Add any custom values between this line and the "stop editing" line. */



define( 'FS_METHOD', 'direct' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
