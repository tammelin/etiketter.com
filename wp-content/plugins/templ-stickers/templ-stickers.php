<?php
/**
 * Plugin Name: Templ Stickers
 * Description: Sticker generator for WordPress.
 * Update URI: null
 * Version: 1.0
 * Author: Templ
 * Author URI: https://templ.io
 * Text Domain: templ-stickers
 * Domain Path: /languages
 */

// Define the plugin directory
define('TEMPL_STICKERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEMPL_STICKERS_PLUGIN_URL', plugin_dir_url(__FILE__));

class Templ_Stickers {

    public function __construct() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'rewrite_rule']);
        add_shortcode('templ-stickers', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_scripts']);
        add_action('wp_head', [$this, 'print_i18n_inline_script']);
        add_filter('script_loader_tag', [$this, 'script_loader_tag'], 10, 3);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('use_block_editor_for_post', [$this, 'disable_gutenberg'], 10, 2);
        // WooCommerce cart item meta hooks
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'get_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'custom_cart_item_thumbnail'], 10, 3);
        add_filter('woocommerce_store_api_cart_item_images', [$this, 'custom_block_cart_item_images'], 10, 3);

        // WooCommerce order hooks (admin + frontend)
        add_action('woocommerce_order_item_meta_start', [$this, 'custom_order_item_thumbnail_frontend'], 10, 3);
        add_filter('woocommerce_admin_order_item_thumbnail', [$this, 'custom_admin_order_item_thumbnail'], 10, 3);
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'custom_order_item_meta_key'], 10, 3);
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'custom_order_item_meta_value'], 10, 3);


        // Link order to sticker posts when order is created
        add_action('woocommerce_new_order', [$this, 'link_order_to_stickers']);

        // Daily cron to clean up abandoned sticker posts
        add_action('templ_stickers_cleanup', [$this, 'cleanup_abandoned_stickers']);
        if (!wp_next_scheduled('templ_stickers_cleanup')) {
            wp_schedule_event(time(), 'daily', 'templ_stickers_cleanup');
        }

        // Admin meta box for sticker preview
        add_action('add_meta_boxes', [$this, 'add_sticker_preview_meta_box']);
        add_action('admin_footer', [$this, 'sticker_admin_scripts']);

        // Admin columns for sticker list table
        add_filter('manage_sticker_posts_columns', [$this, 'add_sticker_admin_columns']);
        add_action('manage_sticker_posts_custom_column', [$this, 'render_sticker_admin_column'], 10, 2);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('templ-stickers', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function rewrite_rule(): void {
        add_rewrite_rule(
            '^etiketter/.*',
            'index.php?pagename=etiketter',
            'top'
        );
    }

    // The shortcode will output the Vue app container
    public function shortcode(): string {
        wp_enqueue_script('templ-stickers');
        wp_enqueue_style('templ-stickers');
        return '<div id="app"></div>';
    }

    public function register_scripts(): void {
        // Find file like index-*.js in vue/dist/assets/ folder
        $files = glob(TEMPL_STICKERS_PLUGIN_DIR . '/vue/dist/assets/index-*.js');
        foreach($files as $file) {
            $file_url = str_replace(TEMPL_STICKERS_PLUGIN_DIR, TEMPL_STICKERS_PLUGIN_URL, $file);
            wp_register_script('templ-stickers', $file_url, [], null, true);
        }
        // Find file like index-*.css in vue/dist/assets/ folder
        $files = glob(TEMPL_STICKERS_PLUGIN_DIR . '/vue/dist/assets/index-*.css');
        foreach($files as $file) {
            $file_url = str_replace(TEMPL_STICKERS_PLUGIN_DIR, TEMPL_STICKERS_PLUGIN_URL, $file);
            wp_register_style('templ-stickers', $file_url, [], null);
        }

    }

    private function get_font_css(): string {
        $fonts_dir = TEMPL_STICKERS_PLUGIN_DIR . 'fonts/';
        $faces = [
            [
                'family' => 'Ubuntu',
                'weight' => 'normal',
                'style'  => 'normal',
                'file'   => 'Ubuntu/Ubuntu-Regular.ttf',
                'format' => 'truetype',
            ],
            [
                'family' => 'Ubuntu',
                'weight' => 'bold',
                'style'  => 'normal',
                'file'   => 'Ubuntu/Ubuntu-Bold.ttf',
                'format' => 'truetype',
            ],
            [
                'family' => 'Ubuntu',
                'weight' => 'normal',
                'style'  => 'italic',
                'file'   => 'Ubuntu/Ubuntu-Italic.ttf',
                'format' => 'truetype',
            ],
            [
                'family' => 'Ubuntu',
                'weight' => 'bold',
                'style'  => 'italic',
                'file'   => 'Ubuntu/Ubuntu-BoldItalic.ttf',
                'format' => 'truetype',
            ],
            [
                'family' => 'Cataneo',
                'weight' => 'normal',
                'style'  => 'italic',
                'file'   => 'Cataneo/Cataneo-Regular.otf',
                'format' => 'opentype',
            ],
        ];

        $css = '';
        foreach ($faces as $face) {
            $path = $fonts_dir . $face['file'];
            if (!file_exists($path)) continue;
            $data = base64_encode(file_get_contents($path));
            $css .= "@font-face{"
                . "font-family:'{$face['family']}';"
                . "font-weight:{$face['weight']};"
                . "font-style:{$face['style']};"
                . "src:url('data:font/{$face['format']};base64,{$data}') format('{$face['format']}');"
                . "}";
        }
        return $css;
    }

    public function print_i18n_inline_script(): void {
        $i18n = [
            'chooseSizeAndColor'   => __('Size and color', 'templ-stickers'),
            'chooseSymbol'         => __('Select symbol', 'templ-stickers'),
            'addText'              => __('Add text', 'templ-stickers'),
            'row'                  => __('Line', 'templ-stickers'),
            'sansSerif'            => __('Sans-serif', 'templ-stickers'),
            'serif'                => __('Serif', 'templ-stickers'),
            'italic'               => __('Italic', 'templ-stickers'),
            'bold'                 => __('Bold', 'templ-stickers'),
            'textAlignment'        => __('Text alignment', 'templ-stickers'),
            'alignLeft'            => __('Left', 'templ-stickers'),
            'alignCenter'          => __('Center', 'templ-stickers'),
            'alignRight'           => __('Right', 'templ-stickers'),
            'save'                 => __('Save', 'templ-stickers'),
            'saveAndAddToCart'     => __('Save and add to cart', 'templ-stickers'),
            'saveAsNewAndAddToCart'=> __('Save as new and add to cart', 'templ-stickers'),
            'createNew'            => __('Create new sticker', 'templ-stickers'),
            'confirmCreateNew'     => __('Do you want to create a new sticker? Unsaved changes will be lost.', 'templ-stickers'),
            'maxChars'             => __('Max %s characters', 'templ-stickers'),
            'columnText'           => __('Text', 'templ-stickers'),
            'columnStraight'       => __('Straight', 'templ-stickers'),
            'columnItalic'         => __('Italic', 'templ-stickers'),
            'columnCursive'        => __('Cursive', 'templ-stickers'),
            'columnBold'           => __('Bold', 'templ-stickers'),
            'shapeRectangular'     => __('Rectangular', 'templ-stickers'),
            'shapeOval'            => __('Oval', 'templ-stickers'),
            'shapeRound'           => __('Round', 'templ-stickers'),
        ];
        $font_css = $this->get_font_css();
        echo '<script>window.templStickersI18n = ' . wp_json_encode($i18n) . ';window.templStickersFontCSS = ' . wp_json_encode($font_css) . ';</script>' . "\n";
    }

    // Add type="module" to script tag
    public function script_loader_tag(string $tag, string $handle, string $src): string {
        if($handle == 'templ-stickers') {
            $tag = '<script src="' . esc_url( $src ) . '" type="module"></script>';
        }
        return $tag;
    }

    public function disable_gutenberg(bool $use_block_editor, WP_Post $post): bool {
        if ($post->post_type === 'sticker') {
            return false;
        }
        return $use_block_editor;
    }

    function get_form_fields(): array {
        $global_colors = get_field('colors', 'option') ?: [];
        $sizes = get_field('sizes', 'option') ?: [];

        // If a size has no per-size colors defined, fall back to global colors
        // Also attach quantity rules from the product-quantity plugin
        foreach ($sizes as &$size) {
            if (empty($size['colors'])) {
                $size['colors'] = $global_colors;
            }
            $pid = isset($size['product']) ? (int) $size['product'] : 0;
            $qty_type = get_post_meta($pid, 'wpq_simpleproduct_quantitytype_select', true);
            if ($qty_type === 'step_quantity') {
                $qty_min  = (int) get_post_meta($pid, 'wpq_simpleproduct_min_input_step', true) ?: 1;
                $qty_max  = (int) get_post_meta($pid, 'wpq_simpleproduct_max_input_step', true) ?: '';
            } else {
                $qty_min  = (int) get_post_meta($pid, 'wpq_simpleproduct_min_input_default', true) ?: 1;
                $qty_max  = (int) get_post_meta($pid, 'wpq_simpleproduct_max_input_default', true) ?: '';
            }
            $size['quantity'] = [
                'min'  => $qty_min,
                'max'  => $qty_max,
                'step' => (int) get_post_meta($pid, 'wpq_simpleproduct_step_input', true) ?: 1,
            ];
            $wc_product = $pid ? wc_get_product($pid) : null;
            $size['unit_price'] = $wc_product ? (float) $wc_product->get_price('edit') : 0;
        }
        unset($size);

        $fields = [
            'sizes'        => $sizes,
            'colors'       => $global_colors,
            'symbols'      => get_field('symbols', 'option'),
            'symbol_price' => (float) get_field('symbol_price', 'option'),
        ];
        return $fields;
    }

    function post_submit_sticker(WP_REST_Request $request): array {
        $data = $request->get_json_params();
        $uuid = wp_generate_uuid4();

        // Extract SVG from data (don't store it in post_content)
        $svg = isset($data['svg']) ? $data['svg'] : null;
        unset($data['svg']);

        $post_id = wp_insert_post([
            'post_title' => __('Sticker Order', 'templ-stickers'),
            'post_content' => json_encode($data),
            'post_status' => 'publish',
            'post_type' => 'sticker',
            'meta_input' => [
                'sticker_uuid' => $uuid,
            ]
        ]);

        if (is_wp_error($post_id)) {
            return [
                'status' => 'error',
                'message' => $post_id->get_error_message(),
            ];
        }

        // Store SVG and generate PNG if SVG was provided
        if ($svg) {
            update_post_meta($post_id, '_sticker_svg', $svg);
            $png_url = $this->generate_sticker_png($svg, $uuid);
            if ($png_url) {
                update_post_meta($post_id, '_sticker_png_url', $png_url);
            }
        }

        // Get product ID from selected size
        $product_id = isset($data['size']['product']) ? (int) $data['size']['product'] : 0;
        return [
            'status' => 'success',
            'data' => $data,
            'post_id' => $post_id,
            'sticker_uuid' => $uuid,
            'product_id' => $product_id,
        ];
    }

    /**
     * Generate PNG from SVG and save to uploads folder
     */
    function generate_sticker_png(string $svg, string $uuid): ?string {
        // Create stickers directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $stickers_dir = $upload_dir['basedir'] . '/stickers';

        if (!file_exists($stickers_dir)) {
            wp_mkdir_p($stickers_dir);
        }

        $png_path = $stickers_dir . '/' . $uuid . '.png';
        $png_url = $upload_dir['baseurl'] . '/stickers/' . $uuid . '.png';

        // Try Imagick first
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->readImageBlob($svg);
                $imagick->setImageFormat('png');
                $imagick->writeImage($png_path);
                $imagick->clear();
                $imagick->destroy();
                return $png_url;
            } catch (Exception $e) {
                error_log('Sticker PNG generation failed (Imagick): ' . $e->getMessage());
            }
        }

        // Fallback: try GD with SVG support (requires librsvg)
        // Note: Most PHP GD installations don't support SVG natively
        // In that case, we just store the SVG and skip PNG generation

        return null;
    }

    // Capture sticker_uuid from add-to-cart request and store in cart item
    function add_cart_item_data(array $cart_item_data, int $product_id): array {
        if (isset($_REQUEST['sticker_uuid'])) {
            $cart_item_data['sticker_uuid'] = sanitize_text_field($_REQUEST['sticker_uuid']);
        }
        return $cart_item_data;
    }

    // Display sticker info in cart
    function get_item_data(array $item_data, array $cart_item): array {
        if (isset($cart_item['sticker_uuid'])) {
            $uuid = esc_attr($cart_item['sticker_uuid']);
            $edit_url = esc_url(home_url('/etiketter/?sticker-uuid=' . $uuid));
            $item_data[] = [
                'key'     => $uuid,
                'value'   => $uuid,
                'display' => '<a href="' . $edit_url . '">' . __('Edit sticker', 'templ-stickers') . '</a>',
            ];
        }
        return $item_data;
    }

    // Save sticker_uuid to order item meta on checkout
    function add_order_item_meta($item, $cart_item_key, $values, $order): void {
        if (isset($values['sticker_uuid'])) {
            $item->add_meta_data('sticker_uuid', $values['sticker_uuid']);
        }
    }

    /**
     * Link order ID to sticker posts when order is created
     */
    function link_order_to_stickers(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $uuid = $item->get_meta('sticker_uuid');
            if (!$uuid) {
                continue;
            }

            $sticker = $this->get_sticker_by_uuid($uuid);
            if (!$sticker) {
                continue;
            }

            add_post_meta($sticker->ID, '_order_id', $order->get_id());
        }
    }

    /**
     * Delete abandoned sticker posts (no _order_id, older than 7 days)
     */
    function cleanup_abandoned_stickers(): void {
        $posts = get_posts([
            'post_type'      => 'sticker',
            'posts_per_page' => -1,
            'date_query'     => [['before' => '7 days ago']],
            'meta_query'     => [
                [
                    'key'     => '_order_id',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);

        foreach ($posts as $post_id) {
            wp_delete_post($post_id, true);
        }
    }

    /**
     * Get sticker post by UUID
     */
    function get_sticker_by_uuid(string $uuid): ?WP_Post {
        $posts = get_posts([
            'post_type' => 'sticker',
            'meta_key' => 'sticker_uuid',
            'meta_value' => $uuid,
            'meta_compare' => '=',
            'posts_per_page' => 1,
        ]);
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Custom cart item thumbnail showing sticker preview
     */
    function custom_cart_item_thumbnail(string $thumbnail, array $cart_item, string $cart_item_key): string {
        if (isset($cart_item['sticker_uuid'])) {
            $sticker = $this->get_sticker_by_uuid($cart_item['sticker_uuid']);

            if ($sticker) {
                $png_url = get_post_meta($sticker->ID, '_sticker_png_url', true);
                // if ($png_url) {
                //     return '<img src="' . esc_url($png_url) . '" alt="Sticker preview" class="sticker-cart-thumbnail" />';
                // }

                // Fallback to SVG if PNG not available
                $svg = get_post_meta($sticker->ID, '_sticker_svg', true);
                if ($svg) {
                    return '<div class="sticker-cart-thumbnail-svg" style="max-width:100px;">' . $svg . '</div>';
                }
            }
        }
        return $thumbnail;
    }

    /**
     * Custom cart item images for WooCommerce block cart/checkout (WC 9.6+)
     */
    function custom_block_cart_item_images(array $product_images, array $cart_item, string $cart_item_key): array {
        if (isset($cart_item['sticker_uuid'])) {
            $uuid = $cart_item['sticker_uuid'];
            $sticker = $this->get_sticker_by_uuid($uuid);

            if ($sticker) {
                $image_url = rest_url('templ-stickers/v1/sticker/' . $uuid . '/svg');

                return [
                    (object) [
                        'id'        => 0,
                        'src'       => $image_url,
                        'thumbnail' => $image_url,
                        'srcset'    => '',
                        'sizes'     => '',
                        'name'      => __('Sticker preview', 'templ-stickers'),
                        'alt'       => __('Sticker preview', 'templ-stickers'),
                    ]
                ];
            }
        }
        return $product_images;
    }

    /**
     * Render sticker thumbnail on frontend (My Account > View Order)
     */
    function custom_order_item_thumbnail_frontend(int $_item_id, WC_Order_Item $item, WC_Order $_order): void {
        if (is_admin()) {
            return;
        }

        $uuid = $item->get_meta('sticker_uuid');
        if (!$uuid) {
            return;
        }

        $sticker = $this->get_sticker_by_uuid($uuid);
        if (!$sticker) {
            return;
        }

        $svg_url = rest_url('templ-stickers/v1/sticker/' . $uuid . '/svg');
        echo '<img src="' . esc_url($svg_url) . '" alt="' . esc_attr__('Sticker preview', 'templ-stickers') . '" style="max-width:80px; display:block; margin-bottom:8px;" />';
    }

    /**
     * Custom thumbnail for order line items in WP Admin
     */
    function custom_admin_order_item_thumbnail(string $image, int $item_id, WC_Order_Item $item): string {
        $uuid = $item->get_meta('sticker_uuid');
        if (!$uuid) {
            return $image;
        }

        $sticker = $this->get_sticker_by_uuid($uuid);
        if (!$sticker) {
            return $image;
        }

        $svg_url = rest_url('templ-stickers/v1/sticker/' . $uuid . '/svg');
        return '<img src="' . esc_url($svg_url) . '" alt="' . esc_attr__('Sticker preview', 'templ-stickers') . '" style="max-width:80px;" />';
    }

    /**
     * Rename sticker_uuid meta key to "Sticker" in WP Admin order display
     */
    function custom_order_item_meta_key(string $key, WC_Meta_Data $meta, WC_Order_Item $item): string {
        if ($meta->key === 'sticker_uuid') {
            return __('Sticker', 'templ-stickers');
        }
        return $key;
    }

    /**
     * Make sticker_uuid meta value a link to the sticker post edit page in WP Admin
     */
    function custom_order_item_meta_value(string $value, WC_Meta_Data $meta, WC_Order_Item $item): string {
        // This hook is used in WP Admin as well as My Account, so we need to check that we are in WP Admin before modifying the output
        // TODO? Make it possible for customers to create a copy of the sticker
        if (!is_admin()) {
            return $value;
        }
        if ($meta->key !== 'sticker_uuid') {
            return $value;
        }
        $sticker = $this->get_sticker_by_uuid($value);
        if(!$sticker) {
            return $value;
        }
        $edit_url = get_edit_post_link($sticker->ID);
        return '<a href="' . esc_url($edit_url) . '">' . esc_html($value) . '</a>';
    }

    /**
     * Add meta box for sticker preview in admin
     */
    function add_sticker_preview_meta_box(): void {
        add_meta_box(
            'sticker_preview',
            __('Sticker Preview', 'templ-stickers'),
            [$this, 'render_sticker_preview_meta_box'],
            'sticker',
            'normal',
            'high'
        );
    }

    /**
     * Render sticker preview meta box content
     */
    function render_sticker_preview_meta_box(WP_Post $post): void {
        $svg = get_post_meta($post->ID, '_sticker_svg', true);
        $uuid = get_post_meta($post->ID, 'sticker_uuid', true);

        if ($svg) {
            echo '<div class="sticker-svg-preview" style="background:#f0f0f0; padding:20px; text-align:center; margin-bottom:15px;">';
            echo '<div style="display:inline-block; background:white; padding:10px;">' . $svg . '</div>';
            echo '</div>';
            echo '<button type="button" class="button" id="download-sticker-svg" data-uuid="' . esc_attr($uuid) . '">' . esc_html__('Download SVG', 'templ-stickers') . '</button>';
        } else {
            echo '<p>' . esc_html__('No SVG preview available for this sticker.', 'templ-stickers') . '</p>';
        }
    }

    /**
     * Admin scripts for sticker SVG download
     */
    function sticker_admin_scripts(): void {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'sticker') {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var downloadBtn = document.getElementById('download-sticker-svg');
                if (downloadBtn) {
                    downloadBtn.addEventListener('click', function() {
                        var svgElement = document.querySelector('.sticker-svg-preview svg');
                        if (svgElement) {
                            var svgData = new XMLSerializer().serializeToString(svgElement);
                            var blob = new Blob([svgData], {type: 'image/svg+xml'});
                            var url = URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = 'sticker-' + this.dataset.uuid + '.svg';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                        }
                    });
                }
            });
            </script>
            <?php
        }
    }

    /**
     * Add custom columns to sticker list table
     */
    function add_sticker_admin_columns(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns['sticker_thumbnail'] = __('Preview', 'templ-stickers');
            }
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['order_id'] = __('Order', 'templ-stickers');
            }
        }
        return $new_columns;
    }

    /**
     * Render custom column content for sticker list table
     */
    function render_sticker_admin_column(string $column, int $post_id): void {
        if ($column === 'sticker_thumbnail') {
            $uuid = get_post_meta($post_id, 'sticker_uuid', true);
            if ($uuid) {
                $svg_url = rest_url('templ-stickers/v1/sticker/' . $uuid . '/svg');
                echo '<img src="' . esc_url($svg_url) . '" alt="' . esc_attr__('Sticker preview', 'templ-stickers') . '" style="width:50px;height:50px;object-fit:contain;" />';
            } else {
                echo '—';
            }
            return;
        }

        if ($column !== 'order_id') {
            return;
        }

        $order_ids = get_post_meta($post_id, '_order_id');
        if (empty($order_ids)) {
            echo '—';
            return;
        }

        $links = [];
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $edit_url = $order->get_edit_order_url();
                $links[] = '<a href="' . esc_url($edit_url) . '">#' . esc_html($order_id) . '</a>';
            } else {
                $links[] = '#' . esc_html($order_id);
            }
        }
        echo implode(', ', $links);
    }

    public function put_update_sticker(WP_REST_Request $request): array {
        $uuid = $request->get_param('uuid') ?? '';
        $sticker = $this->get_sticker_by_uuid($uuid);

        if (!$sticker) {
            return [
                'status' => 'error',
                'message' => 'Sticker not found',
            ];
        }

        $data = $request->get_json_params();
        $svg = isset($data['svg']) ? $data['svg'] : null;
        unset($data['svg']);

        wp_update_post([
            'ID' => $sticker->ID,
            'post_content' => json_encode($data),
        ]);

        if ($svg) {
            update_post_meta($sticker->ID, '_sticker_svg', $svg);
            $png_url = $this->generate_sticker_png($svg, $uuid);
            if ($png_url) {
                update_post_meta($sticker->ID, '_sticker_png_url', $png_url);
            }
        }

        return [
            'status' => 'success',
            'data' => $data,
            'post_id' => $sticker->ID,
            'sticker_uuid' => $uuid,
        ];
    }

    public function get_sticker_svg(WP_REST_Request $request): void {
        $uuid = $request->get_param('uuid') ?? '';
        $sticker = $this->get_sticker_by_uuid($uuid);

        if (!$sticker) {
            status_header(404);
            exit;
        }

        $svg = get_post_meta($sticker->ID, '_sticker_svg', true);
        if (!$svg) {
            status_header(404);
            exit;
        }

        header('Content-Type: image/svg+xml');
        echo $svg;
        exit;
    }

    public function get_sticker(WP_REST_Request $request): array {
        $uuid = $request->get_param('uuid') ?? '';
        $posts = get_posts([
            'post_type' => 'sticker',
            'meta_key' => 'sticker_uuid',
            'meta_value' => $uuid,
            'meta_compare' => '=',
            'posts_per_page' => 1,
        ]);
        if (empty($posts)) {
            return [
                'status' => 'error',
                'message' => 'Sticker not found',
            ];
        }
        $post = $posts[0];
        return [
            'status' => 'success',
            'post_id' => $post->ID,
            'data' => json_decode($post->post_content, true),
        ];
    }

    function register_routes(): void {
        register_rest_route('templ-stickers/v1', '/form-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_form_fields'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('templ-stickers/v1', '/submit-sticker', [
            'methods' => 'POST',
            'callback' => [$this, 'post_submit_sticker'],
            'permission_callback' => '__return_true', // TODO: Validate nonce
        ]);
        register_rest_route('templ-stickers/v1', '/sticker/(?P<uuid>[^/]+)/svg', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sticker_svg'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('templ-stickers/v1', '/sticker/(?P<uuid>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_sticker'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'put_update_sticker'],
                'permission_callback' => '__return_true', // TODO: Validate nonce
            ],
        ]);
    }

}
new Templ_Stickers();

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('templ_stickers_cleanup');
});
