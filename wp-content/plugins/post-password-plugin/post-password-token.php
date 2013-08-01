<?php
/*
Plugin Name: Post Password Token
Plugin URI: http://top-frog.com/projects/post-password-token/
Description: Allow tokens to be supplied in the URL to negate the post_password requirement. Mimics the Guest Pass functionality on Flickr. <a href="options-general.php?page=post-password-token.php">Configure plugin options</a>
Version: 1.2.4
Author: shawnparker, gordonbrander
Author URI: http://top-frog.com
*/

// Copyright (c) 2009 Shawn Parker, Gordon Brander. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// *********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// *********************************************************************
	
	define('PPT_OPTION', 'ppt-token-options');
	define('PPT_COOKIE', 'wp-post-token_');
	define('PPT_PLUGIN_URL', ppt_plugin_path());
	define('PPT_VER', '1.2.4');

// Ugh

	/**
	 * Unfortunately this plugin is named 'post-password-token' but is
	 * in the svn repo as 'post-password-plugin' which breaks the urls
	 * 
	 * Try to be smart without having to rename this file.
	 * 
	 * @return string
	 */
	function ppt_plugin_path() {
		$self = basename(__FILE__);

		if (is_file(WP_PLUGIN_DIR.'/post-password-plugin/'.$self) ||
            is_file(WPMU_PLUGIN_DIR.'/post-password-plugin/'.$self)) {
			return plugins_url().'/post-password-plugin/';
		}
		
		return plugins_url().'/'.basename($self, '.php').'/';	
	}

// Upgrade
	
	function ppt_check_upgrade() {
		$options = get_option(PPT_OPTION);

        if (!is_array($options) && $options != false) {
			ppt_upgrade_options();
		}
	}
	
	add_action('admin_init', 'ppt_check_upgrade', 10);
	
// Hide protected posts except when directly called

	/**
	 * General filter to exclude all password protected posts via the where clause
	 *
	 * @param string $clause 
	 * @return string
	 */
	function ppt_exclude_protected_posts_filter($clause) {
		global $wpdb;

		$clause .= ' AND '.$wpdb->posts.'.post_password = ""';

        return $clause;
	}
	
	/**
	 * toggle between the general filter and the WP (main query) specific filter
	 *
	 * @param stdClass $query_obj
	 * @return void
	 */
	function ppt_set_conditional_protected_posts_filter($query_obj) {
		remove_filter('posts_where_paged', 'ppt_exclude_protected_posts_filter', 10, 1);
		add_filter('posts_where_paged', 'ppt_conditional_exclude_protected_posts_filter');
	}
	
	/**
	 * WP specific filter that enables loading of password protected posts on the main
	 * query and on single pages (direct access of those posts) only
	 *
	 * @param string $clause 
	 * @return string
	 */
	function ppt_conditional_exclude_protected_posts_filter($clause) {
		if (!is_singular()) {
			$clause = ppt_exclude_protected_posts_filter($clause);
		}

		add_filter('posts_where_paged', 'ppt_exclude_protected_posts_filter');
		remove_filter('posts_where_paged', 'ppt_conditional_exclude_protected_posts_filter', 10, 1);
		
		return $clause;
	}

	/**
	 * Init the post exclusion filter based on site option
	 *
	 * @return void
	 */
	function ppt_set_protected_post_exclusion() {
		$options = get_option(PPT_OPTION);

		if ($options['hide_protected'] == 1 && !is_admin()) {
			add_action('parse_request','ppt_set_conditional_protected_posts_filter');
			add_filter('posts_where_paged','ppt_exclude_protected_posts_filter');
			add_filter('wp_list_pages_excludes', 'ppt_wp_list_pages_excludes');
		}
	}

	add_action('plugins_loaded', 'ppt_set_protected_post_exclusion');

	/**
	 * Ho-lee-crap!
	 * Get a list of protected posts and append it to a passed in list of 
	 * excluded posts. They sure don't make it easy...
	 *
	 * @param array $excludes 
	 * @return array
	 */
	function ppt_wp_list_pages_excludes($excludes) {
		if ($protected_posts = wp_cache_get('ppt_wp_list_pages_excludes')) {
			return array_unique(array_merge($excludes, $protected_posts));
		}
		
		remove_filter('posts_where_paged','ppt_exclude_protected_posts_filter');
		add_filter('posts_where_paged', 'ppt_only_protected_posts_filter');

        $query = new WP_Query(array(
			'post_type' => 'page',
			'post_status' => 'publish'
		));

        add_filter('posts_where_paged','ppt_exclude_protected_posts_filter');
		remove_filter('posts_where_paged', 'ppt_only_protected_posts_filter');
		
		$protected_posts = array();

		if (!empty($query->posts)) {
			foreach ($query->posts as $post) {
				array_push($protected_posts, $post->ID);
			}
		}

		wp_cache_set('ppt_wp_list_pages_excludes', $protected_posts);
		return array_unique(array_merge($excludes, $protected_posts));
	}
	
	/**
	 * Add to the where clause and only pull protected posts
	 *
	 * @param string $clause 
	 * @return string
	 */
	function ppt_only_protected_posts_filter($clause) {
		global $wpdb;

		$clause .= ' AND '.$wpdb->posts.'.post_password <> ""';

        return $clause;
	}
	
