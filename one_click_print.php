<?php
/*
Plugin Name: One Click Print
Text Domain: One Click Print
Plugin URI: https://www.printinlondon.co.uk/wordpress-plugin/
Description:[one-click-print] Shortcode that adds a printer icon, allowing the user to print the post, page or a specified HTML element in the post.
Version: 4.9.8
Author: Print In London
Author URI:https://www.printinlondon.co.uk

*/

/**
 * Class WP_Print_O_Matic
 * @package WP_Print_O_Matic
 * @category WordPress Plugins
 */
class WP_Print_O_Matic {
	/**
	 * Current version
	 * @var string
	 */
	var $version = '1.7.11';

	/**
	 * Used as prefix for options entry
	 * @var string
	 */
	var $domain = 'printomat';

	/**
	 * Name of the options
	 * @var string
	 */
	var $options_name = 'WP_Print_O_Matic_options';

	/**
	 * @var array
	 */
	var $options = array(
		'print_target' => 'article',
		'print_title' => '',
		'do_not_print' => '',
		'printicon' => 'true',
		'printstyle' => 'pom-default',
		'use_theme_css' => '',
		'custom_page_css' => '',
		'custom_css' => '',
		'html_top' => '',
		'html_bottom' => '',
		'script_check' => '',
		'fix_clone' => '',
		'pause_time' => '',
		'close_after_print' => '1',
	);

	var $add_print_script = array();


	/**
	 * PHP5 constructor
	 */
	function __construct() {
		// set option values
		$this->_set_options();

		// load text domain for translations
		load_plugin_textdomain( 'One-click-Print' );

		//load the script and style if not viewing the dashboard
		add_action('wp_enqueue_scripts', array( $this, 'printMaticInit' ) );

		// add actions
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_head', array( $this, 'printomat_style' ) );
		add_shortcode('one-click-print', array($this, 'shortcode'));
		add_action( 'wp_footer', array($this, 'printer_scripts') );

		// Add shortcode support for widgets
		add_filter('widget_text', 'do_shortcode');
	}

	//global javascript vars
	function printomat_style(){
		if( !empty( $this->options['custom_page_css'] ) ){
			echo "\n<style>\n";
			echo $this->options['custom_page_css'];
			echo "\n</style>\n";
		}
	}

	/**
	 * Callback init
	 */
	function printMaticInit() {
		//script
		wp_register_script('printomatic-js', plugins_url('/printomat.js', __FILE__), array('jquery'), '1.8.6');
		if( empty($this->options['script_check']) ){
			wp_enqueue_script('printomatic-js');
		}

		wp_register_script('jquery-clone-fix', plugins_url('/jquery.fix.clone.js', __FILE__), array('jquery'), '1.1');
		if( empty($this->options['script_check']) && !empty($this->options['fix_clone']) ){
			wp_enqueue_script('jquery-clone-fix');
		}

		//css
		wp_register_style( 'printomatic-css', plugins_url('/css/style.css', __FILE__) , array (), '1.2' );
		wp_enqueue_style( 'printomatic-css' );
	}

	/**
	 * Callback admin_menu
	 */
	function admin_menu() {
		if ( function_exists( 'add_options_page' ) AND current_user_can( 'manage_options' ) ) {
			// add options page
			$page = add_options_page('One-Click-Print', 'One-Click-Print', 'manage_options', 'One-Click-Print', array( $this, 'options_page' ));
		}
	}

	/**
	 * Callback admin_init
	 */
	function admin_init() {
		// register settings
		register_setting( $this->domain, $this->options_name );
	}

