<?php
/**
 * Plugin Name: GPT PDF Chat
 * Description: Inteligentny chat AI oparty na pliku PDF, który odpowiada na pytania związane z treścią dokumentu.
 * Version: 1.0
 * Author: RLagoo
 * Text Domain: gpt-pdf-chat
 */

if (!defined('ABSPATH')) {
    exit; // Zabezpieczenie przed bezpośrednim dostępem
}

// Definicje stałych
define('GPT_PDF_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GPT_PDF_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GPT_PDF_CHAT_VERSION', '1.0');

// Inkluzja biblioteki PDFParser
require_once GPT_PDF_CHAT_PLUGIN_DIR . 'lib/pdfparser/autoload.php';

// Rejestracja shortcode
add_shortcode('gpt_pdf_chat', 'gpt_pdf_chat_shortcode');

/**
 * Funkcja generująca interfejs chatu.
 */
function gpt_pdf_chat_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Musisz być zalogowany, aby korzystać z chatu.</p>';
    }

    ob_start();
    ?>
    <div id="gpt-chat-box">
        <div id="chat-header">Chat Firmowy</div>
        <div id="chat-window"></div>
        <div id="chat-input">
            <input type="text" id="user-input" placeholder="Zadaj pytanie..." />
            <button id="send-button">Wyślij</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Ładowanie skryptów i stylów
add_action('wp_enqueue_scripts', 'gpt_pdf_chat_enqueue_scripts');

/**
 * Funkcja do ładowania skryptów JavaScript i stylów CSS.
 */
function gpt_pdf_chat_enqueue_scripts() {
    // Styl CSS
    wp_enqueue_style('gpt-pdf-chat-css', GPT_PDF_CHAT_PLUGIN_URL . 'css/styles.css', array(), GPT_PDF_CHAT_VERSION);

    // Skrypt JavaScript
    wp_enqueue_script('gpt-pdf-chat-js', GPT_PDF_CHAT_PLUGIN_URL . 'js/chat.js', array('jquery'), GPT_PDF_CHAT_VERSION, true);

    // Lokalizacja zmiennych dla JS
    wp_localize_script('gpt-pdf-chat-js', 'gptChatData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gpt_pdf_chat_nonce')
    ));
}

// Obsługa AJAX
add_action('wp_ajax_gpt_pdf_chat_response', 'gpt_pdf_chat_response');
// Brak potrzeby dodawania 'nopriv' ponieważ chat jest dostępny tylko dla zalogowanych użytkowników
// add_action('wp_ajax_nopriv_gpt_pdf_chat_response', 'gpt_pdf_chat_response');

/**
 * Funkcja przetwarzająca zapytania AJAX.
 */
function gpt_pdf_chat_response() {
    // Weryfikacja nonce dla bezpieczeństwa
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gpt_pdf_chat_nonce')) {
        wp_send_json_error('Nieprawidłowy token bezpieczeństwa.');
    }

    if (!is_user_logged_in()) {
        wp_send_json_error('Musisz być zalogowany, aby korzystać z chatu.');
    }

    if (isset($_POST['question'])) {
        $question = sanitize_text_field($_POST['question']);

        // Ścieżka do pliku PDF
        $pdf_url = 'https://apk.e-strony.com/wp-content/uploads/2024/10/ADMINISTRACJA.pdf';

        // Pobranie i przetworzenie PDF
        $response = gpt_pdf_chat_get_response($pdf_url, $question);

        if ($response['success']) {
            wp_send_json_success($response['data']);
        } else {
            wp_send_json_error($response['data']);
        }
    } else {
        wp_send_json_error('Brak pytania.');
    }
}

/**
 * Funkcja do przetwarzania PDF i uzyskiwania odpowiedzi z OpenAI.
 *
 * @param string $pdf_url URL do pliku PDF.
 * @param string $question Pytanie użytkownika.
 * @return array Odpowiedź w formacie success/data lub error/data.
 */
function gpt_pdf_chat_get_response($pdf_url, $question) {
    // Pobranie zawartości PDF
    $pdf_content = gpt_pdf_chat_fetch_pdf($pdf_url);
    if (!$pdf_content['success']) {
        return $pdf_content;
    }

    $text = $pdf_content['data'];

    // Pobranie klucza API z ustawień
    $api_key = get_option('gpt_pdf_chat_openai_api_key');
    if (empty($api_key)) {
        return array('success' => false, 'data' => 'Klucz API OpenAI nie jest skonfigurowany.');
    }

    // Przygotowanie promptu
    $prompt = "Poniżej znajduje się treść procedur firmy:\n\n" . $text . "\n\nUżytkownik zadał pytanie: \"$question\"\n\nOdpowiedz na podstawie powyższej treści.";

    // Wywołanie API OpenAI
    $api_response = gpt_pdf_chat_call_openai($prompt, $api_key);

    if ($api_response['success']) {
        return array('success' => true, 'data' => $api_response['data']);
    } else {
        return $api_response;
    }
}

