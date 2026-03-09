<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_AI_Helper {
    public function __construct() {
        add_action('wp_ajax_ali_ai_improve_product', [$this, 'handle_ajax_improve_product']);
    }

    public function render_button($product_id) {
        $nonce = wp_create_nonce('ali_ai_edit_product_' . $product_id);
        ?>
        <div style="margin: 16px 0 8px;">
            <button type="button" id="ali-ai-improve-btn" class="button button-primary">POPRAW AI</button>
            <span id="ali-ai-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
            <div id="ali-ai-status" style="margin-top:10px;display:none;"></div>
        </div>
        <script>
            jQuery(function($) {
                function extractAttributes() {
                    const attrs = [];
                    $('input[name^="attrs["]').each(function(){
                        const nameMatch = $(this).attr('name').match(/^attrs\[(\d+)\]\[name\]$/);
                        if (!nameMatch) {
                            return;
                        }
                        const index = nameMatch[1];
                        const name = $(this).val();
                        const value = $('input[name="attrs[' + index + '][value]"]').val();
                        if (name && value) {
                            attrs.push({name: name, value: value});
                        }
                    });
                    return attrs;
                }

                function setEditorContent(content) {
                    if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.get('ali_detail_editor')) {
                        window.tinyMCE.get('ali_detail_editor').setContent(content);
                        return;
                    }
                    $('#ali_detail_editor').val(content);
                }

                function getEditorContent() {
                    if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.get('ali_detail_editor')) {
                        return window.tinyMCE.get('ali_detail_editor').getContent();
                    }
                    return $('#ali_detail_editor').val() || '';
                }

                function applyCategories(data) {
                    const normalized = function(value) {
                        return String(value || '').trim().toLowerCase();
                    };
                    const pathMap = {};

                    $('.categorychecklist input[type="checkbox"]').each(function() {
                        const checkbox = this;
                        const idMatch = checkbox.id ? checkbox.id.match(/in-product_cat-(\d+)/) : null;
                        if (!idMatch) {
                            return;
                        }
                        const termId = idMatch[1];
                        const path = $(checkbox).closest('li').find('> label').text().replace(/\s+/g, ' ').trim();
                        if (path) {
                            pathMap[normalized(path)] = termId;
                        }
                    });

                    const wanted = [];
                    if (Array.isArray(data.category_paths)) {
                        data.category_paths.forEach(function(path) {
                            const key = normalized(path);
                            if (key && pathMap[key]) {
                                wanted.push(pathMap[key]);
                            }
                        });
                    }

                    const fallbackNames = [];
                    if (wanted.length === 0) {
                        if (data.main_category) {
                            fallbackNames.push(data.main_category);
                        }
                        if (data.sub_category) {
                            fallbackNames.push(data.sub_category);
                        }
                        if (fallbackNames.length === 0 && data.category) {
                            String(data.category).split('→').map(function(item){ return item.trim(); }).filter(Boolean).forEach(function(item){ fallbackNames.push(item); });
                        }

                        fallbackNames.forEach(function(name){
                            const target = normalized(name);
                            $('.categorychecklist label').each(function(){
                                const label = normalized($(this).text());
                                if (label === target) {
                                    const checkbox = $(this).find('input[type="checkbox"]');
                                    const idMatch = checkbox.attr('id') ? checkbox.attr('id').match(/in-product_cat-(\d+)/) : null;
                                    if (idMatch) {
                                        wanted.push(idMatch[1]);
                                    }
                                }
                            });
                        });
                    }

                    if (wanted.length > 0) {
                        $('input[name="tax_input[product_cat][]"]').prop('checked', false);
                        wanted.forEach(function(termId) {
                            $('#in-product_cat-' + termId).prop('checked', true);
                        });
                    }
                }

                function applyAttributes(attributes) {
                    if (!Array.isArray(attributes) || attributes.length === 0) {
                        return;
                    }

                    const normalize = function(value) {
                        return String(value || '').trim().toLowerCase();
                    };
                    const used = {};

                    $('input[name^="attrs["]').each(function(){
                        const nameMatch = $(this).attr('name').match(/^attrs\[(\d+)\]\[name\]$/);
                        if (!nameMatch) {
                            return;
                        }

                        const i = parseInt(nameMatch[1], 10);
                        const $name = $(this);
                        const $value = $('input[name="attrs[' + i + '][value]"]');
                        const $row = $name.closest('tr');
                        const currentName = normalize($name.val());
                        const currentValue = normalize($value.val());

                        let found = null;

                        if (attributes[i] && !used[i]) {
                            found = attributes[i];
                            used[i] = true;
                        }

                        if (!found) {
                            attributes.forEach(function(attr, idx) {
                                if (found || used[idx] || !attr) {
                                    return;
                                }
                                const aiName = normalize(attr.name);
                                const aiValue = normalize(attr.value);
                                if ((aiName && aiName === currentName) || (aiValue && aiValue === currentValue)) {
                                    found = attr;
                                    used[idx] = true;
                                }
                            });
                        }

                        if (!found) {
                            return;
                        }

                        const translatedName = String(found.name || '').trim();
                        const translatedValue = String(found.value || '').trim();
                        if (!translatedName && !translatedValue) {
                            return;
                        }

                        if (translatedName) {
                            $name.val(translatedName);
                            $row.find('.ali-attr-name-display').text(translatedName);
                        }
                        if (translatedValue) {
                            $value.val(translatedValue);
                            $row.find('.ali-attr-value-display').text(translatedValue);
                        }
                    });
                }

                $('#ali-ai-improve-btn').on('click', function() {
                    const $btn = $(this);
                    const $spinner = $('#ali-ai-spinner');
                    const $status = $('#ali-ai-status');
                    const payload = {
                        action: 'ali_ai_improve_product',
                        nonce: '<?php echo esc_js($nonce); ?>',
                        product_id: <?php echo (int) $product_id; ?>,
                        title: $('#title').val() || '',
                        category: $('#product_category').val() || '',
                        description: getEditorContent(),
                        attributes: extractAttributes()
                    };

                    if (!payload.title.trim()) {
                        alert('Wypełnij tytuł produktu.');
                        return;
                    }

                    $btn.prop('disabled', true).text('AI pracuje...');
                    $spinner.css('visibility', 'visible').addClass('is-active');
                    $status.hide().empty();

                    $.post(ajaxurl, payload)
                        .done(function(response) {
                            if (!response || !response.success || !response.data) {
                                const message = response && response.data ? response.data : 'Nieznany błąd AI';
                                $status.html('<div class="notice notice-error inline"><p>' + message + '</p></div>').show();
                                return;
                            }

                            const data = response.data;
                            if (data.title) {
                                $('#title').val(data.title);
                            }
                            if (data.description) {
                                setEditorContent(data.description);
                            }
                            applyCategories(data);
                            applyAttributes(data.attributes || []);

                            $status.html('<div class="notice notice-success inline"><p>AI poprawiło dane produktu.</p></div>').show();
                        })
                        .fail(function(xhr) {
                            const msg = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Błąd połączenia z AI.';
                            $status.html('<div class="notice notice-error inline"><p>' + msg + '</p></div>').show();
                        })
                        .always(function() {
                            $btn.prop('disabled', false).text('POPRAW AI');
                            $spinner.css('visibility', 'hidden').removeClass('is-active');
                        });
                });
            });
        </script>
        <?php
    }

    public function handle_ajax_improve_product() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        check_ajax_referer('ali_ai_edit_product_' . $product_id, 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Brak uprawnień.');
        }

        $attributes = [];
        if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
            foreach (wp_unslash($_POST['attributes']) as $attr) {
                if (!is_array($attr)) {
                    continue;
                }
                $name = sanitize_text_field($attr['name'] ?? '');
                $value = sanitize_text_field($attr['value'] ?? '');
                if ($name !== '' && $value !== '') {
                    $attributes[] = [
                        'name' => $name,
                        'value' => $value,
                    ];
                }
            }
        }

        $product_data = [
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'category' => sanitize_text_field(wp_unslash($_POST['category'] ?? '')),
            'attributes' => $attributes,
            'description_preview' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
        ];

        $response = $this->improve_product($product_data, ['categories' => $this->get_categories_hierarchical()]);
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        wp_send_json_success($response);
    }

    private function improve_product($product_data, $store_data) {
        $prompt = $this->build_prompt($product_data, $store_data);
        $response = $this->send_to_groq($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_ai_response($response, $product_data);
    }

    private function get_categories_hierarchical() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($categories)) {
            return [];
        }

        $result = [];
        foreach ($categories as $cat) {
            $path = '';
            if (!empty($cat->parent)) {
                $parent = get_term($cat->parent, 'product_cat');
                if ($parent && !is_wp_error($parent)) {
                    $path = $parent->name . ' → ';
                }
            }
            $result[] = $path . $cat->name;
        }

        return $result;
    }

    private function build_prompt($product_data, $store_data) {
        $attributes_text = "";
        if (!empty($product_data['attributes'])) {
            foreach ($product_data['attributes'] as $attr) {
                $attributes_text .= "- {$attr['name']}: {$attr['value']}\n";
            }
        } else {
            $attributes_text = "Brak atrybutów\n";
        }

        $full_description = wp_strip_all_tags((string) ($product_data['description_preview'] ?? ''));
        $hierarchical_categories = isset($store_data['categories']) && is_array($store_data['categories']) ? $store_data['categories'] : [];

        $prompt = "Jesteś TOP 1 ekspertem SEO i copywriterem w Polsce specjalizującym się w akcesoriach samochodowych z AliExpress.
Twoje zadanie: ZOPTYMALIZUJ dane produktu pod polskie SEO.

PRAWIDŁOWY FORMAT ODPOWIEDZI (NIC WIĘCEJ!):
1. POPRAWIONY TYTUŁ:
[TUTAJ WPISZ TYTUŁ]

2. WYBRANE KATEGORIE (2 POZIOMY):
GŁÓWNA: [TUTAJ WPISZ DOKŁADNĄ NAZWĘ KATEGORII GŁÓWNEJ Z LISTY]
PODKATEGORIA: [TUTAJ WPISZ DOKŁADNĄ NAZWĘ PODKATEGORII Z LISTY]

3. POPRAWIONE ATRYBUTY:
NAZWA_ATRYBUTU: Wartość
NAZWA_ATRYBUTU2: Wartość2

4. ULEPSZONY OPIS:
[TUTAJ WPISZ OPIS W HTML]

==================================================
WAŻNE: ANALIZA KATEGORII Z TWOJEGO SKLEPU
==================================================

Masz dostęp do RZECZYWISTYCH KATEGORII z tego sklepu WordPress. 
ANALIZUJ je uważnie, aby zrozumieć JAKIE PRODUKTY są tutaj sprzedawane.

LISTA KATEGORII Z TWOJEGO SKLEPU (→ oznacza podkategorię):
" . implode("\n", $hierarchical_categories) . "

ANALIZA NA PODSTAWIE KATEGORII:
1. Przestudiuj listę kategorii - zobacz jakie produkty są sprzedawane
2. Zobacz jakie słowa kluczowe są używane w nazwach kategorii
3. Dopasuj produkt do istniejących kategorii
4. Użyj TERMINOLOGII Z KATEGORII w tytule

PRZYKŁAD JAK ANALIZOWAĆ:
Jeśli w kategoriach masz: 'Diagnostyka → Interfejsy diagnostyczne'
To produkt OBD/ELM327 powinien mieć w tytule: 'Interfejs diagnostyczny'

Jeśli w kategoriach masz: 'Multimedia → CarPlay/Android Auto'
To produkt do CarPlay powinien mieć: 'Moduł CarPlay'

Jeśli w kategoriach masz: 'Wyposażenie wnętrza → Dywaniki'
To produkt powinien mieć: 'Dywaniki samochodowe'

==================================================
FORMAT TYTUŁU
==================================================

Struktura: [Rodzaj produktu WEDŁUG KATEGORII] [Marka] [Model] [Specyfikacja]

Jak określić RODZAJ PRODUKTU:
1. Przeanalizuj opis produktu z AliExpress
2. Spójrz na dostępne kategorie w sklepie
3. Znajdź NAJLEPSZE DOPASOWANIE
4. Użyj TERMINU Z KATEGORII

PRZYKŁADY POPRAWNYCH TYTUŁÓW (na podstawie kategorii):
Jeśli kategorie mają: 'Diagnostyka → Interfejsy diagnostyczne'
PRZED: 'Vgate iCar Pro elm327 V2.3 OBD 2 OBD2 Narzędzia diagnostyczne'
PO: 'Interfejs diagnostyczny Vgate iCar Pro V2.3 OBD2'

Jeśli kategorie mają: 'Multimedia → Moduły CarPlay'
PRZED: 'Carplay Android Auto Wireless dla Mercedes'
PO: 'Moduł CarPlay Android Auto bezprzewodowy Mercedes'

Jeśli kategorie mają: 'Wyposażenie wnętrza → Dywaniki'
PRZED: 'Dywaniki samochodowe welurowe dla BMW X5'
PO: 'Dywaniki welurowe BMW X5 X6 X7'

ZASADY TYTUŁU:
- MAX 70 znaków
- BEZ 'AliExpress' i 'najtaniej' w tytule (tylko w opisie!)
- Tłumacz angielskie terminy na polskie
- Usuń powtórzenia
- Zachowaj tylko kluczowe słowa
- UŻYWAJ TERMINOLOGII Z TWOICH KATEGORII

==================================================
DANE WEJŚCIOWE
==================================================

TYTUŁ ORYGINALNY: {$product_data['title']}

KATEGORIA ORYGINALNA: {$product_data['category']}

ATRYBUTY DO POPRAWY:
{$attributes_text}

PEŁNY OPIS Z AliExpress (użyj TEGO do stworzenia nowego opisu):
{$full_description}

==================================================
INSTRUKCJE KROK PO KROKU
==================================================

KROK 1: PRZECZYTAJ LISTĘ KATEGORII
- Zobacz jakie kategorie masz w sklepie
- Zrozum strukturę i terminologię

KROK 2: OKREŚL RODZAJ PRODUKTU
- Na podstawie opisu z AliExpress
- DOPASUJ do istniejących kategorii
- Użyj terminu Z KATEGORII (nie wymyślaj nowego)

KROK 3: STWÓRZ TYTUŁ
- Format: [Rodzaj z kategorii] [Marka] [Model] [Specyfikacja]
- Max 70 znaków
- Naturalny polski język

KROK 4: WYBIERZ KATEGORIE
- Znajdź najlepsze dopasowanie w liście
- Wybierz GŁÓWNĄ i PODKATEGORIĘ
- UŻYJ DOKŁADNEJ NAZWY z listy

KROK 5: POPRAW ATRYBUTY
- Tłumacz chińskie/angielskie nazwy na polski
- Używaj naturalnego języka

KROK 6: NAPISZ OPIS
- HTML, minimum 500 słów
- Użyj struktury poniżej
- SEO: 'AliExpress' 3x, 'najtaniej' 2x

STRUKTURA OPISU:
<h2>[Rodzaj produktu z kategorii] [Marka] - opinie i test</h2>
<p>2-3 akapity wprowadzenia.</p>

<h3>🔧 Specyfikacja techniczna</h3>
<p>Weź wszystkie dane techniczne z oryginalnego opisu.</p>

<h3>⭐ Zalety i korzyści</h3>
<p>Wypunktuj dlaczego warto wybrać akurat ten produkt.</p>

<h3>🚗 Zastosowanie i kompatybilność</h3>
<p>Dla jakich aut/modeli/urządzeń produkt jest przeznaczony.</p>

<h3>📦 Zakup z AliExpress - co warto wiedzieć?</h3>
<p>Informacje o dostawie, gwarancji, zwrotach przy zakupie z AliExpress.</p>

PAMIĘTAJ: Produkt jest z AliExpress - w opisie podkreślaj aspekty: cena vs jakość, czas dostawy, opcje zwrotu, dostępność.

NIE DODAWAJ żadnych 'wezwań do działania', przycisków kup teraz, itp.
Opis ma być czysto informacyjny i SEO.";

        return $prompt;
    }

    private function send_to_groq($prompt) {
        $settings = get_option('ali_super_wtyka_settings', []);
        $settings = is_array($settings) ? $settings : [];

        $api_key = !empty($settings['ai_api_key']) ? (string) $settings['ai_api_key'] : (defined('ALI_GROQ_API_KEY') ? ALI_GROQ_API_KEY : '');
        $model = !empty($settings['ai_model']) ? (string) $settings['ai_model'] : 'llama-3.3-70b-versatile';

        if (empty($api_key)) {
            return new WP_Error('ali_ai_no_api_key', 'Brak klucza API Groq. Uzupełnij Ustawienia -> AI API Key.');
        }

        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Jesteś najlepszym polskim copywriterem SEO. Odpowiadasz TYLKO w podanym formacie, bez dodatkowych komentarzy.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.8,
                'max_tokens' => 3000,
                'top_p' => 0.9,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ali_ai_connection_error', 'Błąd połączenia: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Nieznany błąd';
            return new WP_Error('ali_ai_api_error', 'Błąd API (' . $response_code . '): ' . $error_message);
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('ali_ai_bad_response', 'Nieprawidłowa odpowiedź z API');
        }

        return $data['choices'][0]['message']['content'];
    }

    private function parse_ai_response($ai_response, $original_data) {
        $result = [
            'title' => '',
            'category' => '',
            'main_category' => '',
            'sub_category' => '',
            'category_paths' => [],
            'attributes' => [],
            'description' => '',
        ];

        if (empty($ai_response)) {
            return new WP_Error('ali_ai_empty_response', 'Odpowiedź AI jest pusta');
        }

        $ai_response = str_replace(['```html', '```'], '', $ai_response);
        $lines = explode("\n", $ai_response);
        $current_section = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^#{1,6}\s*/u', '', $line);
            $line = preg_replace('/^\*\*(.+)\*\*$/u', '$1', $line);
            $line = preg_replace('/^__(.+)__$/u', '$1', $line);

            if (strpos($line, 'POPRAWIONY TYTUŁ') !== false) {
                $current_section = 'title';
                $title_line = preg_replace('/^(1\.\s*)?POPRAWIONY\s+TYTUŁ:\s*/i', '', $line);
                if (!empty($title_line) && $title_line !== $line) {
                    $result['title'] = trim($title_line);
                }
                continue;
            }

            if ($current_section === 'title' && empty($result['title'])) {
                $result['title'] = trim($line);
                $current_section = '';
                continue;
            }

            if (preg_match('/^(2\.)?\s*(WYBRANE KATEGORIE|GŁÓWNA:|PODKATEGORIA:)/i', $line)) {
                $current_section = 'category';
                if (preg_match('/GŁÓWNA:\s*(.+)/i', $line, $matches)) {
                    $result['main_category'] = trim($matches[1]);
                }
                if (preg_match('/PODKATEGORIA:\s*(.+)/i', $line, $matches)) {
                    $result['sub_category'] = trim($matches[1]);
                }
                continue;
            }

            if ($current_section === 'category') {
                if (preg_match('/GŁÓWNA:\s*(.+)/i', $line, $matches)) {
                    $result['main_category'] = trim($matches[1]);
                }
                if (preg_match('/PODKATEGORIA:\s*(.+)/i', $line, $matches)) {
                    $result['sub_category'] = trim($matches[1]);
                }
            }

            if (preg_match('/^(3\.)?\s*POPRAWIONE\s+ATRYBUTY(?:\s+PRODUKTU)?\s*:?/i', $line)) {
                $current_section = 'attributes';
                continue;
            }

            if ($current_section === 'attributes') {
                if (preg_match('/^(4\.)?\s*ULEPSZONY OPIS:/i', $line)) {
                    $current_section = 'description';
                    continue;
                }
                if (preg_match('/^[\-•\*]\s*/u', $line)) {
                    $line = preg_replace('/^[\-•\*]\s*/u', '', $line);
                }
                if (preg_match('/^\d+[\.)]\s+/', $line)) {
                    $line = preg_replace('/^\d+[\.)]\s+/', '', $line);
                }
                if (strpos($line, ':') !== false || strpos($line, ' - ') !== false) {
                    $parts = strpos($line, ':') !== false ? explode(':', $line, 2) : explode(' - ', $line, 2);
                    if (count($parts) === 2) {
                        $name = trim((string) $parts[0]);
                        $value = trim((string) $parts[1]);
                        if ($name !== '' && $value !== '') {
                            $result['attributes'][] = [
                                'name' => sanitize_text_field($name),
                                'value' => sanitize_text_field($value),
                            ];
                        }
                    }
                }
                continue;
            }

            if (preg_match('/^(4\.)?\s*ULEPSZONY OPIS:/i', $line)) {
                $current_section = 'description';
                $desc = preg_replace('/^(4\.)?\s*ULEPSZONY OPIS:\s*/i', '', $line);
                if (!empty($desc) && $desc !== $line) {
                    $result['description'] .= $desc . "\n";
                }
                continue;
            }

            if ($current_section === 'description') {
                $result['description'] .= $line . "\n";
            }
        }

        if (!empty($result['main_category']) && !empty($result['sub_category'])) {
            $result['category'] = $result['main_category'] . ' → ' . $result['sub_category'];
        } elseif (!empty($result['main_category'])) {
            $result['category'] = $result['main_category'];
        } elseif (!empty($result['sub_category'])) {
            $result['category'] = $result['sub_category'];
        }

        $result['category_paths'] = $this->resolve_category_paths(
            $result['main_category'],
            $result['sub_category'],
            $result['category']
        );

        if (empty($result['title'])) {
            $result['title'] = $original_data['title'] ?? '';
        }
        if (empty($result['category']) && empty($result['main_category'])) {
            $result['category'] = $original_data['category'] ?? '';
        }
        if (empty($result['attributes'])) {
            $result['attributes'] = $original_data['attributes'] ?? [];
        }
        if (empty($result['description'])) {
            $result['description'] = $original_data['description_preview'] ?? '';
        }

        return $result;
    }


    private function resolve_category_paths($main_category, $sub_category, $category_path) {
        $selected = [];
        $all_paths = $this->get_categories_hierarchical();

        $normalize = static function ($value) {
            return mb_strtolower(trim((string) $value));
        };

        $main_norm = $normalize($main_category);
        $sub_norm = $normalize($sub_category);
        $path_norm = $normalize($category_path);

        foreach ($all_paths as $path) {
            $path_parts = array_map('trim', explode('→', (string) $path));
            $path_parts = array_values(array_filter($path_parts));
            if (empty($path_parts)) {
                continue;
            }

            $path_main = $normalize($path_parts[0]);
            $path_sub = $normalize(end($path_parts));
            $full_path = $normalize($path);

            if ($path_norm !== '' && $full_path === $path_norm) {
                $selected[] = trim((string) $path);
                continue;
            }

            if ($main_norm !== '' && $sub_norm !== '' && $path_main === $main_norm && $path_sub === $sub_norm) {
                $selected[] = trim((string) $path);
                continue;
            }

            if ($sub_norm !== '' && $path_sub === $sub_norm) {
                $selected[] = trim((string) $path);
                continue;
            }

            if ($main_norm !== '' && count($path_parts) === 1 && $path_main === $main_norm) {
                $selected[] = trim((string) $path);
            }
        }

        return array_values(array_unique(array_filter($selected)));
    }

}
