<?php
/*
Plugin Name: Git Theme Updater
Plugin URI: https://github.com/brunocantuaria/gitthemeupdater/
Description: A theme updater for developers who use GitHub. Based on plugin Theme Updater from Douglas Beck (https://github.com/UCF/Theme-Updater). This version includes support to private projects and an interface to link themes to GitHub projects.
Author: Bruno Cantuaria
Author URI: http://cantuaria.net.br
Version: 1.0.5
*/

require_once('assets.php');
include_once('admin_page.php');

global $gitThemeUpdaterError;
$gitThemeUpdaterError = false;

add_action( 'extra_theme_headers', 'github_extra_theme_headers' );
function github_extra_theme_headers( $headers ) {
    $headers['Github Theme URI'] = 'Github Theme URI';
    return $headers;
}

function github_error_message() {
	global $gitThemeUpdaterError;

	if ($gitThemeUpdaterError === false)
		return;

    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo $gitThemeUpdaterError; ?></p>
    </div>
    <?php
}
add_action( 'admin_notices', 'github_error_message' );

add_filter('site_transient_update_themes', 'transient_update_themes_filter');
function transient_update_themes_filter($data){

	global $gitThemeUpdaterError;

	//Themes may block updates
	$update = true;
	$update = apply_filters( 'gtu_ignore_update', $update );

	if ($update === false)
		return;
	
	$installed_themes = wp_get_themes();
	foreach ( (array) $installed_themes as $name => $_theme ) {

		$name = str_replace( array('.', ' ', ','), '_', $name );
				
		$gitUriValue = get_option("GTU_gituri_". $name);
		if ($gitUriValue =="" || !$gitUriValue) {
			$t = wp_get_theme($name);
			$gitUriValue = $t->get('Github Theme URI');
		}
		
		if ($gitUriValue =="" || !$gitUriValue)
			continue;
			
		$theme = array(
			'Github Theme URI' => $gitUriValue,
			'Stylesheet'       => $_theme->stylesheet,
			'Version'          => $_theme->version
		);
		
		$theme_key = $theme['Stylesheet'];
		
		// Add Github Theme Updater to return $data and hook into admin
		remove_action( "after_theme_row_" . $theme['Stylesheet'], 'wp_theme_update_row');
		add_action( "after_theme_row_" . $theme['Stylesheet'], 'github_theme_update_row', 11, 2 );
		
		// Grab Github Tags
		$urlfix = str_replace( array('https://', 'http://', 'www.github.com/', 'github.com/'), '', $gitUriValue );
		$urldata = explode('/', $urlfix);

		if (count($urldata) < 2) {
			$gitThemeUpdaterError = 'Incorrect github project url.  Format should be (no trailing slash): <code style="background:#FFFBE4;">https://github.com/&lt;username&gt;/&lt;repo&gt;</code>';
			continue;
		}

		$matches = array(
			'username' => $urldata[0],
			'repo' => $urldata[1],
		);
		
		//Maybe stop using tag?
		//https://raw.githubusercontent.com/username/repo/master/style.css?token=token
		$url = sprintf('https://api.github.com/repos/%s/%s/tags', urlencode($matches['username']), urlencode($matches['repo']));
		
		//If have token
		$token = get_option("GTU_gituri_token_". $name);
		if ($token!="")
			$url .= "?access_token=$token";
			
		$response = get_transient(md5($url)); // Note: WP transients fail if key is long than 45 characters
		if(empty($response)){
			$raw_response = wp_remote_get($url, array('sslverify' => false, 'timeout' => 10));
			if ( is_wp_error( $raw_response ) ){
				$gitThemeUpdaterError = "Error response from " . $url;
				continue;
			}
			$response = json_decode($raw_response['body']);

			if(isset($response->message)){
				if(is_array($response->message)){
					$errors = '';
					foreach ( $response->message as $error) {
						$errors .= ' ' . $error;
					}
				} else {
					$errors = print_r($response->message, true);
				}
				$gitThemeUpdaterError = sprintf('While <a href="%s">fetching tags</a> api error</a>: <span class="error">%s</span>', $url, $errors);
				continue;
			}
			
			if(count($response) == 0){
				$gitThemeUpdaterError = "Github theme does not have any tags";
				continue;
			}
			
			//set cache, just 60 seconds
			set_transient(md5($url), $response, 30);
		}
		
		// Sort and get latest tag
		$tags = array_map(function($t){ return $t->name; }, $response);
		usort($tags, "version_compare");
		
		
		// check for rollback
		if(isset($_GET['rollback'])){
			$zipball = sprintf('https://api.github.com/repos/%s/%s/zipball/%s', urlencode($matches['username']), urlencode($matches['repo']), urlencode($_GET['rollback']));
			if ($token!="")
				$zipball .= "?access_token=$token";
			
			$data->response[$theme_key]['package'] = $zipball;
			continue;
		}
		
		// check and generate download link
		$newest_tag = array_pop($tags);
		if(version_compare($theme['Version'],  $newest_tag, '>=')){
			// up-to-date!
			$data->up_to_date[$theme_key]['rollback'] = $tags;
			continue;
		}
		
		// new update available, add to $data
		$zipball = sprintf('https://api.github.com/repos/%s/%s/zipball/%s', urlencode($matches['username']), urlencode($matches['repo']), $newest_tag);
		if ($token!="")
			$zipball .= "?access_token=$token";

		$update = array();
		$update['new_version'] = $newest_tag;
		$update['url']         = $gitUriValue;
		$update['package']     = $zipball;
		$data->response[$theme_key] = $update;
		
	}

	return $data;
}


add_filter('upgrader_source_selection', 'upgrader_source_selection_filter', 10, 3);
function upgrader_source_selection_filter($source, $remote_source=NULL, $upgrader=NULL){
	
	global $wp_filesystem;
	
	$type = get_class($upgrader);
	if ($type != "Theme_Upgrader") {
		_e("Github Theme URI: Isn't a theme installation. Skipping.","gitThemeUpdater"); echo '<BR />';
		return $source;
	}
	
	$name = $upgrader->skin->theme_info->get_stylesheet();
		
	$gitUriValue = get_option("GTU_gituri_". $name);
	if ($gitUriValue =="" || !$gitUriValue) {
		$t = wp_get_theme($name);
		$gitUriValue = $t->get('Github Theme URI');
	}
	
	if ($gitUriValue =="" || !$gitUriValue) {
		_e("Github Theme URI: Theme isn't on Github. Skipping.","gitThemeUpdater"); echo '<BR />';
		return $source;	
	}
	_e("Github Theme URI: Theme on Github. Processing...","gitThemeUpdater"); echo '<BR />';
		
	if(isset($source, $remote_source)){
		$corrected_source = $remote_source . '/'. $name .'/';
		if($wp_filesystem->move($source, $corrected_source, true)){
			_e("Github Theme URI: File renamed. Finishing...","gitThemeUpdater"); echo '<BR />';
			return $corrected_source;
		} else {
			return new WP_Error("rename_dir","Unable to rename downloaded theme.");
		}
	}
	
	return new WP_Error("rename_dir","Unknow Error x09.");
	
}

/*
   Function to address the issue that users in a standalone WordPress installation
   were receiving SSL errors and were unable to install themes.
   https://github.com/UCF/Theme-Updater/issues/3
*/
add_action('http_request_args', 'no_ssl_http_request_args', 10, 2);
function no_ssl_http_request_args($args, $url) {
	$args['sslverify'] = false;
	return $args;
}
