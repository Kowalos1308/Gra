<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Admin_Moderate_Page {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_pages']);
    }

    public function register_pages() {
        add_menu_page(
            'ALI SUPER WTYKA',
            'ALI SUPER WTYKA',
            'manage_woocommerce',
            'ali-super-wtyka',
            [$this, 'render_page'],
            'dashicons-store',
            56
        );

        add_submenu_page(
            'ali-super-wtyka',
            'Moderuj produkty',
            'Moderuj produkty',
            'manage_woocommerce',
            'ali-super-wtyka',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $status = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        $cat = isset($_GET['product_cat']) ? absint($_GET['product_cat']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $args = [
            'post_type' => 'product',
            'posts_per_page' => 50,
            'post_status' => $status ?: ['draft', 'publish', 'pending', 'private'],
            'meta_query' => [[
                'key' => '_ali_import_data',
                'compare' => 'EXISTS',
            ]],
            's' => $search,
        ];

        if ($cat > 0) {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => [$cat],
            ]];
        }

        if ($date_from || $date_to) {
            $range = ['inclusive' => true];
            if ($date_from) {
                $range['after'] = $date_from;
            }
            if ($date_to) {
                $range['before'] = $date_to;
            }
            $args['date_query'] = [$range];
        }

        $query = new WP_Query($args);
        $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

        echo '<div class="wrap">';
        echo '<h1>Moderuj produkty</h1>';

        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="ali-super-wtyka" />';
        echo '<p>';
        echo '<label>Status: <select name="post_status">';
        echo '<option value="">Wszystkie</option>';
        foreach (['publish' => 'Opublikowane', 'draft' => 'Szkic', 'pending' => 'Oczekujące', 'private' => 'Prywatne'] as $k => $label) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($status, $k, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> ';

        echo '<label>Od: <input type="date" name="date_from" value="' . esc_attr($date_from) . '" /></label> ';
        echo '<label>Do: <input type="date" name="date_to" value="' . esc_attr($date_to) . '" /></label> ';

        echo '<label>Kategoria: <select name="product_cat">';
        echo '<option value="0">Wszystkie</option>';
        if (!is_wp_error($cats)) {
            foreach ($cats as $term) {
                echo '<option value="' . esc_attr($term->term_id) . '" ' . selected($cat, (int) $term->term_id, false) . '>' . esc_html($term->name) . '</option>';
            }
        }
        echo '</select></label> ';

        echo '<label>Szukaj: <input type="search" name="s" value="' . esc_attr($search) . '" /></label> ';
        submit_button('Filtruj', 'secondary', '', false);
        echo '</p>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Tytuł</th><th>Status</th><th>Kategorie</th><th>Tagi</th><th>Atrybuty</th><th>Opis</th><th>Moderacja</th><th>Akcja</th>';
        echo '</tr></thead><tbody>';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $pid = get_the_ID();
                $product = wc_get_product($pid);
                $meta = get_post_meta($pid, '_ali_import_data', true);
                $meta = is_array($meta) ? $meta : [];

                $has_cats = has_term('', 'product_cat', $pid);
                $has_tags = has_term('', 'product_tag', $pid);
                $attrs = get_post_meta($pid, '_product_attributes', true);
                $has_attrs = is_array($attrs) && !empty($attrs);
                $preview = wp_trim_words((string) ($meta['detail'] ?? ''), 20, '...');
                $corrected = !empty($meta['detail_corrected']);

                $edit_url = add_query_arg([
                    'page' => 'ali-edit-product',
                    'product_id' => $pid,
                ], admin_url('admin.php'));

                echo '<tr>';
                echo '<td>' . esc_html($pid) . '</td>';
                echo '<td>' . esc_html(get_the_title()) . '</td>';
                echo '<td>' . $this->badge(get_post_status($pid), '#f0f0f1', '#1d2327') . '</td>';
                echo '<td>' . $this->badge($has_cats ? 'TAK' : 'NIE', $has_cats ? '#d1e7dd' : '#f8d7da', $has_cats ? '#0f5132' : '#842029') . '</td>';
                echo '<td>' . $this->badge($has_tags ? 'TAK' : 'NIE', $has_tags ? '#d1e7dd' : '#f8d7da', $has_tags ? '#0f5132' : '#842029') . '</td>';
                echo '<td>' . $this->badge($has_attrs ? 'TAK' : 'NIE', $has_attrs ? '#d1e7dd' : '#f8d7da', $has_attrs ? '#0f5132' : '#842029') . '</td>';
                echo '<td>' . esc_html($preview ?: '-') . '</td>';
                echo '<td>' . $this->badge($corrected ? 'POPRAWIONO OPIS' : 'brak', $corrected ? '#cfe2ff' : '#fff3cd', $corrected ? '#084298' : '#664d03') . '</td>';
                echo '<td><a class="button" href="' . esc_url($edit_url) . '">Edytuj produkt</a></td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="9">Brak produktów do moderacji.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function badge($text, $bg, $color) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';font-weight:600;">' . esc_html((string) $text) . '</span>';
    }
}
