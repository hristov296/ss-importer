<?php
/**
 * Plugin Name: SS Importer
 * Plugin URI: https://github.com/ahmadawais/create-guten-block/
 * Description: SS Importer - Import anything
 * Author: supersait
 * Author URI: https://supersait.bg/
 * Version: 1.0.0
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 *
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SS_IMPORTER_VERSION', '1.0');

require( MY_PLUGIN_PATH . 'view.php' );
require( MY_PLUGIN_PATH . 'classes/SS_Import_Helper.php' );
require( MY_PLUGIN_PATH . 'classes/SS_Import_Submit.php' );


class SS_Import_Main {
	public $is_woocommerce;
	public $im_post_type;
	public static $im_is_wpml;
	public static $csv_max_length = 21000;
	public static $site_post_types;
	public static $sheets_url;

	function __construct() {
		$this->is_woocommerce = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
		add_action( 'admin_menu', array($this, 'im_admin_menu') );
		add_action( 'admin_init', array($this, 'im_admin_init') );
	}

	function im_admin_menu() {
		add_menu_page( 'SS Import', 'SS Import', 'manage_options', 'ss-import.php', 'ss_import_view', 'dashicons-admin-page', 74  );
		add_submenu_page( 'ss-import.php', 'Standard import', 'Standard import', 'manage_options', 'ss-import.php',  'ss_import_view' );
		add_submenu_page( 'ss-import-submit.php', 'Standard import', 'Standard import', 'manage_options', 'ss-import-submit.php',  array($this, 'ss_handle_submit') );
		add_submenu_page( 'ss-import-preview.php', 'Standard import', 'Standard import', 'manage_options', 'ss-import-preview.php',  array($this, 'ss_handle_preview') );
		add_submenu_page( 'ss-import-custom.php', 'Standard import', 'Standard import', 'manage_options', 'ss-import-custom.php',  array($this, 'ss_handle_custom') );
	}

	function im_admin_init() {
		global $pagenow;
		self::$site_post_types = get_post_types(array('public'=>true), 'objects');
		self::$sheets_url = !empty($_POST['im-file-url']) ? $_POST['im-file-url'] : get_option( 'ss_import_url', '' );

		// needs modification
		// if ($pagenow = 'admin.php' && isset( $_GET['page'] ) && ($_GET['page'] == 'ss-import-submit.php' || $_GET['page'] == 'ss-import-preview.php') ) {
		// 	wp_redirect( menu_page_url('ss-import.php', false) );
		// 	exit;
		// }
	}

	function enqueue_admin_notice() {

		// needs mods
		add_action( 'admin_notices', function() {
			printf( '<div class="%1$s notice"><p>%2$s</p></div>', esc_attr( 'notice-error' ), esc_html( 'asd' ) );
		} );
	}



	function form_action($req) {
    if (!empty($req['im-post-type'])) {
      $this->im_post_type = $req['im-post-type'];
    }
    if (!empty($req['im-csv-length'])) {
      self::$csv_max_length = $req['im-csv-length'];
    }
    if (!empty($req['im-is-wpml'])) {
      self::$im_is_wpml = true;
    }
	}

	function ss_handle_preview() {
		$this->form_action($_POST);

		if (!empty($_POST['im-preview'])) {
			if ( $file_type = SS_Import_Helper::ss_checks_file($_FILES['im-file'], $_POST['im-file-url']) ) {
				switch($file_type) {
					case 'csv':
						$data = SS_Import_Helper::ss_parse_csv($_FILES['im-file']['tmp_name'], self::$im_is_wpml, self::$csv_max_length);
						echo '<pre>';var_dump($data);echo '</pre>';
						break;
	
					case 'json':
						$string = json_decode(file_get_contents($_FILES['im-file']['tmp_name']), true);
						if (!$string){
							echo 'Invalid json!';
							return;
						}
						echo '<pre>';var_dump($string);echo '</pre>';
						break;
	
					case 'csv_url':
						update_option('ss_import_url', $_POST['im-file-url']);
						$data = SS_Import_Helper::ss_parse_csv($_POST['im-file-url'], self::$im_is_wpml, self::$csv_max_length);
						echo '<pre>';var_dump($data);echo '</pre>';
						break;
	
					default:
						echo 'Something went wrong';
						break;
				}
			}
		}
	}

	function ss_handle_submit() {
		$this->form_action($_POST);

		if (!empty($_POST['im-submit'])) {
      if ( $file_type = SS_Import_Helper::ss_checks_file($_FILES['im-file'], $_POST['im-file-url']) ) {
          switch ($file_type) {
						case 'csv':
								$data = SS_Import_Helper::ss_parse_csv($_FILES['im-file']['tmp_name'], self::$im_is_wpml, self::$csv_max_length);
								$ss_importer = new SS_Import_Submit();
								self::$im_is_wpml ? 
									$ss_importer->ss_insert_csv_wpml($data, $this->im_post_type, $this->is_woocommerce ) : 
									$ss_importer->ss_insert_csv_no_wpml($data, $this->im_post_type, $this->is_woocommerce );
								break;
								
						case 'json':
								// $this->ss_submit_json();
								break;

						case 'csv_url':
								update_option('ss_import_url', $_POST['im-file-url']);
								$data = SS_Import_Helper::ss_parse_csv($_POST['im-file-url'], self::$im_is_wpml, self::$csv_max_length);
								$ss_importer = new SS_Import_Submit();
								self::$im_is_wpml ? 
									$ss_importer->ss_insert_csv_wpml($data, $this->im_post_type, $this->is_woocommerce ) 
									: $ss_importer->ss_insert_csv_no_wpml($data, $this->im_post_type, $this->is_woocommerce );
								break;

						default:
								echo 'Something went wrong';
								break;
          }
      }
    }
	}

	function ss_handle_custom() {
		$this->form_action($_POST);

		if (!empty($_POST['im-custom'])) {
      if ( $file_type = SS_Import_Helper::ss_checks_file($_FILES['im-file'], $_POST['im-file-url']) ) {
          switch ($file_type) {
						case 'csv':
								$data = SS_Import_Helper::ss_parse_csv($_FILES['im-file']['tmp_name'], self::$im_is_wpml, self::$csv_max_length);
								$ss_importer = new SS_Import_Submit();
								$ss_importer->ss_custom_action($data, $this->im_post_type, $this->is_woocommerce );
								break;
								
						case 'json':
								// $this->ss_submit_json();
								break;

						case 'csv_url':
								update_option('ss_import_url', $_POST['im-file-url']);
								$data = SS_Import_Helper::ss_parse_csv($_POST['im-file-url'], self::$im_is_wpml, self::$csv_max_length);
								$ss_importer = new SS_Import_Submit();
								$ss_importer->ss_custom_action($data, $this->im_post_type, $this->is_woocommerce );
								break;

						default:
								echo 'Something went wrong';
								break;
          }
			}
		}
	}
}


new SS_Import_Main();