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
        add_submenu_page(null, 'Edycja produktu Ali', 'Edycja produktu Ali', 'manage_woocommerce', 'ali-edit-product', [$this, 'render_page']);
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

        echo '<div class="wrap">';
        echo '<h1>Edycja produktu Ali #' . esc_html($product_id) . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

        wp_nonce_field('ali_save_product_nonce_' . $product_id);
        echo '<input type="hidden" name="action" value="ali_save_product" />';
        echo '<input type="hidden" name="product_id" value="' . esc_attr($product_id) . '" />';

        echo '<h2>Podstawowe</h2>';
        echo '<table class="form-table" role="presentation">';
        $this->text_input_row('title', 'Tytuł', $product->get_name());
        $this->text_input_row('price', 'Cena', $product->get_regular_price());
        $this->text_input_row('brand', 'Marka', $meta['brand'] ?? '');
        $this->text_input_row('original_link', 'Link AliExpress', $meta['original_link'] ?? '');
        echo '</table>';

        echo '<h2>Zdjęcia</h2>';
        echo '<p><label><input type="radio" name="image_choice" value="image_link" ' . checked(($meta['image_choice'] ?? 'image_link'), 'image_link', false) . ' /> Zwykłe</label> ';
        echo '<label><input type="radio" name="image_choice" value="image_white" ' . checked(($meta['image_choice'] ?? ''), 'image_white', false) . ' /> White</label></p>';
        echo '<table class="form-table" role="presentation">';
        $this->text_input_row('image_link', 'Image link', $meta['image_link'] ?? '');
        $this->text_input_row('image_white', 'Image white', $meta['image_white'] ?? '');
        echo '</table>';

        echo '<h2>Kategorie</h2>';
        $this->text_input_row('product_category', 'Ścieżka kategorii AliExpress', $meta['product_category'] ?? '');
        wp_terms_checklist($product_id, [
            'taxonomy' => 'product_cat',
            'selected_cats' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
            'checked_ontop' => false,
        ]);

        echo '<h2>Dostawa i statystyki</h2>';
        echo '<table class="form-table" role="presentation">';
        $this->text_input_row('ship_from_country', 'Kraj wysyłki', $meta['ship_from_country'] ?? '');
        $this->text_input_row('min_delivery_days', 'Min dni dostawy', $meta['min_delivery_days'] ?? '');
        $this->text_input_row('max_delivery_days', 'Max dni dostawy', $meta['max_delivery_days'] ?? '');
        $this->text_input_row('shipping_fees', 'Koszt dostawy', $meta['shipping_fees'] ?? '');
        $this->text_input_row('product_score', 'Ocena produktu', $meta['product_score'] ?? '');
        $this->text_input_row('review_number', 'Liczba opinii', $meta['review_number'] ?? '');
        $this->text_input_row('order_number', 'Sprzedane sztuki', $meta['order_number'] ?? '');
        $this->text_input_row('store_name', 'Nazwa sklepu', $meta['store_name'] ?? '');
        echo '</table>';

        echo '<h2>Atrybuty produktu</h2>';
        echo '<p><button class="button" type="button" id="ali-select-all">Zaznacz wszystkie</button> <button class="button" type="button" id="ali-unselect-all">Odznacz wszystkie</button></p>';
        echo '<table class="widefat striped"><thead><tr><th>Dodaj</th><th>Nazwa</th><th>Wartość</th></tr></thead><tbody>';
        foreach ($attrs as $i => $attr) {
            echo '<tr>';
            echo '<td><input class="ali-attr-check" type="checkbox" name="attrs[' . esc_attr($i) . '][selected]" value="1" ' . checked(!empty($attr['selected']), true, false) . ' /></td>';
            echo '<td>' . esc_html($attr['name'] ?? '') . '</td>';
            echo '<td>' . esc_html($attr['value'] ?? '') . '</td>';
            echo '</tr>';
            echo '<input type="hidden" name="attrs[' . esc_attr($i) . '][name]" value="' . esc_attr($attr['name'] ?? '') . '" />';
            echo '<input type="hidden" name="attrs[' . esc_attr($i) . '][value]" value="' . esc_attr($attr['value'] ?? '') . '" />';
        }
        echo '</tbody></table>';

        echo '<h2>Opis</h2>';
        wp_editor(
            (string) ($meta['detail'] ?? ''),
            'ali_detail_editor',
            [
                'textarea_name' => 'detail',
                'textarea_rows' => 12,
                'media_buttons' => false,
                'teeny' => true,
            ]
        );
        echo '<p><label><input type="checkbox" name="detail_corrected" value="1" ' . checked(!empty($meta['detail_corrected']), true, false) . ' /> POPRAWIONO OPIS</label></p>';

        submit_button('Zapisz');
        echo '</form></div>';
        ?>
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

        $result = $this->service->save_edited_product($product_id, wp_unslash($_POST));
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect(add_query_arg(['page' => 'ali-edit-product', 'product_id' => $product_id, 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    private function text_input_row($name, $label, $value) {
        echo '<tr><th><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
        echo '</td></tr>';
    }
}
