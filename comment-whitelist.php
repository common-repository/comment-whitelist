<?php
/*
Plugin Name: Comment Whitelist
Plugin URI: http://taller.pequelia.es/plugins/comment-whitelist/
Description: Admin list of whitelist commenters
Version: 0.9.1
Author: Alejandro Carravedo (Blogestudio)
Author URI: http://blogestudio.com/
Update Server:  http://blogestudio.com/

0.9.0
	- Salida del Plugin
0.9.1
	- Arreglado README.TXT
*/


### Use WordPress 2.6 Constants
if ( !defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', ABSPATH.'wp-content');
if ( !defined('WP_CONTENT_URL') )
	define('WP_CONTENT_URL', get_option('siteurl').'/wp-content');

// Cogemos la ruta
$comment_whitelist__wp_dirname = basename(dirname(dirname(__FILE__))); // for "plugins" or "mu-plugins"
$comment_whitelist__pi_dirname = basename(dirname(__FILE__)); // plugin name

$comment_whitelist__path = WP_CONTENT_DIR.'/'.$comment_whitelist__wp_dirname.'/'.$comment_whitelist__pi_dirname;
$comment_whitelist__url = WP_CONTENT_URL.'/'.$comment_whitelist__wp_dirname.'/'.$comment_whitelist__pi_dirname;


// Cargamos el idioma cada vez que usemos el plugin
function comment_whitelist__init() {
	global $comment_whitelist__pi_dirname;
	
	// Load the location file
	load_plugin_textdomain('comment-whitelist', false, $comment_whitelist__pi_dirname.'/langs');
}
add_action('init', 'comment_whitelist__init');


/* Opciones del formulario de discusion */
function comment_whitelist__admin_init() {
	register_setting('discussion', 'whitelist_keys');
	add_settings_field('whitelist_keys', __('Comment Whitelist', 'comment-whitelist'), 'comment_whitelist__whitelist_keys', 'discussion');
}
add_action('admin_init', 'comment_whitelist__admin_init');


function comment_whitelist__whitelist_keys() {
	echo '<fieldset>';
		echo '<legend class="hidden">'.__('Comment Whitelist', 'comment-whitelist').'</legend>';
		echo '<p>';
			echo '<label for="whitelist_keys">';
				echo __('Is marked as "approved" any comment that includes any of the following e-mail accounts. Separate with newlines.', 'comment-whitelist');
			echo '</label>';
		echo '</p>';
		echo '<p>';
			echo '<textarea name="whitelist_keys" rows="10" cols="50" id="whitelist_keys" class="large-text code">';
				form_option('whitelist_keys');
			echo '</textarea>';
		echo '</p>';
	echo '</fieldset>';
}


/* Chequeo de Cuenta de Correo en Lista Blanca, si no eres el autor y si no eres un ADMIN */
function comment_whitelist__pre_comment_approved( $approved ) {
	global $wpdb, $current_user;
	
	// Si esta aprobado ...
	if ( $approved == '1' ) {
		
		// Pues sigue aprobado!! ;-))
		return $approved;
	}else{
		
		$mod_keys = trim( get_option('whitelist_keys') );
		
		// Si no tengo lista blanca ...
		if ( empty($mod_keys) ) {
			// Devuelvo lo que tenia!!
			return $approved;
		}else{
			
			// Continuo probando...
			$words = explode("\n", $mod_keys );
			// Si no tenemos elementos ...
			if ( !sizeof($words) ) {
				// Devolvemos lo que nos habia llegado de origen
				return $approved;
			}else{
				
				// Revisamos cada entrada de la lista blanca
				foreach ( (array) $words as $word ) {
					$word = trim($word);
					
					// Skip empty lines
					if ( empty($word) ) { continue; }
					
					// Do some escaping magic so that '#' chars in the
					// spam words don't break things:
					$word = preg_quote($word, '#');
					
					$pattern = "#$word#i";
					if ( preg_match($pattern, $_REQUEST['email']) ) {
						// Esta incluido en la lista blanca, nos da igual, le aprobamos.
						return '1';
					}
				}
				
				// NO Esta incluido en la lista blanca, le moderamos si no lo estaba, si es SPAM le dejamos.
				return ( $approved == '1' ) ? '0' : $approved;
				
			}
		}
	}
	
	die('fin');
	return $approved;
}
add_filter('pre_comment_approved', 'comment_whitelist__pre_comment_approved');


// Opciones de "Mandar/Sacar" de "Lista Blanca" en Comentarios
function comment_whitelist__comment_row_actions($actions, $comment) {
	
	$viewPut = 0;
	$viewQuit = 0;
	
	$mod_keys = trim( get_option('whitelist_keys') );
	if ( '' == $mod_keys ) {
		$viewPut = 1;
	}else{
		$viewPut = 1;
		
		$words = explode("\n", $mod_keys );
		foreach ( (array) $words as $word ) {
			$word = trim($word);
			
			// Skip empty lines
			if ( empty($word) ) { continue; }
			
			// Do some escaping magic so that '#' chars in the
			// spam words don't break things:
			$word = preg_quote($word, '#');
			
			$pattern = "#$word#i";
			if ( preg_match($pattern, $comment->comment_author_email) ) {
				$viewPut = 0;
				$viewQuit = 1;
			}
		}
	}
	
	if ( $viewPut || $viewQuit ) {
		
		if ( $viewPut ) {
			$whitelist_in_url = "comment.php?action=whitelistin&c=$comment->comment_ID";
			$actions['whitelist_in'] = "<a href='$whitelist_in_url' class='' title='" . __( 'Send this commenter to comment whitelist', 'comment-whitelist' ) . "'>" . __( 'Put in Whitelist', 'comment-whitelist' ) . '</a>';
		}
		
		if ( $viewQuit ) {
			$whitelist_out_url = "comment.php?action=whitelistoout&c=$comment->comment_ID";
			$actions['whitelist_out'] = "<a href='$whitelist_out_url' class='' title='" . __( 'Quit this commenter from comment whitelist', 'comment-whitelist' ) . "'>" . __('Quit from Whitelist', 'comment-whitelist') . '</a>';
		}
	}
	
	return $actions;
}
add_filter('comment_row_actions', 'comment_whitelist__comment_row_actions', 10, 2);


function comment_whitelist__actions__init() {
	
	if (
		( isset($_REQUEST['c']) && $_REQUEST['c'] > 0 )
		&& 
		( isset($_REQUEST['action']) && ( $_REQUEST['action'] == 'whitelistin' || $_REQUEST['action'] == 'whitelistoout' ) ) 
	) {
		
		$comment = get_comment( $_REQUEST['c']);
		
		$new_mod_keys = array();
		
		if ( $_REQUEST['action'] == 'whitelistin' )
			$new_mod_keys[] = $comment->comment_author_email;
		
		$mod_keys = trim( get_option('whitelist_keys') );
		
		if ( !empty($mod_keys) ) {
			
			$words = explode("\n", $mod_keys );
			foreach ( (array) $words as $word ) {
				$word = trim($word);
				$original_word = $word;
				
				// Skip empty lines
				if ( empty($word) ) { continue; }
				
				// Do some escaping magic so that '#' chars in the
				// spam words don't break things:
				$word = preg_quote($word, '#');
				
				$pattern = "#$word#i";
				
				if ( preg_match($pattern, $comment->comment_author_email) ) {
					// NADA
				}else{
					$new_mod_keys[] = $original_word;
				}
			}
		}
		
		if ( sizeof($new_mod_keys) ) {
			update_option( 'whitelist_keys', implode("\n", $new_mod_keys) );
		}else{
			update_option( 'whitelist_keys', '' );
		}
		
		$redirect_to = 'edit-comments.php';
		
		if ( isset($_REQUEST['apage']) )
			$redirect_to = add_query_arg( 'apage', absint($_REQUEST['apage']), $redirect_to );
		if ( !empty($_REQUEST['mode']) )
			$redirect_to = add_query_arg('mode', $_REQUEST['mode'], $redirect_to);
		if ( !empty($_REQUEST['comment_status']) )
			$redirect_to = add_query_arg('comment_status', $_REQUEST['comment_status'], $redirect_to);
		if ( !empty($_REQUEST['s']) )
			$redirect_to = add_query_arg('s', $_REQUEST['s'], $redirect_to);
		
		wp_redirect( $redirect_to );
		die();
	}
}
add_action('init', 'comment_whitelist__actions__init');
?>