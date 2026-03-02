<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Admin_Edit_Page {
    private $service;

    public function __construct(Ali_Product_Service $service) {
        $this->service = $service;
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_post_ali_save_product', [$this, 'handle_save_product']);
    }

    public function register_page() {
        add_submenu_page(
            null,
            'Edycja produktu Ali',
            'Edycja produktu Ali',
            'manage_woocommerce',
            'ali-edit-product',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $product = $product_id ? wc_get_product($product_id) : false;

        if (!$product) {
            echo '<div class="wrap"><h1>Edycja produktu Ali</h1><p>Nie znaleziono produktu.</p></div>';
            return;
        }

        $meta = get_post_meta($product_id, '_ali_import_data', true);
        $meta = is_array($meta) ? $meta : [];
        $attrs = isset($meta['attributes']) && is_array($meta['attributes']) ? $meta['attributes'] : [];
        ?>
        <div class="wrap">
            <h1>Edycja produktu Ali #<?php echo esc_html($product_id); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ali_save_product_nonce_' . $product_id); ?>
                <input type="hidden" name="action" value="ali_save_product" />
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />

                <h2>Podstawowe</h2>
                <table class="form-table" role="presentation">
                    <tr><th><label for="title">Tytuł</label></th><td><input class="regular-text" type="text" name="title" id="title" value="<?php echo esc_attr($product->get_name()); ?>" /></td></tr>
                    <tr><th><label for="price">Cena</label></th><td><input class="regular-text" type="text" name="price" id="price" value="<?php echo esc_attr($product->get_regular_price()); ?>" /></td></tr>
                    <tr><th><label for="brand">Marka</label></th><td><input class="regular-text" type="text" name="brand" id="brand" value="<?php echo esc_attr($meta['brand'] ?? ''); ?>" /></td></tr>
                </table>

                <h2>Zdjęcia</h2>
                <p>
                    <label><input type="radio" name="image_choice" value="image_link" <?php checked(($meta['image_choice'] ?? 'image_link'), 'image_link'); ?> /> Zwykłe</label>
                    <label><input type="radio" name="image_choice" value="image_white" <?php checked(($meta['image_choice'] ?? ''), 'image_white'); ?> /> White</label>
                </p>
                <p>Zwykłe: <a href="<?php echo esc_url($meta['image_link'] ?? ''); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($meta['image_link'] ?? '-'); ?></a></p>
                <p>White: <a href="<?php echo esc_url($meta['image_white'] ?? ''); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($meta['image_white'] ?? '-'); ?></a></p>

                <h2>Kategorie</h2>
                <p>AliExpress: <?php echo esc_html($meta['product_category'] ?? '-'); ?></p>
                <?php
                wp_terms_checklist($product_id, [
                    'taxonomy' => 'product_cat',
                    'selected_cats' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
                    'checked_ontop' => false,
                ]);
                ?>

                <h2>Dostawa</h2>
                <p>Kraj wysyłki: <?php echo esc_html($meta['ship_from_country'] ?? '-'); ?></p>
                <p>Czas dostawy: <?php echo esc_html($meta['min_delivery_days'] ?? '-'); ?> - <?php echo esc_html($meta['max_delivery_days'] ?? '-'); ?> dni</p>
                <p>Koszt dostawy: <?php echo esc_html($meta['shipping_fees'] ?? '-'); ?></p>

                <h2>Statystyki</h2>
                <p>Ocena: <?php echo esc_html($meta['product_score'] ?? '-'); ?>/5</p>
                <p>Liczba opinii: <?php echo esc_html($meta['review_number'] ?? '-'); ?></p>
                <p>Sprzedanych sztuk: <?php echo esc_html($meta['order_number'] ?? '-'); ?></p>

                <h2>Atrybuty produktu</h2>
                <p>
                    <button class="button" type="button" id="ali-select-all">Zaznacz wszystkie</button>
                    <button class="button" type="button" id="ali-unselect-all">Odznacz wszystkie</button>
                </p>
                <table class="widefat striped">
                    <thead><tr><th>Dodaj</th><th>Nazwa</th><th>Wartość</th></tr></thead>
                    <tbody>
                    <?php foreach ($attrs as $i => $attr) : ?>
                        <tr>
                            <td><input class="ali-attr-check" type="checkbox" name="attrs[<?php echo esc_attr($i); ?>][selected]" value="1" <?php checked(!empty($attr['selected'])); ?> /></td>
                            <td><?php echo esc_html($attr['name'] ?? ''); ?></td>
                            <td><?php echo esc_html($attr['value'] ?? ''); ?></td>
                        </tr>
                        <input type="hidden" name="attrs[<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($attr['name'] ?? ''); ?>" />
                        <input type="hidden" name="attrs[<?php echo esc_attr($i); ?>][value]" value="<?php echo esc_attr($attr['value'] ?? ''); ?>" />
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>Opis (z API2, po czyszczeniu HTML)</h2>
                <textarea name="detail" rows="8" class="large-text"><?php echo esc_textarea($meta['detail'] ?? ''); ?></textarea>

                <?php submit_button('Zapisz'); ?>
            </form>
        </div>
        <script>
            document.getElementById('ali-select-all')?.addEventListener('click', function(){
                document.querySelectorAll('.ali-attr-check').forEach(function(el){el.checked = true;});
            });
            document.getElementById('ali-unselect-all')?.addEventListener('click', function(){
                document.querySelectorAll('.ali-attr-check').forEach(function(el){el.checked = false;});
            });
        </script>
        <?php
    }

    public function handle_save_product() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$product_id || !current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }
        check_admin_referer('ali_save_product_nonce_' . $product_id);

        $input = wp_unslash($_POST);
        $result = $this->service->save_edited_product($product_id, $input);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'ali-edit-product',
            'product_id' => $product_id,
            'updated' => 1,
        ], admin_url('admin.php')));
        exit;
    }
}