// Front end Auth

	/**
	 * check the post_password token at template redirect
	 *
	 * @return void
	 */
	function ppt_template_redirect() {
		global $wp_query;

        if ((is_single() || is_page()) && isset($wp_query->post->post_password) && isset($_GET['ppt'])) {
			if (ppt_cookie_match($wp_query->post,$_GET['ppt'])) {
				ppt_set_cookie($wp_query->post->post_password);
			}
		}
	}

	add_action('template_redirect', 'ppt_template_redirect');

	/**
	 * Attempt a token match
	 *
	 * @param object $post 
	 * @param string $token 
	 * @return bool
	 */
	function ppt_cookie_match($post,$token) {		
		if(!isset($_COOKIE[PPT_COOKIE.COOKIEHASH]) || $_COOKIE[PPT_COOKIE.COOKIEHASH] != ppt_make_token($post)) {
			return ppt_make_token($post) == $token;
		}

		return false;
	}

	/**
	 * Set the cookie 
	 * Functionality duplicated from WordPress' post password submit in wp-pass.php
	 *
	 * @param string $post_password 
	 * @return void
	 */
	function ppt_set_cookie($post_password) {
		global $token, $wp_version;
		
		setcookie(PPT_COOKIE.COOKIEHASH, $token, null, COOKIEPATH);
		$redirect_uri = 'http' . (is_ssl() ? 's' : '') . '://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		
		if (version_compare($wp_version, '3.3', '<=')) {
			// legacy cookie
			setcookie('wp-postpass_' . COOKIEHASH, $post_password, time() + 864000, COOKIEPATH);
			wp_redirect($redirect_uri);
		}
		else {
			// hashed cookie
			global $wp_hasher;

            if (empty($wp_hasher)) {
				require_once( ABSPATH . 'wp-includes/class-phpass.php' );
				// By default, use the portable hash from phpass
				$wp_hasher = new PasswordHash(8, true);
			}

			setcookie('wp-postpass_' . COOKIEHASH, $wp_hasher->HashPassword(stripslashes($post_password)), time() + 864000, COOKIEPATH);
			wp_safe_redirect($redirect_uri);
		}
		exit;
	}

// Admin

	/**
	 * Add the meta box to the post/page edit meta boxes
	 *
	 * @return void
	 */
	function ppt_add_meta_box() {
		$options = get_option(PPT_OPTION);
		
		if (!is_array($options)) {
			ppt_upgrade_options();
			$options = get_option('ppt_options');
		}

		if (!empty($options['enable'])) {
			$post_types = get_post_types();

            foreach ($options['enable'] as $type) {
				if (in_array($type, $post_types)) {
					add_meta_box('ppt-token', __('Post Password Token'), 'ppt_meta_box', $type, 'normal', 'high');
				}
			}
		}
	}

	add_action('admin_head', 'ppt_add_meta_box');

	/**
	 * Output informational meta box
	 *
	 * @return void
	 */
	function ppt_meta_box() {
		global $post;
		
		// if no password set message and exit quickly
		if (empty($post->post_password)) { 
			echo '<p>'.__('There are no password token URLs for this post. Set a post password to enable the password token.').'</p>';
			return;
		}
		
		// don't show tokenized url for drafts
		if ($post->post_status != 'publish' && !empty($post->post_password)) {
			echo '<p>'.__('Tokens are only provided for published posts. Check back here when the post is published to get the token.').'</p>';
			return;
		}

		// build link
		$url = ppt_make_permalink($post);

		$html = '
			<dl>
				<dt><p class="ppt-blurb">'.__('Copy and share this secret <em>Password Token URL</em> to allow readers to see the content of this post').'. <a href="options-general.php?page=post-password-token">'.__('Learn more').' &raquo;</a></p></dt>
				<dd>
				    <a href="'.$url.'">'.$url.'</a>
				';

		if (get_option('permalink_structure') != '') {
            $shortUrl = ppt_make_permalink($post, true);

			$html .= '<br />
			        ' . __('or') . '<br />
				    <a href=="'.$shortUrl.'">'.$shortUrl.'</a>
				';
		}
		
		$html .= '</dd>
			</dl>
			';

		echo $html;
	}
	
