<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Admin_Edit_Page {
    private $service;
    private $ai_helper;

    public function __construct(Ali_Product_Service $service, Ali_AI_Helper $ai_helper) {
        $this->service = $service;
        $this->ai_helper = $ai_helper;
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
        wp_enqueue_media();
        $current_tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
        $current_tags = is_wp_error($current_tags) ? [] : $current_tags;
        $suggested_tags = [
            'Darmowa dostawa',
            'Wysyłka z Polski',
            'Wysyłka z Europy',
            'Wysyłka z Chin',
            'Szybka dostawa',
            'Dostawa w tydzień',
            'Ponad 1000 sprzedaży',
        ];

        echo '<div class="wrap">';
        echo '<h1>Edycja produktu Ali #' . esc_html($product_id) . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

        wp_nonce_field('ali_save_product_nonce_' . $product_id);
        echo '<input type="hidden" name="action" value="ali_save_product" />';
        echo '<input type="hidden" name="product_id" value="' . esc_attr($product_id) . '" />';

        $this->ai_helper->render_button($product_id);

        echo '<h2>Podstawowe</h2>';
        echo '<table class="form-table" role="presentation">';
        $this->text_input_row('title', 'Tytuł', $product->get_name());
        $this->text_input_row('price', 'Cena', $product->get_regular_price());
        $this->status_row('product_status', 'Status', $product->get_status());
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

        echo '<p><strong>Podgląd:</strong></p>';
        if (!empty($meta['image_link'])) {
            echo '<img src="' . esc_url($meta['image_link']) . '" alt="image_link" style="max-width:180px;height:auto;margin-right:12px;border:1px solid #dcdcde;padding:4px;background:#fff;" />';
        }
        if (!empty($meta['image_white'])) {
            echo '<img src="' . esc_url($meta['image_white']) . '" alt="image_white" style="max-width:180px;height:auto;border:1px solid #dcdcde;padding:4px;background:#fff;" />';
        }

        $featured_id = $product->get_image_id();
        $featured_src = $featured_id ? wp_get_attachment_image_url($featured_id, 'medium') : '';
        echo '<h3>Obrazek produktu</h3>';
        echo '<input type="hidden" name="featured_image_id" id="featured_image_id" value="' . esc_attr((string) $featured_id) . '" />';
        echo '<div id="ali-featured-preview" style="margin:8px 0;">';
        if ($featured_src) {
            echo '<img src="' . esc_url($featured_src) . '" style="max-width:180px;height:auto;border:1px solid #dcdcde;padding:4px;background:#fff;" />';
        }
        echo '</div>';
        echo '<p><a class="button" href="#" id="ali-choose-featured">Ustaw obrazek produktu</a> <a class="button" href="#" id="ali-remove-featured">Usuń obrazek produktu</a></p>';

        echo '<h2>Kategorie</h2>';
        $this->text_input_row('product_category', 'Ścieżka kategorii AliExpress', $meta['product_category'] ?? '');
        wp_terms_checklist($product_id, [
            'taxonomy' => 'product_cat',
            'selected_cats' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
            'checked_ontop' => false,
        ]);
        echo '<h3>Tagi</h3>';
        echo '<p>Proponowane / często używane (max 5):</p>';
        foreach ($suggested_tags as $tag) {
            echo '<label style="display:inline-block;margin-right:10px;margin-bottom:6px;">';
            echo '<input class="ali-suggested-tag" type="checkbox" name="selected_tags[]" value="' . esc_attr($tag) . '" ' . checked(in_array($tag, $current_tags, true), true, false) . ' /> ' . esc_html($tag);
            echo '</label>';
        }
        echo '<p><label>Dodatkowe tagi (po przecinku): <input type="text" class="regular-text" name="extra_tags" value="" /></label></p>';

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
            jQuery(function($){
                var frame;
                $('#ali-choose-featured').on('click', function(e){
                    e.preventDefault();
                    if (typeof wp === 'undefined' || !wp.media) {
                        alert('Media library niedostępna.');
                        return;
                    }
                    if (frame) {
                        frame.open();
                        return;
                    }
                    frame = wp.media({
                        title: 'Ustaw obrazek produktu',
                        button: { text: 'Ustaw obrazek produktu' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#featured_image_id').val(attachment.id);
                        $('#ali-featured-preview').html('<img src="' + attachment.url + '" style="max-width:180px;height:auto;border:1px solid #dcdcde;padding:4px;background:#fff;" />');
                    });
                    frame.open();
                });

                $('#ali-remove-featured').on('click', function(e){
                    e.preventDefault();
                    $('#featured_image_id').val('');
                    $('#ali-featured-preview').html('');
                });
            });
            document.querySelectorAll('.ali-suggested-tag').forEach(function(box){
                box.addEventListener('change', function(){
                    var checked = document.querySelectorAll('.ali-suggested-tag:checked');
                    if (checked.length > 5) {
                        this.checked = false;
                        alert('Możesz wybrać maksymalnie 5 proponowanych tagów.');
                    }
                });
            });
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

    private function status_row($name, $label, $value) {
        $statuses = [
            'draft' => 'Szkic',
            'publish' => 'Opublikowany',
            'pending' => 'Oczekuje na review',
            'private' => 'Prywatny',
        ];
        echo '<tr><th><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach ($statuses as $key => $text) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</select></td></tr>';
    }
}