	/**
	 * Callback shortcode
	 */
	function shortcode($atts, $content = null){
		$ran = rand(1, 10000);
		$options = $this->options;

		if( !empty($this->options['script_check']) ){
			wp_enqueue_script('printomatic-js');
			if(!empty($this->options['fix_clone'])){
				wp_enqueue_script('jquery-clone-fix');
			}
		}

		extract( shortcode_atts(array(
			'id' => 'id'.$ran,
			'class' => '',
			'tag' => 'div',
			'alt' => '',
			'target' => $options['print_target'],
			'do_not_print' => $options['do_not_print'],
			'printicon' => $options['printicon'],
			'printstyle' => $options['printstyle'],
			'html_top' => $options['html_top'],
			'html_bottom' => $options['html_bottom'],
			'pause_before_print' => $options['pause_time'],
			'title' => $options['print_title'],
			'close_after_print' => $options['close_after_print'],

		), $atts));

		//if no printstyle, force-set to default
		if( empty( $printstyle ) ){
			$printstyle = 'pom-default';
		}

		//swap target placeholders out for the real deal
		$target = str_replace('%ID%', get_the_ID(), $target);

		if( empty( $options['use_theme_css'] ) ){
			$pom_site_css = '';
		}else{
			$pom_site_css = get_stylesheet_uri();
		}
		if( empty( $options['custom_css'] ) ){
			$pom_custom_css = '';
		}
		else{
			$pom_custom_css = $options['custom_css'];
		}
		if( empty( $html_top ) ){
			$pom_html_top = '';
		}
		else{
			//$pom_html_top = apply_filters('the_content', $html_top);
			//switching to do_shortcode to avoid conflicts with social sharing plugins
			$pom_html_top = do_shortcode($html_top);
		}
		if( empty( $html_bottom ) ){
			$pom_html_bottom = '';
		}
		else{
			//$pom_html_bottom = apply_filters('the_content', $html_bottom);
			//switching to do_shortcode to avoid conflicts with social sharing plugins
			$pom_html_bottom = do_shortcode($html_bottom);
		}
		if( empty( $do_not_print ) ){
			$pom_do_not_print = '';
		}
		else{
			$pom_do_not_print = $do_not_print;
		}

		$this->add_print_script[$id] = array(
			'pom_site_css' => $pom_site_css,
			'pom_custom_css' => $pom_custom_css,
			'pom_html_top' => $pom_html_top,
			'pom_html_bottom' => $pom_html_bottom,
			'pom_do_not_print' => $pom_do_not_print,
			'pom_pause_time' => $pause_before_print,
			'pom_close_after_print' => $close_after_print,
		);

		//return nothing if usign an external button
		if($printstyle == "external"){
			return;
		}

		if($printicon == "false"){
			$printicon = 0;
		}
		if( empty($alt) ){
			if( empty($title) ){
				$alt_tag = '';
			}
			else{
				$alt_tag = "alt='".strip_tags($title)."' title='".strip_tags($title)."'";
			}
		}
		else{
			$alt_tag = "alt='".$alt."' title='".$alt."'";
		}
		if($printicon && $title){
			$output = "<div class='printomatic ".$printstyle." ".$class."' id='".$id."' ".$alt_tag." data-print_target='".$target."'></div> <div class='printomatictext' id='".$id."' ".$alt_tag." data-print_target='".$target."'>".$title."</div><div style='clear: both;'></div>";
		}
		else if($printicon){
			$output = "<".$tag." class='printomatic ".$printstyle." ".$class."' id='".$id."' ".$alt_tag." data-print_target='".$target."'></".$tag.">";
		}
		else if($title){
			$output = "<".$tag." class='printomatictext ".$class."' id='".$id."' ".$alt_tag." data-print_target='".$target."'>".$title."</".$tag.">";
		}
		return  $output;
	}

	function printer_scripts() {
		if ( empty( $this->add_print_script ) ){
			return;
		}

		?>
		<script language="javascript" type="text/javascript">
			var print_data = <?php echo json_encode( $this->add_print_script ); ?>;
		</script>
		<?php
	}