// Admin Page

	/**
	 * Admin init, preppity prep-prep
	 *
	 * @return void
	 */
	function ppt_admin_init() {
		if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {		
			if (isset($_POST['ppt-save-salt'])) {
				check_admin_referer('ppt_update_salt');
				ppt_admin_save_salt();
			}
			elseif (isset($_POST['ppt-save-options'])) {
				check_admin_referer('ppt_update_settings');
				ppt_admin_save_options();
			}
		}
	}

	add_action('admin_init', 'ppt_admin_init', 10);

	/**
	 * Customize the security check failure message to something better than the
	 * default "are you sure..." message that WordPress will default to here.
	 *
	 * @return string
	 */
	function ppt_nonce_failure() {
		return __('Security Check Failed.');
	}

	add_filter('explain_nonce_ppt_update_salt', 'ppt_nonce_failure');
	add_filter('explain_nonce_ppt_update_settings', 'ppt_nonce_failure');

	/**
	 * Add the admin menu item
	 *
	 * @return void
	 */
	function ppt_admin_menu_item(){
		add_submenu_page('options-general.php', __('Post Password Token'), __('Post Password Token'), 'manage_options', basename(__FILE__), 'ppt_admin_page');
	}

	add_action('admin_menu', 'ppt_admin_menu_item');
	
	/**
	 * Save plugin options
	 *
	 * @return void
	 */
	function ppt_admin_save_options() {
		$options = get_option(PPT_OPTION);
			
		if (isset($_POST['ppt_hide_protected'])) {
			$options['hide_protected'] = intval($_POST['ppt_hide_protected']);
		}
		else {
			$options['hide_protected'] = 0;
		}
		
		if (!empty($_POST['ppt_enable'])) {
			$enable = array();
			$post_types = get_post_types();

            foreach($_POST['ppt_enable'] as $type => $active) {
				if (in_array($type, $post_types)) {
					$enable[] = $type;
				}
			}

			$options['enable'] = $enable;
		}
		
		// yes, update option - this always gets pulled
		update_option(PPT_OPTION, $options);
		wp_redirect('http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
		exit;
	}
	
	/**
	 * Init options for upgrade/install
	 *
	 * @return void
	 */
	function ppt_init_options() {
		$options = array(
			'enable' => array('post','page')
		);

		update_option('ppt_options', $options);
	}
	
	/**
	 * Save the salt
	 *
     * @param string|null $salt
	 * @return void
	 */
	function ppt_admin_save_salt($salt=null) {
		$options = get_option(PPT_OPTION);

        $redirect = false;

		if ($salt == null && isset($_POST['ppt_salt'])) {
			$salt = strval($_POST['ppt_salt']);
			$redirect=true;
			if(!get_magic_quotes_gpc()) {
				$options['salt'] = stripslashes($salt);
			}
		}
		else {
			$options['salt'] = $salt;
		}

		update_option(PPT_OPTION, $options);

		if($redirect) {
			wp_redirect('http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
			exit;
		}
	}
	
	/**
	 * Create a default salt
	 *
	 * @return string
	 */
	function ppt_create_salt() {
		$salt = substr(crypt(md5(time())), 0, 32);
		return $salt;
	}
	
	/**
	 * Output admin scripts and styles
	 *
	 * @return void
	 */
	function ppt_admin_enqueue_scripts() {
		if (isset($_GET['page']) && $_GET['page'] == basename(__FILE__)) {
			wp_enqueue_script('ppt-admin', PPT_PLUGIN_URL.'js/ppt-admin.js', array('jquery'), PPT_VER);
			wp_enqueue_style('ppt-admin', PPT_PLUGIN_URL.'css/ppt-admin.css', array(), PPT_VER, 'screen');
		}
	}

    add_action('admin_enqueue_scripts', 'ppt_admin_enqueue_scripts');
	
	/**
	 * Output the admin page
	 *
	 * @return void
	 */
	function ppt_admin_page() {
		global $wp_version;
		
		$options = get_option(PPT_OPTION);

		if ($options == false) {
			ppt_install();
			$options = get_option(PPT_OPTION);
		}

		// UI
		echo '
			<div id="ppt-wrap" class="wrap">
				';

		if (function_exists('screen_icon')) { 
			screen_icon(); 
		}

        echo '
				<h2>'.__('Post Password Token').'</h2>
				<p>Issue secret Password token urls that allow readers to access password-protected posts without having to type in a password. This is similar to Flickr&rsquo;s "Guest Pass" functionality.</p>
				<p>After password protecting a page or post the url with token will be displayed in a meta-box below the post-content area on the post/page edit screen.</p>
				<p>Accessing a password-protected post by its url will still show the standard password dialog, but if a reader accesses a password-protected post by its <strong>secret Password Token url</strong>, they will be automatically authenticated and be able to see the full content. Accessing the post by its secret url will also set an authentication cookie for the user that lasts for 10 days.</p>
				
				<hr class="ppt-hr" />
				
				<h3>Plugin Options</h3>
				<form method="post" id="ppt-options-form" name="ppt-options-form">
					<div class="ppt-form-box ppt-rounded">
						<h3>Protected Post Visibility</h3>
						<p>Protected posts can be hidden from general view. Protected posts will be hidden from everyone everywhere posts are shown and only displayed when directly accessed via the permalink.</p>
						<p class="ppt-inset">
							<input type="checkbox" name="ppt_hide_protected" id="ppt_hide_protected" value="1" '.($options['hide_protected'] ? 'checked="checked" ' : '').'/>
							<label for="ppt_hide_protected">Hide Protected Posts</label>
						</p>
						<h3>Post Type Support</h3>
						<p>This feature can be enabled or disabled for Pages and Posts. As of WordPress 3.0 custom post types are also supported.</p>
						<div class="ppt-post-type-options ppt-inset">
							<p>
								<input type="checkbox" name="ppt_enable[post]" id="ppt_enable_post" value="1" '.(in_array('post', $options['enable']) ? 'checked="checked" ' : '').'/>
								<label for="ppt_enable_post">Posts</label>
							</p>
							<p>
								<input type="checkbox" name="ppt_enable[page]" id="ppt_enable_page" value="1" '.(in_array('page', $options['enable']) ? 'checked="checked" ' : '').'/>
								<label for="ppt_enable_page">Pages</label>
							</p>
						';

		// sniff for custom post types and allow config if they are used
		if (version_compare($wp_version, '3.0', '>=')) {
			foreach (get_post_types() as $type) {
				$o = get_post_type_object($type);
				if ($o->_builtin != true) {
					echo '
							<p>
								<input type="checkbox" name="ppt_enable['.$o->name.']" id="ppt_enable_'.$o->name.'" value="1" '.(in_array($o->name, $options['enable']) ? 'checked="checked" ' : '').'/>
								<label for="ppt_enable_'.$o->name.'">'.$o->label.'</label>
							</p>
					';
				}
			}
		}

        echo '
						</div>
						<p>
							'.wp_nonce_field('ppt_update_settings', '_wpnonce', true, false).'
							<button class="button-primary" type="submit" id="ppt-save-options" name="ppt-save-options">Save Options</button>
						</p>
					</div>
				</form>
				
				<h3>Password Salt</h3>
				<form id="token-profile" name="token-profile" method="post" >
					<p class="help">A "salt" is a secret code key that is used when creating tokens, making them more secure. We recommend that you <strong>set this once and leave&nbsp;it</strong>.</p>
					<div class="ppt-danger-box ppt-rounded">
						<p>
							<label for="ppt_salt">Add some salt</label>
							<input type="text" size="50" id="ppt_salt" name="ppt_salt" class="lock" value="'.(!empty($options['salt']) ? htmlspecialchars($options['salt']) : '').'" />
						</p>
						<div id="token-profile-submit">
							<p><strong>Warning</strong>: changing the salt will modify all <em>Password Token URLs</em> site-wide: readers will no longer be able to use old <em>Password Token URLs</em> to view protected content.</p>
							<p>
								'.wp_nonce_field('ppt_update_salt').'
								<button type="submit" class="button-primary" id="ppt-save-salt" name="ppt-save-salt">Save salt, change tokens for all posts</button> <span class="hide-if-no-js">or <a class="lock-cancel" href="#ppt_salt">cancel</a></span>
							</p>
						</div><!--/token-profile-submit-->
					</div>
				</form>
				
				<hr class="ppt-hr" />
				
				<h3>How do I revoke the <em>Password Token URL</em> for a post?</h3>
				
				<p>Just change the password on the post. This will change the token, meaning the old link will no longer authenticate readers.</p>
				
				<p>If you need to revoke all <em>Password Token URL</em>s everywhere (the nuclear option), you can change the Password Salt above. This will create new tokens for all protected posts and invalidate all old tokens.</p>
				
				<h3>A note about Caching</h3>
				<p>If your site uses caching (like WP-Super-Cache) these pages will be cached. While this isn&rsquo;t really a security threat of galactic proportions (not any more so that what this plugin does already and, really, you wouldn&rsquo;t be using WordPress if national security was at stake) you can stop the pages from being cached if you prefer by adding <code>ppt=</code> to the caching exclusions list in WP-Super-Cache to keep the pages from being cached.</p>
				
				<hr class="ppt-hr" />
				
				<div id="donate">
					<h3>Please Donate</h3>
					<p>Donations buy donuts. Donuts help keep us motivated. When we&rsquo;re motivated we make and update plugins. Please help keep us motivated to make more useful plugins.</p>
					<div id="paypal">
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
							<input type="hidden" name="cmd" value="_s-xclick">
							<input type="hidden" name="hosted_button_id" value="6908957">
							<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
							<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
						</form>
					</div>
				</div>
				
				<hr class="ppt-hr" />
				
				<script type="text/javascript">
					var WPHC_AFF_ID = "14357";
					var WPHC_WP_VERSION = "'.$wp_version.'";
				</script>
				<script type="text/javascript" src="http://cloud.wphelpcenter.com/wp-admin/0001/deliver.js"></script>
				
			</div>
		';
	}
	
// Core

	/**
	 * Build our custom permalink with token
	 *
	 * @param object $post
     * @param bool $force_short
	 * @return string
	 */
	function ppt_make_permalink($post, $force_short = false) {
		$permalinks = get_option('permalink_structure');

		if ($force_short) {
			$url = wp_get_shortlink($post->ID).'&ppt='.ppt_make_token($post);
		} else {
			$url = get_permalink($post->ID).('' != $permalinks ? '?ppt=' : '&ppt=').ppt_make_token($post);
		}
		
		return $url;
	}

	/**
	 * Make an access hash
	 * Currently as simple as md5($post_name.$post_password)
	 *
	 * @param object $post 
	 * @return string
	 */
	function ppt_make_token($post) {
		global $token;

        if (is_null($token)) {
			$options = get_option(PPT_OPTION, '');

            if(empty($options['salt'])) {
				$salt = ppt_create_salt();
				ppt_admin_save_salt($salt);
			}

			$token = md5($options['salt'].$post->post_name.$post->post_password);
		}

        return $token;
	}
	
	/**
	 * Install plugin options
	 *
	 * @return void
	 */
	function ppt_install() {
		$opts = array(
			'salt' => ppt_create_salt(),
			'enable' => array('page', 'post'),
			'hide_protected' => 0
		);

		add_option(PPT_OPTION, $opts, '', 'no');
	}
	
	/**
	 * Upgrade the plugin options
	 * I didn't have the foresight to just do a god
	 * damn array when I created the plugin.
	 *
	 * @return void
	 */
	function ppt_upgrade_options() {
		$prev_opts = get_option(PPT_OPTION);

		// should never run in to this (<-- sign of a bad programmer)
		// but I'm just damn paranoid and don't want to nuke a user's private url set
		if (is_array($prev_opts) && isset($prev_opts['salt'])) {
			$salt = $prev_opts['salt'];
		}
		else {
			$salt = (empty($prev_opts) ? ppt_create_salt() : $prev_opts);
		}
		
		$options = array(
			'salt' => $salt,
			'enable' => array('page', 'post'),
			'hide_protected' => get_option('ppt_hide_protected', 0)
		);

		delete_option('ppt_hide_protected');
		delete_option(PPT_OPTION);
		add_option(PPT_OPTION, $options, '', 'no');
	}
