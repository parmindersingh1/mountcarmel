<?php
/*
Plugin Name: Password Content ShortCode 
Plugin URI: http://www.ttweb.ru/cspassword.html
Description: Password for the content of records WordPress
Version: 2.1
Author: ZetRider
Author URI: http://www.zetrider.ru
Author Email: ZetRider@bk.ru
*/
/*  Copyright 2011  zetrider  (email: zetrider@bk.ru)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

load_plugin_textdomain('cs-password', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)). '/lang/');

$WPCSP_PLUGIN_URL = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));

function cspasswordmenu(){
	add_options_page('CS Password', 'CS Password', 8, 'setting_cspassword', 'setting_cspassword');
} add_action('admin_menu', 'cspasswordmenu');

function setting_cspassword() {
global $WPCSP_PLUGIN_URL;
?>
<div class="wrap">
<h2><?php _e("Password Content Shortcode", "clone-spc"); ?></h2>

<a href="http://wordpress.org/extend/plugins/clone-spc/" target="_blank"><img src="<?php echo $WPCSP_PLUGIN_URL; ?>images/wpo.jpg"></a>
<a href="http://www.zetrider.ru/" target="_blank"><img src="<?php echo $WPCSP_PLUGIN_URL; ?>images/zwd.jpg"></a><br style="clear:both;">
<a href="http://www.ttweb.ru/" target="_blank"><img src="<?php echo $WPCSP_PLUGIN_URL; ?>images/stt.jpg"></a>
<a href="http://www.zetrider.ru/donate" target="_blank"><img src="<?php echo $WPCSP_PLUGIN_URL; ?>images/dwy.jpg"></a>
<br><br>
<b>ShortCode:</b> [cspasswordcode password=""][/cspasswordcode]<br>
<b>CSS Class:</b> .csp_form{}, .csp_input{}, .csp_submit{}
<hr>

<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>
<strong><?php _e("Message when an error entering the password:","cs-password"); ?></strong><br>
<input type="text" name="cspassword_error" size="60" value="<?php echo get_option('cspassword_error') ?>" /> <small>(<?php _e("Default:", "cs-password"); ?> <?php _e("Access Denied", "cs-password"); ?>)</small><br><br>
<strong><?php _e("The text before the input field:","cs-password"); ?></strong><br>
<input type="text" name="cspassword_text" size="60" value="<?php echo get_option('cspassword_text') ?>" /> <small>(<?php _e("Default:", "cs-password"); ?> <?php _e("Content with a password", "cs-password"); ?>)</small><br><br>
<strong><?php _e("The name of the input buttons:","cs-password"); ?></strong><br>
<input type="text" name="cspassword_submit" size="60" value="<?php echo get_option('cspassword_submit') ?>" /> <small>(<?php _e("Default:", "cs-password"); ?> <?php _e("Access", "cs-password"); ?>)</small><br><br>
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="cspassword_error, cspassword_submit, cspassword_text" />
<input type="submit" name="update" value="<?php _e("Save","cs-password"); ?>" class="button-primary">
</form>
</div>    
<?php 
}

function cspassword_shortcode($atts, $content = null) {
	extract(shortcode_atts(array('password' => ""), $atts));
	$cspassword = $password;
	if (get_option('cspassword_text') == '') { $cspassword_text = __("Content with a password", "cs-password"); } else { $cspassword_text = get_option('cspassword_text'); }
	if (get_option('cspassword_submit') == '') { $cspassword_submit = __("Access", "cs-password"); } else { $cspassword_submit = get_option('cspassword_submit'); }
	if (get_option('cspassword_error') == '') { $cspassword_error = __("Access Denied", "cs-password"); } else { $cspassword_error = get_option('cspassword_error'); } 
	$cspassword_form = '
	<form action="" method="post" class="csp_form"> 
	'.$cspassword_text.'<input type="text" size="20" name="csp_input"> <input type="submit" name="csp_submit" value="'.$cspassword_submit.'"> 
	</form>
	';
	
	if (isset($_POST['csbutton'])==true) {
		if ($_POST['cspasswordform'] == $cspassword && $cspassword != '') {
			return $content;
		}
		else {
			return "<strong>".$cspassword_error."</strong>".$cspassword_form;
		}
	}
	return $cspassword_form;

} add_shortcode('cspasswordcode', 'cspassword_shortcode');

function csp_submit(){
	global $WPCSP_PLUGIN_URL;
	echo '<a title="[cspasswordcode password=\'PASS\'] [/cspasswordcode]" id="csp_submit" style="cursor:pointer;"><img src="'.$WPCSP_PLUGIN_URL.'images/ico.png"></a>';
} add_action('media_buttons','csp_submit',11);

function csp_submit_js() {
	echo '<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery("#csp_submit").click(function() {
			send_to_editor(jQuery("#csp_submit").attr(\'title\'));
			return false;
		});
	});
	</script>';
} add_action('admin_head', 'csp_submit_js');
?>