<?php
if ( is_multisite() )
	add_action( 'network_admin_menu', 'git_theme_updater_add_menu' );
else
	add_action( 'admin_menu', 'git_theme_updater_add_menu' );
	
function git_theme_updater_add_menu() {
	add_menu_page('Git Theme Updater Config Page', 'ThemeUpdater', 8, 'git_theme_updater_config_page', 'git_theme_updater_config_page'); 
}

function git_theme_updater_config_page() {
	
	?>
	<style>
	.form-table {
		width: 90%;
		margin: 20px 0 30px;
		display: block;
		background: #fafafa;
		padding: 20px;
		border: 1px solid #eee;	
	}
	h2 {
		margin-bottom: 30px;
		border-bottom: 1px solid #000;
		padding-bottom: 15px;
	}
	</style>
	<div id="icon-themes" class="icon32"><br></div><h2><?php _e("Settings for Git Theme Updater","gitThemeUpdater"); ?></h2>
	<?php
	
	if ($_POST['save'] || $_GET['save']) {
		if ($_POST['theme'])
			git_theme_updater_save_options($_POST['theme']);
		elseif ($_GET['theme'])
			git_theme_updater_save_options($_GET['theme']);
	}
	
	$themes = wp_get_themes();
	
	foreach ($themes as $name => $theme) {
		
		$gitUri = "GTU_gituri_". $name;
		$gitUriID = "GTU_gituri_id_". $name;
		$gitUriSecret = "GTU_gituri_secret_". $name;
		$gitUriToken = "GTU_gituri_token_". $name;
		
		$gitUriValue = get_option($gitUri);
		$gitUriIDValue = get_option($gitUriID);
		$gitUriSecretValue = get_option($gitUriSecret);
		$gitUriTokenValue = get_option($gitUriToken);
		
		$t = wp_get_theme($name);
		
		if ($gitUriValue =="" || !$gitUriValue)
			$gitUriValue = $t->get('Github Theme URI');
		
		?><h3><?php echo $t; ?></h3>
		<form method="POST" action="admin.php?page=git_theme_updater_config_page">
		<input type="hidden" name="save" value="data" />
		<input type="hidden" name="theme" value="<?php echo $name; ?>" />
		<table class="form-table">
			<tbody>
				<tr valign="top">
				<th scope="row"><label for="<?php echo $gitUri; ?>"><?php _e("Github Theme URI","gitThemeUpdater"); ?></label></th>
				<td><input name="<?php echo $gitUri; ?>" type="text" id="<?php echo $gitUri; ?>" value="<?php echo $gitUriValue; ?>" class="regular-text"></td>
				</tr>
				
				<tr valign="top">
				<th scope="row"><label for="<?php echo $gitUriID; ?>"><?php _e("GitHub Client ID","gitThemeUpdater"); ?></label></th>
				<td><input name="<?php echo $gitUriID; ?>" type="text" id="<?php echo $gitUriID; ?>" value="<?php echo get_option($gitUriID); ?>" class="regular-text"></td>
				</tr>
				
				<tr valign="top">
				<th scope="row"><label for="<?php echo $gitUriSecret; ?>"><?php _e("GitHub Client Secret","gitThemeUpdater"); ?></label></th>
				<td><input name="<?php echo $gitUriSecret; ?>" type="text" id="<?php echo $gitUriSecret; ?>" value="<?php echo get_option($gitUriSecret); ?>" class="regular-text"></td>
				</tr>
				
				<?php if ($gitUriSecretValue && $gitUriIDValue) {
					if ($gitUriTokenValue) {
						?><tr valign="top">
						<td colspan="2"><strong><?php _e("Authorized!","gitThemeUpdater"); ?></strong> <a href="admin.php?page=git_theme_updater_config_page&save=forgottoken&theme=<?php echo $name; ?>"><?php _e("Click here to forgot authorization.","gitThemeUpdater"); ?></a></td>
						</tr>
						<?php
					} else {
						$return = get_admin_url();
						if ( is_multisite() )
							$return .= "network/";
						$return .= "admin.php?page=git_theme_updater_config_page&save=returntoken&theme=". $name;
						
						?><tr valign="top">
						<td colspan="2"><a href="https://github.com/login/oauth/authorize?client_id=<?php echo $gitUriIDValue; ?>&client_secret=<?php echo $gitUriSecretValue; ?>&scope=repo&redirect_uri=<?php echo urlencode($return); ?>"><?php _e("Click here to authorize.","gitThemeUpdater"); ?></a></td>
						</tr>
						<?php
					}
				} else { ?>
					<tr valign="top">
					<td colspan="2"><?php _e("If it's a private project, we'll need to authenticate this site with Github. You will need to register an developer application to get the info above. Make sure the Callback URL have this site domain.","gitThemeUpdater"); ?> <a href="https://github.com/settings/applications" target="_blank"><?php _e("Register here.","gitThemeUpdater"); ?></a></td>
					</tr>
				<?php } ?>
				<tr><td><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e("Save Theme Options","gitThemeUpdater"); ?>"></td></tr>
			</tbody>
		</table>	
		</form>
		<?php
	}
	
}

function git_theme_updater_save_options($name) {

	if ($_POST['save'] == "data") {
	
		$gitUri = "GTU_gituri_". $name;
		update_option($gitUri,$_POST[$gitUri]);
		
		$gitUriID = "GTU_gituri_id_". $name;
		update_option($gitUriID,$_POST[$gitUriID]);
		
		$gitUriSecret = "GTU_gituri_secret_". $name;
		update_option($gitUriSecret,$_POST[$gitUriSecret]);
		
		?>
		<div id="setting-error-settings_updated" class="updated settings-error"> 
			<p><strong><?php _e("Settings Saved!","gitThemeUpdater"); ?></strong></p>
		</div>
		<?php
		
	} elseif ($_GET['save'] == "forgottoken") {
	
		update_option("GTU_gituri_token_". $name,"");
		?>
		<div id="setting-error-settings_updated" class="updated settings-error"> 
			<p><strong><?php _e("Token erased!","gitThemeUpdater"); ?></strong></p>
		</div>
		<?php
		
	} elseif ($_GET['save'] == "returntoken") {
	
		if ($_GET['code']) {
			$url = "https://github.com/login/oauth/access_token?client_id=". get_option("GTU_gituri_id_".$name) ."&client_secret=". get_option("GTU_gituri_secret_".$name) ."&code=". $_GET['code'];
			$token = parse_str(file_get_contents($url), $data);
			if ($data['access_token']) {
				update_option("GTU_gituri_token_". $name,$data['access_token']);
				?>
				<div id="setting-error-settings_updated" class="updated settings-error"> 
					<p><strong><?php _e("Token saved!","gitThemeUpdater"); ?></strong></p>
				</div>
				<?php
			} else {
				?>
				<div id="setting-error-settings_updated" class="error settings-error"> 
					<p><strong><?php _e("Error while fetching the access token!","gitThemeUpdater"); ?></strong></p>
				</div>
				<?php
			}
		} else {
			?>
			<div id="setting-error-settings_updated" class="error settings-error"> 
				<p><strong><?php _e("Error while fetching the access code!","gitThemeUpdater"); ?></strong></p>
			</div>
			<?php
		}
		
	}
	
}