/**
 * Funkcja do pobierania i przetwarzania PDF.
 *
 * @param string $pdf_url URL do pliku PDF.
 * @return array Odpowiedź w formacie success/data lub error/data.
 */
function gpt_pdf_chat_fetch_pdf($pdf_url) {
    // Sprawdzenie, czy tekst PDF jest już zapisany w transients
    $cached_text = get_transient('gpt_pdf_chat_pdf_text');
    if ($cached_text !== false) {
        return array('success' => true, 'data' => $cached_text);
    }

    // Pobranie zawartości PDF
    $pdf_content = @file_get_contents($pdf_url);
    if ($pdf_content === false) {
        return array('success' => false, 'data' => 'Nie można pobrać pliku PDF.');
    }

    // Zapisanie pliku tymczasowego
    $tmp_file = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($tmp_file, $pdf_content);

    // Użycie PDFParser do wyciągnięcia tekstu
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tmp_file);
        $text = $pdf->getText();
    } catch (Exception $e) {
        unlink($tmp_file);
        return array('success' => false, 'data' => 'Błąd podczas przetwarzania pliku PDF.');
    }

    // Usunięcie pliku tymczasowego
    unlink($tmp_file);

    // Zapisanie tekstu PDF w transients na 12 godzin
    set_transient('gpt_pdf_chat_pdf_text', $text, 12 * HOUR_IN_SECONDS);

    return array('success' => true, 'data' => $text);
}

/**
 * Funkcja do komunikacji z API OpenAI.
 *
 * @param string $prompt Tekst promptu.
 * @param string $api_key Klucz API OpenAI.
 * @return array Odpowiedź w formacie success/data lub error/data.
 */
function gpt_pdf_chat_call_openai($prompt, $api_key) {
    $endpoint = 'https://api.openai.com/v1/completions';
    $model = 'text-davinci-003'; // Możesz zmienić na nowszy model, jeśli dostępny

    $data = array(
        'model'       => $model,
        'prompt'      => $prompt,
        'max_tokens'  => 150,
        'temperature' => 0.5,
        'n'           => 1,
        'stop'        => null,
    );

    $args = array(
        'body'        => json_encode($data),
        'headers'     => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'timeout'     => 60,
    );

    $response = wp_remote_post($endpoint, $args);

    if (is_wp_error($response)) {
        return array('success' => false, 'data' => 'Błąd komunikacji z API OpenAI.');
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (isset($result['choices'][0]['text'])) {
        return array('success' => true, 'data' => trim($result['choices'][0]['text']));
    } else {
        return array('success' => false, 'data' => 'Nie udało się uzyskać odpowiedzi od AI.');
    }
}

// Dodanie ustawień wtyczki
add_action('admin_menu', 'gpt_pdf_chat_add_settings_page');
add_action('admin_init', 'gpt_pdf_chat_register_settings');

/**
 * Funkcja dodająca stronę ustawień do panelu admina.
 */
function gpt_pdf_chat_add_settings_page() {
    add_options_page(
        'GPT PDF Chat Settings',
        'GPT PDF Chat',
        'manage_options',
        'gpt-pdf-chat',
        'gpt_pdf_chat_render_settings_page'
    );
}

/**
 * Funkcja rejestrująca ustawienia.
 */
function gpt_pdf_chat_register_settings() {
    register_setting('gpt_pdf_chat_settings_group', 'gpt_pdf_chat_openai_api_key', 'sanitize_text_field');

    add_settings_section(
        'gpt_pdf_chat_main_section',
        'Główne Ustawienia',
        'gpt_pdf_chat_main_section_callback',
        'gpt-pdf-chat'
    );

    add_settings_field(
        'gpt_pdf_chat_openai_api_key',
        'OpenAI API Key',
        'gpt_pdf_chat_openai_api_key_callback',
        'gpt-pdf-chat',
        'gpt_pdf_chat_main_section'
    );
}

/**
 * Callback do sekcji ustawień.
 */
function gpt_pdf_chat_main_section_callback() {
    echo '<p>Wprowadź swój klucz API z OpenAI, aby chat mógł generować odpowiedzi.</p>';
}

/**
 * Callback do pola klucza API.
 */
function gpt_pdf_chat_openai_api_key_callback() {
    $api_key = get_option('gpt_pdf_chat_openai_api_key', '');
    echo '<input type="text" name="gpt_pdf_chat_openai_api_key" value="' . esc_attr($api_key) . '" size="50" />';
}

/**
 * Renderowanie strony ustawień.
 */
function gpt_pdf_chat_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>GPT PDF Chat Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gpt_pdf_chat_settings_group');
            do_settings_sections('gpt-pdf-chat');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
