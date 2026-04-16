<?php
/*
Plugin Name: Ultimate WP Live Search
Plugin URI: https://laptrinhweb.net
Description: Plugin hỗ trợ tìm kiếm trực tiếp (Live Search) với tốc độ cao bằng cách sử dụng cache file JSON.
Version: 1.0.1
Author: Nguyễn Đức Tuệ
Author URI: https://laptrinhweb.net
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
if(!function_exists('add_action')) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

define('UWLS_URL', plugin_dir_url(__FILE__));
define('UWLS_PATH', plugin_dir_path(__FILE__));

if(!class_exists('Ultimate_WP_Live_Search')) {

	class Ultimate_WP_Live_Search {

		private $_version = '1.0.1';
		private $_plugin_slug = 'ultimate-wp-live-search';
		private $_option_name = 'uwls_settings';

		private $_uwls_settings;

		public function __construct()
		{
			$this->_uwls_settings = get_option($this->_option_name);
			add_action('admin_menu', [$this, 'add_settings_page']);
			add_action('admin_init', [$this, 'setup_field']);
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_setting_link'] );

			add_action('activated_plugin', array($this, 'uwls_activation_redirect'));

			add_action('admin_enqueue_scripts', function(){
				wp_register_script('uwls_admin_js', UWLS_URL . 'custom.js', array('jquery'));
				wp_localize_script('uwls_admin_js', 'uwls_js', [
					'url' => admin_url('admin-ajax.php')
				]);
				wp_enqueue_script('uwls_admin_js');
			});

			add_action('wp_enqueue_scripts', function(){
				wp_enqueue_style('uwls-custom-css', UWLS_URL . 'custom.min.css');
				wp_enqueue_script('uwls-style-js', UWLS_URL . 'style.min.js', array( 'jquery' ),'',true );
			});

			add_action('wp_ajax_create_data_search', [$this, 'create_data_search']);

			add_action('wp_footer', [$this, 'search_client']);
		}

		public function add_settings_page()
		{
			$parent_slug = 'options-general.php';
			$page_title = 'Ultimate WP Live Search';
			$menu_title = 'Ultimate WP Live Search';
			$capability = 'manage_options';
			$slug = $this->_plugin_slug;
			$callback = [$this, 'plugin_settings_page_content'];
		
			add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $slug, $callback);
		}

		public function setup_field()
		{
			register_setting(
				$this->_plugin_slug, // Option group
				$this->_option_name, // Option name
				array( $this, 'sanitize' ) // Sanitize
			);

			add_settings_section(
				'uwls_section', // ID
				'CẤU HÌNH', // Title
				false, // Callback
				$this->_plugin_slug // Page
			);

			$fields_section = [
				[
					'id'       => 'uwls_selector',
					'title'    => 'ID / Class ô tìm kiếm',
					'callback' => 'uwls_selector_html'
				],
				[
					'id'       => 'uwls_button_create',
					'title'    => 'Tạo dữ liệu',
					'callback' => 'uwls_button_create_html'
				],
				[
					'id'       => 'uwls_support',
					'title'    => 'Liên hệ hỗ trợ',
					'callback' => 'uwls_support_html'
				]
			];

			foreach($fields_section as $field){
				add_settings_field(
					$field['id'],
					$field['title'],
					[$this, $field['callback']],
					$this->_plugin_slug,
					'uwls_section' // ID Setting Section
				);
			}
		}

		public function sanitize($input)
		{
			$sanitized_input = [];

			foreach($input as $key => $item) {
				$sanitized_input[$key] = sanitize_text_field($item);
			}

			return $sanitized_input;
		}

		public function plugin_settings_page_content()
		{
			if(!current_user_can('manage_options')) return;
			?>
				<div class="wrap">
					<h2>ULTIMATE WP LIVE SEARCH - TÌM KIẾM TỐC ĐỘ CAO</h2>

					<form method="post" action="options.php">
						<?php
							settings_fields( $this->_plugin_slug );
							do_settings_sections( $this->_plugin_slug );
							submit_button();
						?>
					</form>

					<div class="loading_snipper">
				        <div class='uil-ring-css'>
				            <div></div>
				        </div>
				    </div>

				    <script>
				    	function loading_snipper(type = true) {
				            if(type) {
				                jQuery('.loading_snipper').css('display', 'block')
				            } else {
				                jQuery('.loading_snipper').css('display', 'none')
				            }
				        }
				    </script>

				    <style>
				    	div.loading_snipper{position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(16,16,16,.5);z-index:999999999;display:none}@-ms-keyframes uil-ring-anim{0%{-ms-transform:rotate(0);-moz-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);transform:rotate(0)}100%{-ms-transform:rotate(360deg);-moz-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);transform:rotate(360deg)}}@-moz-keyframes uil-ring-anim{0%{-ms-transform:rotate(0);-moz-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);transform:rotate(0)}100%{-ms-transform:rotate(360deg);-moz-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);transform:rotate(360deg)}}@-webkit-keyframes uil-ring-anim{0%{-ms-transform:rotate(0);-moz-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);transform:rotate(0)}100%{-ms-transform:rotate(360deg);-moz-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);transform:rotate(360deg)}}@-o-keyframes uil-ring-anim{0%{-ms-transform:rotate(0);-moz-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);transform:rotate(0)}100%{-ms-transform:rotate(360deg);-moz-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);transform:rotate(360deg)}}@keyframes uil-ring-anim{0%{-ms-transform:rotate(0);-moz-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);transform:rotate(0)}100%{-ms-transform:rotate(360deg);-moz-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);transform:rotate(360deg)}}.uil-ring-css{margin:auto;position:absolute;top:0;left:0;bottom:0;right:0;width:200px;height:200px;transform:scale(.79)}.uil-ring-css>div{position:absolute;display:block;width:160px;height:160px;top:20px;left:20px;border-radius:80px;box-shadow:0 6px 0 0 #fff;-ms-animation:uil-ring-anim 1s linear infinite;-moz-animation:1s linear infinite uil-ring-anim;-webkit-animation:1s linear infinite uil-ring-anim;-o-animation:1s linear infinite uil-ring-anim;animation:1s linear infinite uil-ring-anim}
				    </style>	
				</div>
			<?php
		}

		public function uwls_selector_html() {
			printf(
					'<input type="text" style="width:300px" id="uwls_selector" name="'.$this->_option_name.'[uwls_selector]" value="%s" placeholder="#id hoặc .class" required />',
					isset( $this->_uwls_settings['uwls_selector'] ) ? esc_attr( $this->_uwls_settings['uwls_selector']) : ''
				);
		}

		public function uwls_button_create_html() {
			echo '<a href="javascript:;" class="button uwls-btn-create-cache">Tạo dữ liệu</a>';
		}

		public function uwls_support_html() {
			echo '<a href="https://laptrinhweb.net" target="_blank"><i>Lập Trình Web Net</i></a>';
		}

		public function add_setting_link($links)
		{
			array_unshift($links, '<a href="' . admin_url('options-general.php?page='.$this->_plugin_slug) . '">' . __('Settings') . '</a>');
			return $links;
		}

		public function uwls_activation_redirect($plugin)
		{
		    if($plugin == plugin_basename( __FILE__ )) {
		        exit(wp_redirect(admin_url('options-general.php?page='. $this->_plugin_slug )));
		    }
		}

		public function create_data_search()
		{
			$posts = get_posts(array(
				'posts_per_page' => -1,
				'post_type' => array('post'),
				'orderby'   => 'ID',
        		'order' => 'ASC',
				'fields' => 'ids',
			));
			
			$data_post = [];
			foreach($posts as $post) {
				$data_post[] = [
					'title' => get_the_title($post),
					'url' => get_permalink($post),
					'thumbnail' => get_the_post_thumbnail_url($post),
					'price' => ''
				];
			}

			$products = get_posts(array(
				'posts_per_page' => -1,
				'post_type' => array('product'),
				'orderby'   => 'ID',
        		'order' => 'ASC',
				'fields' => 'ids',
			));

			$data_product = [];
			foreach($products as $product) {
				$price = '';
				if(function_exists('wc_get_product')) {
					$_product = wc_get_product($product);
					if($_product) {
						$price = strip_tags($_product->get_price_html());
					}
				} else {
					$price = get_post_meta($product, '_price', true);
				}

				$data_product[] = [
					'title' => get_the_title($product),
					'url' => get_permalink($product),
					'thumbnail' => get_the_post_thumbnail_url($product),
					'price' => $price
				];
			}

			$data_all = ['post' => $data_post, 'product' => $data_product];

			$new_file = 'data-'.time().'.json';
			$json_file = UWLS_PATH.$new_file;
			$jsonString = json_encode($data_all, JSON_UNESCAPED_UNICODE);

			file_put_contents($json_file, $jsonString);

			$old_file = get_option('uwls_data_file');

			if($old_file) {
				$old_file = UWLS_PATH.$old_file;
				wp_delete_file($old_file);
				update_option('uwls_data_file', $new_file);
			} else {
				add_option('uwls_data_file', $new_file);
			}

			wp_send_json_success([
				'status'  => true,
				'message' => 'Tạo dữ liệu thành công'
			]);
		}

		public function search_client()
		{
			$data_file = get_option('uwls_data_file');

			if(!empty($data_file)):
			$file = UWLS_URL . $data_file;
			?>
				<script>

					jQuery(document).ready(function($){

						var selector = '<?php echo $this->_uwls_settings['uwls_selector'] ?>';
						var file = '<?php echo $file ?>';

						if (selector && file) {
							$.getJSON(file, function(data) {
								var posts = data.post || [];
								var products = data.product || [];
								var allData = [...posts, ...products];

								$(selector).each(function() {
									$(this).autocomplete({
										delay: 10,
										source: function(request, response) {
											var term = request.term.toLowerCase();
											var words = term.split(' ').filter(w => w !== '');
											
											// Filter data: All words must be present in the title
											var results = allData.filter(item => {
												var title = item.title.toLowerCase();
												return words.every(word => title.includes(word));
											});

											// Limit to 10 results
											response(results.slice(0, 10));
										},
										select: function(event, ui) {
											window.open(ui.item.url, '_blank');
											return false; // Ngăn không cho gán giá trị vào ô input
										}
									}).data("ui-autocomplete")._renderItem = function(ul, item) {
										var term = this.element.val();
										var words = term.split(' ').filter(w => w !== '');
										var displayTitle = item.title;

										// Highlight matching words
										words.forEach(word => {
											if (word) {
												var regex = new RegExp("(" + word + ")", "gi");
												displayTitle = displayTitle.replace(regex, "<mark>$1</mark>");
											}
										});

										return $("<li>")
											.append("<a href='" + item.url + "' target='_blank' style='display: flex; align-items: center; justify-content: space-between; width: 100%; text-decoration: none; color: inherit; padding: 5px 10px;'><div style='display: flex; align-items: center;'><img src='" + item.thumbnail + "' style='width: 40px; height: 40px; object-fit: cover; margin-right: 10px;'>" + displayTitle + "</div><div style='font-weight: bold; color: #ff5722; white-space: nowrap; margin-left: 10px;'>" + item.price + "</div></a>")
											.appendTo(ul);
									};
								});
							});
						}
					});

				</script>

				<style>
					mark{border:none!important;background:0 0!important;font-weight:700;color:#ffa95e}.ui-autocomplete{z-index:999999999!important}.ui-autocomplete img{width:50px;aspect-ratio:1/1;object-fit:cover}.ui-widget.ui-widget-content{max-height:80vh;overflow:auto;margin-top:5px!important;border-radius:5px;border:none;box-shadow:0 8px 10px -5px rgb(0 0 0 / 20%),0 16px 24px 2px rgb(0 0 0 / 14%),0 6px 30px 5px rgb(0 0 0 / 10%)}.ui-menu-item:hover .ui-menu-item-wrapper,.ui-state-active{background:#538ebb!important;border:none!important}.ui-menu-item-wrapper{text-overflow:ellipsis;white-space:nowrap;display:block;overflow:hidden}.ui-menu .ui-menu-item-wrapper{padding:5px!important}.ui-widget.ui-widget-content::-webkit-scrollbar-track{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);background-color:#f5f5f5;border-radius:10px}.ui-widget.ui-widget-content::-webkit-scrollbar{width:5px;height:0;background-color:#f5f5f5}.ui-widget.ui-widget-content::-webkit-scrollbar-thumb{background-color:#aaa;border-radius:10px;background-image:-webkit-linear-gradient(90deg,rgba(0,0,0,.2) 25%,transparent 25%,transparent 50%,rgba(0,0,0,.2) 50%,rgba(0,0,0,.2) 75%,transparent 75%,transparent)}
				</style>
			<?php
			endif;
		}
	}
	new Ultimate_WP_Live_Search();
}