	/**
	 * Admin options page
	 */
	function options_page() {
			$like_it_arr = array(
						__('really tied the room together', 'One-click-print'),
);
	
		$rand_key = array_rand($like_it_arr);
		$like_it = $like_it_arr[$rand_key];
	?>
		<div class="wrap">
			<div class="icon32" id="icon-options-custom" style="background:url( <?php echo plugins_url( 'css/print-icon.png', __FILE__ ) ?> ) no-repeat 50% 50%"><br></div>
			<h2>One Click Print</h2>
		</div>

		<div class="postbox-container metabox-holder meta-box-sortables" style="width: 69%">
			<div style="margin:0 5px;">
				<div class="postbox">
					<div class="handlediv" title="<?php _e( 'Click to toggle' ) ?>"><br/></div>
					<h3 class="handle"><?php _e( 'One Click Print Settings', 'One-Click-Print' ) ?></h3>
					<div class="inside">
						<form method="post" action="options.php">
							<?php
								settings_fields( $this->domain );
								$options = $this->options;
							?>
							<fieldset class="options">
								<table class="form-table">
								<tr>
									<th><?php _e( 'Default Target Attribute' , 'One-Click-Print'  ) ?></th>
									<td><label><input type="text" id="<?php echo $this->options_name ?>[print_target]" name="<?php echo $this->options_name ?>[print_target]" value="<?php echo $options['print_target']; ?>" />
										<br /><span class="description"><?php echo('Print target. See in the documentation for <a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>.One-click-print'); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Default Print Title' , 'One-Click-Print'  ) ?></th>
									<td><label>
										<textarea id="<?php echo $this->options_name ?>[print_title]" name="<?php echo $this->options_name ?>[print_title]" style="width: 100%;"><?php echo $options['print_title']; ?></textarea>
									</label></td>
								</tr>
								<tr>
									<th><?php _e( 'Use Print Icon', 'One-Click-Print' ) ?></th>
									<td><label><select id="<?php echo $this->options_name ?>[printicon]" name="<?php echo $this->options_name ?>[printicon]">
										<?php
											$se_array = array(
												__('Yes', 'One-Click-Print') => true,
												__('No', 'One-Click-Print') => false
											);
											foreach( $se_array as $key => $value){
												$selected = '';
												if($options['printicon'] == $value){
													$selected = 'SELECTED';
												}
												echo '<option value="'.$value.'" '.$selected.'>'.$key.'</option>';
											}
										?>
										</select>
										<br /><span class="description"><?php echo('Use printer icon. See in the documentation for <a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>.One-Click-Print'); ?></span></label>
									</td>
								</tr>

								<tr>
									<th><?php _e( 'Printer Icon', 'One-Click-Print') ?></th>
									<td>
										<?php
											if( empty($options['printstyle']) ){
												$options['printstyle']	= 'pom-default';
											}
											$si_array = array(
												__('Default', 'One-Click-Print') => 'pom-default',
												__('Small', 'One-Click-Print') => 'pom-small',
												__('Small Black', 'One-Click-Print') => 'pom-small-black',
												__('Small Grey', 'One-Click-Print') => 'pom-small-grey',
												__('Small White', 'One-Click-Print') => 'pom-small-white'
											);
											$icon_array = array(
												'pom-default' => 'print-icon.png',
												'pom-small' => 'print-icon-small.png',
												'pom-small-black' => 'print-icon-small-black.png',
												'pom-small-grey' => 'print-icon-small-grey.png',
												'pom-small-white' => 'print-icon-small-white.png'
											);
											foreach( $si_array as $key => $value){
												$selected = '';
												if($options['printstyle'] == $value){
													$selected = 'checked';
												}
												?>
												<label><input type="radio" name="<?php echo $this->options_name ?>[printstyle]" value="<?php echo $value; ?>" <?php echo $selected; ?>> &nbsp;<?php echo $key; ?>
												<img src="<?php echo plugins_url( 'css/'.$icon_array[$value], __FILE__ ) ?>"/>
												</label><br/>
												<?php
											}
										?>
										<span class="description"><?php echo('If using a printer icon, which printer icon should be used? See in the documentation for <a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>. One-click-print'); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Custom Style', 'One-Click-Print' ) ?></th>
									<td><label><textarea id="<?php echo $this->options_name ?>[custom_page_css]" name="<?php echo $this->options_name ?>[custom_page_css]" style="width: 100%; height: 150px;"><?php echo $options['custom_page_css']; ?></textarea>
										<br /><span class="description"><?php echo('Custom <strong>display page</strong> CSS Style for <em>Ultimate Flexibility</em>. Here are some helpful<a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>. One-click-print' ); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Use Theme CSS For Print Page', 'One-Click-Print' ) ?></th>
									<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[use_theme_css]" name="<?php echo $this->options_name ?>[use_theme_css]" value="1"  <?php echo checked( $options['use_theme_css'], 1 ); ?> /> <?php _e('Yes, Use Theme CSS', 'One-click-print'); ?>
										<br /><span class="description"><?php _e('Use the CSS style of the active theme for print page.', 'One-click-print'); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Custom Print Page Style', 'One-click-print' ) ?></th>
									<td><label><textarea id="<?php echo $this->options_name ?>[custom_css]" name="<?php echo $this->options_name ?>[custom_css]" style="width: 100%; height: 150px;"><?php echo $options['custom_css']; ?></textarea>
										<br /><span class="description"><?php _e( 'Custom <strong>print page</strong> CSS style for <em>Ultimate Flexibility</em>', 'One-click-print' ) ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Do Not Print Elements', 'One-click-print' ) ?></th>
									<td><label><input type="text" id="<?php echo $this->options_name ?>[do_not_print]" name="<?php echo $this->options_name ?>[do_not_print]" value="<?php echo $options['do_not_print']; ?>" />
										<br /><span class="description"><?php echo('Content elements to exclude from the print page. See %sDo Not Print Attribute%s in the documentation for <a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>. One-click-print'); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Print Page Top HTML', 'One-Click-Print' ) ?></th>
									<td><label><textarea id="<?php echo $this->options_name ?>[html_top]" name="<?php echo $this->options_name ?>[html_top]" style="width: 100%; height: 150px;"><?php echo $options['html_top']; ?></textarea>
										<br /><span class="description"><?php echo('HTML to be inserted at the top of the print page. See %sHTML Top Attribute%s in the documentation for<a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>. One-click-print' ); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Print Page Bottom HTML', 'One-click-print' ) ?></th>
									<td><label><textarea id="<?php echo $this->options_name ?>[html_bottom]" name="<?php echo $this->options_name ?>[html_bottom]" style="width: 100%; height: 150px;"><?php echo $options['html_bottom']; ?></textarea>
<br /><span class="description"><?php echo('HTML to be inserted at the bottom of the print page. See HTML Bottom Attribute in the documentation for <a href="https://www.printinlondon.co.uk/wordpress-plugin/" target="_blank"> more info</a>. One-click-print' ); ?></span></label>
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Shortcode Loads Scripts', 'One-click-print' ) ?></th>
									<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[script_check]" name="<?php echo $this->options_name ?>[script_check]" value="1"  <?php echo checked( $options['script_check'], 1 ); ?> /> <?php _e('Only load scripts with shortcode.', 'One-Click-Print'); ?>
										<br /><span class="description"><?php _e('Only load  One-Click-Print scripts if [one-click-print] shortcode is used.', 'One-Click-Print'); ?></span></label>
									</td>
								</tr>

								<tr>
									<th><?php _e( 'Activate jQuery fix.clone', 'One-click-print' ) ?></th>
									<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[fix_clone]" name="<?php echo $this->options_name ?>[fix_clone]" value="1"  <?php echo checked( $options['fix_clone'], 1 ); ?> /> <?php _e('Activate if textbox or select elements are not printing.', 'One-Click-Print'); ?>
										<br /><span class="description"><?php echo('Addresses known bug with textbox and select elements when using the One-click-print'); ?></span></label>
									</td>
								</tr>

								<tr>
									<th><?php _e( 'Pause Before Print', 'One-Click-Print' ) ?></th>
									<td><label><input type="text" id="<?php echo $this->options_name ?>[pause_time]" name="<?php echo $this->options_name ?>[pause_time]" value="<?php echo $options['pause_time']; ?>" />
										<br /><span class="description"><?php _e('Amount of time in milliseconds to pause and let the page fully load before triggering the print dialogue box', 'One-Click-Print'); ?></span></label>
									</td>
								</tr>

								<tr>
									<th><?php _e( 'Close After Print', 'One-Click-Print' ) ?></th>
									<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[close_after_print]" name="<?php echo $this->options_name ?>[close_after_print]" value="1"  <?php echo checked( $options['close_after_print'], 1 ); ?> /> <?php _e('Close Print Window after Print', 'One'); ?>
										<br /><span class="description"><?php _e('Automatically close the print window after the print dialogue box is closed. Leave this option unchecked when troubleshooting print issues.'); ?></span></label>
									</td>
								</tr>

								</table>
							</fieldset>

							<p class="submit">
								<input class="button-primary" type="submit" style="float:right" value="<?php _e( 'Save Changes' ) ?>" />
							</p>
						</form>
					</div>
				</div>
			</div>
		</div>

	<?php
	}

	/**
	 * Set options from save values or defaults
	 */
	function _set_options() {
		// set options
		$saved_options = get_option( $this->options_name );

		// backwards compatible (old values)
		if ( empty( $saved_options ) ) {
			$saved_options = get_option( $this->domain . 'options' );
		}

		// set all options
		if ( ! empty( $saved_options ) ) {
			foreach ( $this->options AS $key => $option ) {
				$this->options[ $key ] = ( empty( $saved_options[ $key ] ) ) ? '' : $saved_options[ $key ];
			}
		}
	}

} // end class WP_Print_O_Matic

/**
 * Create instance
 */
$WP_Print_O_Matic = new WP_Print_O_Matic;

?>
