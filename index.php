<?php
/*
Plugin Name: Ultimate WP Live Search
Plugin URI: https://laptrinhweb.net
Description: Plugin hỗ trợ tìm kiếm trực tiếp (Live Search) với tốc độ cao bằng cách sử dụng cache file JSON.
Version: 1.2.1	
Author: Nguyễn Đức Tuệ
Author URI: https://laptrinhweb.net
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define('UWLS_URL', plugin_dir_url(__FILE__));
define('UWLS_PATH', plugin_dir_path(__FILE__));
define('UWLS_VERSION', '1.1.0');

// Khởi tạo Class
add_action('plugins_loaded', function() {
    new Ultimate_WP_Live_Search();
});

if(!class_exists('Ultimate_WP_Live_Search')) {

	class Ultimate_WP_Live_Search {

		public $plugin_slug = 'ultimate-wp-live-search';
		public $option_name = 'uwls_settings';

		public function __construct()
		{
			add_action('admin_menu', [$this, 'add_settings_page']);
			add_action('admin_init', [$this, 'setup_field']);
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_setting_link'] );
			add_action('activated_plugin', array($this, 'uwls_activation_redirect'));

			add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
			add_action('wp_enqueue_scripts', [$this, 'public_scripts']);
			
			// AJAX hooks
			add_action('wp_ajax_uwls_do_create_data', [$this, 'create_data_search']);

			// Frontend hooks
			add_action('wp_footer', [$this, 'search_client']);
		}

		public function admin_scripts($hook)
		{
			if (strpos($hook, $this->plugin_slug) === false) return;

			wp_enqueue_style('uwls-sweetalert-css', 'https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.13.3/sweetalert2.min.css');
			wp_enqueue_script('uwls-sweetalert-js', 'https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.13.3/sweetalert2.min.js', array('jquery'));

			wp_enqueue_script('uwls_admin_js', UWLS_URL . 'custom.js', array('jquery', 'uwls-sweetalert-js'), UWLS_VERSION, true);
			wp_localize_script('uwls_admin_js', 'uwls_js', [
				'url'    => admin_url('admin-ajax.php'),
				'nonce'  => wp_create_nonce('uwls_nonce_secure')
			]);
		}

		public function public_scripts()
		{
			wp_enqueue_style('uwls-custom-css', UWLS_URL . 'custom.min.css');
			wp_enqueue_script('uwls-style-js', UWLS_URL . 'style.min.js', array( 'jquery' ), UWLS_VERSION, true );
		}

		public function add_settings_page()
		{
			add_submenu_page('options-general.php', 'Ultimate WP Live Search', 'Ultimate WP Live Search', 'manage_options', $this->plugin_slug, [$this, 'plugin_settings_page_content']);
		}

		public function setup_field()
		{
			register_setting($this->plugin_slug, $this->option_name, array( $this, 'sanitize' ));
			add_settings_section('uwls_section', 'CẤU HÌNH', false, $this->plugin_slug);
			add_settings_field('uwls_selector', 'ID / Class ô tìm kiếm', [$this, 'uwls_selector_html'], $this->plugin_slug, 'uwls_section');
			add_settings_field('uwls_post_types', 'Chọn loại bài viết', [$this, 'uwls_post_types_html'], $this->plugin_slug, 'uwls_section');
			add_settings_field('uwls_button_create', 'Tạo dữ liệu', [$this, 'uwls_button_create_html'], $this->plugin_slug, 'uwls_section');
			add_settings_field('uwls_support', 'Liên hệ hỗ trợ', [$this, 'uwls_support_html'], $this->plugin_slug, 'uwls_section');
		}

		public function sanitize($input)
		{
			$sanitized_input = [];
			if(is_array($input)) {
				foreach($input as $key => $item) {
					if (is_array($item)) {
						$sanitized_input[$key] = array_map('sanitize_text_field', $item);
					} else {
						$sanitized_input[$key] = sanitize_text_field($item);
					}
				}
			}
			return $sanitized_input;
		}

		public function plugin_settings_page_content()
		{
			?>
			<div class="wrap">
				<h2>ULTIMATE WP LIVE SEARCH - TÌM KIẾM TỐC ĐỘ CAO</h2>
				<form method="post" action="options.php">
					<?php
						settings_fields( $this->plugin_slug );
						do_settings_sections( $this->plugin_slug );
						submit_button();
					?>
				</form>
				<div class="loading_snipper">
					<div class='uil-ring-css'><div></div></div>
				</div>
				<script>
					function loading_snipper(type = true) {
						jQuery('.loading_snipper').css('display', type ? 'block' : 'none');
					}
				</script>
				<style>
					div.loading_snipper{position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(16,16,16,.5);z-index:999999999;display:none}.uil-ring-css{margin:auto;position:absolute;top:0;left:0;bottom:0;right:0;width:200px;height:200px;transform:scale(.79)}.uil-ring-css>div{position:absolute;display:block;width:160px;height:160px;top:20px;left:20px;border-radius:80px;box-shadow:0 6px 0 0 #fff;animation:uil-ring-anim 1s linear infinite}@keyframes uil-ring-anim{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
				</style>	
			</div>
			<?php
		}

		public function uwls_selector_html() {
			$options = get_option($this->option_name);
			printf(
				'<input type="text" style="width:300px" id="uwls_selector" name="'.$this->option_name.'[uwls_selector]" value="%s" placeholder="#id hoặc .class" required />',
				isset( $options['uwls_selector'] ) ? esc_attr( $options['uwls_selector'] ) : ''
			);
		}

		public function uwls_post_types_html() {
			$options = get_option($this->option_name);
			$selected_types = isset($options['uwls_post_types']) ? (array)$options['uwls_post_types'] : array('post', 'product');
			
			$args = array(
				'public'   => true,
				'_builtin' => false
			);
			$output = 'names'; // names or objects, note names is the default
			$operator = 'and'; // 'and' or 'or'
			$post_types = get_post_types($args, $output, $operator);
			
			$builtin_types = array('post' => 'Bài viết', 'page' => 'Trang');
			$all_types = array_merge($builtin_types, (array)$post_types);

			foreach ($all_types as $slug => $label) {
				if ($slug == 'attachment') continue;
				$checked = in_array($slug, $selected_types) ? 'checked' : '';
				echo '<label style="margin-right:15px"><input type="checkbox" name="'.$this->option_name.'[uwls_post_types][]" value="'.$slug.'" '.$checked.'> '.$label.'</label>';
			}
		}

		public function uwls_button_create_html() {
			echo '<a href="javascript:;" class="button uwls-btn-create-cache">Tạo dữ liệu</a>';
		}

		public function uwls_support_html() {
			echo '<a href="https://laptrinhweb.net" target="_blank"><i>Lập Trình Web Net</i></a>';
		}

		public function add_setting_link($links)
		{
			array_unshift($links, '<a href="' . admin_url('options-general.php?page='.$this->plugin_slug) . '">' . __('Settings') . '</a>');
			return $links;
		}

		public function uwls_activation_redirect($plugin)
		{
		    if($plugin == plugin_basename( __FILE__ )) {
		        exit(wp_redirect(admin_url('options-general.php?page='. $this->plugin_slug )));
		    }
		}

		public function create_data_search()
		{
			check_ajax_referer('uwls_nonce_secure', 'nonce');

			if(!current_user_can('manage_options')) {
				wp_send_json_error(['message' => 'Bạn không có quyền thực hiện']);
			}

			$options = get_option($this->option_name);
			$selected_types = isset($options['uwls_post_types']) ? (array)$options['uwls_post_types'] : array('post', 'product');

			$data_all = [];

			foreach ($selected_types as $post_type) {
				$args = array(
					'posts_per_page' => -1,
					'post_type'      => array($post_type),
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
				);
				
				$items = get_posts($args);
				$type_data = [];

				foreach ($items as $item_id) {
					$entry = [
						'title'     => get_the_title($item_id),
						'url'       => get_permalink($item_id),
						'thumbnail' => get_the_post_thumbnail_url($item_id),
						'reg_price' => '',
						'sale_price' => '',
						'sku'       => ''
					];

					// Xử lý riêng cho sản phẩm WooCommerce
					if ($post_type === 'product') {
						if (function_exists('wc_get_product')) {
							$product = wc_get_product($item_id);
							if ($product) {
								$entry['sku'] = $product->get_sku();
								if ($product->is_type('variable')) {
									$entry['reg_price'] = strip_tags($product->get_variation_regular_price('min', true));
									$entry['sale_price'] = strip_tags($product->get_variation_sale_price('min', true));
								} else {
									$entry['reg_price'] = strip_tags(wc_price($product->get_regular_price()));
									$entry['sale_price'] = strip_tags(wc_price($product->get_sale_price()));
								}

								if (empty($entry['sale_price']) || $entry['sale_price'] == $entry['reg_price']) {
									$entry['sale_price'] = strip_tags(wc_price($product->get_price()));
									$entry['reg_price'] = '';
								}
							}
						} else {
							$entry['sale_price'] = get_post_meta($item_id, '_price', true);
							$entry['sku'] = get_post_meta($item_id, '_sku', true);
						}
					}

					$type_data[] = $entry;
				}

				$data_all[$post_type] = $type_data;
			}

			$new_file = 'data-'.time().'.json';
			$json_file = UWLS_PATH.$new_file;
			$jsonString = json_encode($data_all, JSON_UNESCAPED_UNICODE);

			if(file_put_contents($json_file, $jsonString) === false) {
				wp_send_json_error(['message' => 'Lỗi: Không thể ghi file. Kiểm tra quyền ghi folder.']);
			}

			$old_file = get_option('uwls_data_file');
			if($old_file) {
				$old_path = UWLS_PATH.$old_file;
				if(file_exists($old_path)) {
					wp_delete_file($old_path);
				}
			}
			update_option('uwls_data_file', $new_file);

			wp_send_json_success([
				'status'  => true,
				'message' => 'Tạo dữ liệu thành công'
			]);
		}

		public function search_client()
		{
			$data_file = get_option('uwls_data_file');
			$settings = get_option($this->option_name);

			if(!empty($data_file)):
			$file = UWLS_URL . $data_file;
			?>
				<script>
					jQuery(document).ready(function($){
						var selector = '<?php echo isset($settings['uwls_selector']) ? esc_js($settings['uwls_selector']) : '' ?>';
						var file = '<?php echo esc_url($file) ?>';

						if (selector && file) {
							console.log('UWLS DEBUG: Initializing search...');
							
							// Helper xóa dấu tiếng Việt
							var removeAccents = function(str) {
								str = str.toLowerCase();
								str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, "a");
								str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, "e");
								str = str.replace(/ì|í|ị|ỉ|ĩ/g, "i");
								str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, "o");
								str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, "u");
								str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, "y");
								str = str.replace(/đ/g, "d");
								return str;
							};

							console.log('UWLS DEBUG: Selector: ', selector);
							console.log('UWLS DEBUG: Data file: ', file);

							$.getJSON(file, function(data) {
								console.log('UWLS DEBUG: Data loaded successfully:', data);
								
								// Gộp tất cả các loại post type lại thành một mảng duy nhất để search
								var allData = [];
								Object.keys(data).forEach(function(key) {
									if (Array.isArray(data[key])) {
										allData = allData.concat(data[key]);
									}
								});

								console.log('UWLS DEBUG: Total items for search:', allData.length);

								$(selector).each(function() {
									var autocompleteInstance = $(this).autocomplete({
										delay: 10,
										source: function(request, response) {
											console.log('UWLS DEBUG: Searching for:', request.term);
											var termNormalized = removeAccents(request.term);
											var words = termNormalized.split(' ').filter(w => w !== '');
											
											var results = allData.filter(item => {
												var titleNormalized = removeAccents(item.title);
												var skuNormalized = removeAccents(item.sku || '');
												return words.every(word => titleNormalized.includes(word) || skuNormalized.includes(word));
											});
											console.log('UWLS DEBUG: Results found:', results.length);
											response(results.slice(0, 10));
										},
										select: function(event, ui) {
											window.open(ui.item.url, '_blank');
											return false;
										},
										open: function(event, ui) {
											var searchForm = $('ul.nav.header-bottom-nav.nav-center.mobile-nav.nav-prompts-overlay li.header-search-form');
											if (searchForm.length > 0 && $(window).width() <= 1024) {
												$(this).data("ui-autocomplete").menu.element.css("left", searchForm.offset().left);
											}
										}
									}).data("ui-autocomplete");

									// Căn chỉnh width luôn bằng với thanh search
									autocompleteInstance._resizeMenu = function() {
										var ul = this.menu.element;
										ul.outerWidth(this.element.outerWidth());
									};

									// Ngăn chặn đóng popup khi người dùng right-click hoặc tương tác (đưa chuột vào popup)
									autocompleteInstance.menu.element.on('mouseenter', function() {
										autocompleteInstance.uwlsPreventClose = true;
									}).on('mouseleave', function() {
										autocompleteInstance.uwlsPreventClose = false;
									});

									var originalClose = autocompleteInstance.close;
									autocompleteInstance.close = function(event) {
										if (this.uwlsPreventClose) {
											return; // Bỏ qua việc đóng menu
										}
										originalClose.apply(this, arguments);
									};

									autocompleteInstance._renderItem = function(ul, item) {
										var words = this.element.val().split(' ').filter(w => w !== '');
										var displayTitle = item.title;
										var displaySku = item.sku ? item.sku : '';
										words.forEach(word => {
											if (word) {
												displayTitle = displayTitle.replace(new RegExp("(" + word + ")", "gi"), "<mark>$1</mark>");
												if (displaySku) {
													displaySku = displaySku.replace(new RegExp("(" + word + ")", "gi"), "<mark>$1</mark>");
												}
											}
										});

										var skuHTML = displaySku ? `<div class="uwls-sku">SKU: ${displaySku}</div>` : '';

										var priceHTML = '';
										if (item.sale_price) {
											var regPrice = item.reg_price ? `<span class="uwls-reg-price">${item.reg_price}</span>` : '';
											priceHTML = `<div class="uwls-price-group">${regPrice}<span class="uwls-sale-price">${item.sale_price}</span></div>`;
										}

										var thumbnail = item.thumbnail ? item.thumbnail : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

										return $("<li>")
											.append(`
												<a href="${item.url}" target="_blank" class="uwls-item">
													<div class="uwls-thumb-wrapper">
														<img src="${thumbnail}" class="uwls-thumb">
													</div>
													<div class="uwls-info">
														<div class="uwls-title">${displayTitle}</div>
														${skuHTML}
													</div>
													${priceHTML}
												</a>
											`)
											.appendTo(ul);
									};
								});
							});
						}
					});
				</script>
				<style>
					/* jQuery UI Autocomplete Custom Premium Styles */
					.ui-autocomplete {
						z-index: 9999999999 !important;
						background: rgba(255, 255, 255, 0.98) !important;
						backdrop-filter: blur(10px);
						border: 1px solid rgba(0,0,0,0.08) !important;
						border-radius: 12px !important;
						box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.05) !important;
						padding: 8px !important;
						max-height: 500px !important;
						overflow-y: auto !important;
						margin-top: 8px !important;
						box-sizing: border-box !important;
						max-width: 100vw !important;
					}

					.ui-autocomplete::-webkit-scrollbar {
						width: 6px;
					}
					.ui-autocomplete::-webkit-scrollbar-thumb {
						background: #ddd;
						border-radius: 10px;
					}

					.ui-menu-item {
						list-style: none !important;
						margin-bottom: 4px !important;
					}

					.uwls-item {
						display: flex !important;
						align-items: center !important;
						justify-content: space-between !important;
						padding: 10px !important;
						text-decoration: none !important;
						color: #333 !important;
						border-radius: 8px !important;
						box-sizing: border-box !important;
						width: 100% !important;
					}

					.ui-state-active, .ui-widget-content .ui-state-active {
						background: rgba(52, 152, 219, 0.08) !important;
						border: 1px solid rgba(52, 152, 219, 0.2) !important;
						margin: 0 !important;
					}

					.uwls-thumb-wrapper {
						width: 50px;
						height: 50px;
						flex-shrink: 0;
						margin-right: 15px;
						border-radius: 8px;
						overflow: hidden;
						background: #f8f9fa;
						border: 1px solid #eee;
					}

					.uwls-thumb {
						width: 100%;
						height: 100%;
						object-fit: cover;
					}

					.uwls-info {
						flex: 1;
						min-width: 0;
						display: flex;
						flex-direction: column;
						justify-content: center;
					}

					.uwls-title {
						font-size: 15px;
						font-weight: 500;
						color: #2c3e50;
						line-height: 1.4;
						white-space: normal; /* Cho phép xuống dòng */
						word-break: break-word;
					}

					.uwls-sku {
						font-size: 13px;
						color: #7f8c8d;
						margin-top: 4px;
					}

					.uwls-price-group {
						display: flex;
						flex-direction: row;
						align-items: center;
						margin-left: 15px;
						white-space: nowrap;
					}

					.uwls-reg-price {
						font-size: 13px;
						color: #999;
						text-decoration: line-through;
						margin-right: 8px;
						font-weight: 400;
					}

					.uwls-sale-price {
						font-size: 16px;
						font-weight: 700;
						color: #e74c3c;
					}

					mark {
						background: transparent !important;
						color: #3498db !important;
						font-weight: 700;
						border-bottom: 2px solid rgba(52, 152, 219, 0.4);
						padding-bottom: 1px;
					}

					.ui-helper-hidden-accessible {
						display: none !important;
					}

					.ui-menu-item-wrapper {
						padding: 0 !important;
						border: none !important;
						background: transparent !important;
					}

					/* Responsive Mobile */
					@media (max-width: 768px) {
						.uwls-item {
							display: grid !important;
							grid-template-columns: 50px 1fr;
							grid-template-rows: auto auto;
							column-gap: 15px;
							align-items: center !important;
						}
						.uwls-thumb-wrapper {
							grid-column: 1;
							grid-row: 1 / span 2;
							height: 70px;
							margin-right: 0;
						}
						.uwls-info {
							grid-column: 2;
							grid-row: 1;
						}
						.uwls-price-group {
							grid-column: 2;
							grid-row: 2;
							margin-left: 0 !important;
							margin-top: 4px;
						}
					}
				</style>
			<?php
			endif;
		}
	}
}