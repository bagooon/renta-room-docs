<?php//Begin Really Simple Security key
define('RSSSL_KEY', 'dV4xBJBpJSgk9tgy5Z0HVTtX7Hh7IaTwAGyooCY9itcXkjlyaJmsyr2yswccOt4F');
//END Really Simple Security key

//Begin Really Simple SSL session cookie settings
@ini_set('session.cookie_httponly', true);
@ini_set('session.cookie_secure', true);
@ini_set('session.use_only_cookies', true);
//END Really Simple SSL cookie settings

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
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'mycxs_rentaroom' );

/** Database username */
define( 'DB_USER', 'mycxs_rentaroom' );

/** Database password */
define( 'DB_PASSWORD', 'BRep2EsWotH' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',         'nW5>-[;a%k]#)?9id6$5FVudJ_t&Nga<+]bXw#eWDB7 f9g6feP&^y*d@FD2.(AR' );
define( 'SECURE_AUTH_KEY',  'tC(sA%[/I@HK.;mra2i#xF0}:!rcRM_EG(]R,by V:^8_}11aB=n]*.^A5=h|q3p' );
define( 'LOGGED_IN_KEY',    ']Q(YBCnY^TPe(}XY@(+`l6g=/<qa6n0(c_hMy#|jIe@gIJ;bH(>)1Y!2~i52~8Ef' );
define( 'NONCE_KEY',        '}qVsZhZJ5ce?]KY)#a0SzB~{x#d!O$uQ8IJT=U|pD4$4<> Bnpc|oQ89bH?eQuu3' );
define( 'AUTH_SALT',        'f:!-`xxJp~,VB^S{5]A*+4w[JXODX%,5AP (gs5!KBY].^PupfK*Y<}yD@S3BB)5' );
define( 'SECURE_AUTH_SALT', '9hEBl?`F|ur=~Jp}W>/,}zQhRjGM[A,Pn]%5hc|F= ?g*(6D#M8= Gzu1~6&ZnP}' );
define( 'LOGGED_IN_SALT',   'P>)#|[C/d0>nIAEOu,3<VeUm(@1Sv.9z2-(^rij?:{_v2B+kuU>n4A]r V.P&ZG>' );
define( 'NONCE_SALT',       'ywSp&Jh68XD%s5v1pCd%,F[$ 2$!2adv@oI?F!#-R-3i>Ygz/USV?xa!u3k;x;L9' );

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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
