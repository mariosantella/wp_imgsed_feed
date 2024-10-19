<?php
/*
Plugin Name: Piokok Feed
Description: Display the latest posts from Piokok profiles - Uses Scrape.do for scraping - No direct API required
Version: 1.0
Author: Mario Santella
*/

// Aggiungi il menu nelle impostazioni
function wp_piokok_feed_menu() {
    add_options_page('Settings wp_piokok_feed', 'wp_piokok_feed', 'manage_options', 'wp_piokok_feed', 'wp_piokok_feed_page');
}

function wp_piokok_feed_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access Denied');
    }

    // Recupera il nome utente salvato
    $piokok_username = get_option('piokok_username');

    // Mostra il form
    ?>
    <div class="wrap">
        <h2>WP Piokok Feed Settings</h2>
        <form method="post" action="">
            <label for="piokok_username">Piokok Username:</label>
            <input type="text" id="piokok_username" name="piokok_username" value="<?php echo esc_attr($piokok_username); ?>">
            <input type="submit" name="submit" class="button-primary" value="Save">
        </form>
    </div>
    <?php
}
add_action('admin_menu', 'wp_piokok_feed_menu');

// Salva le impostazioni
function wp_piokok_feed_save_settings() {
    if (isset($_POST['submit'])) {
        $piokok_username = sanitize_text_field($_POST['piokok_username']);

        if (!empty($piokok_username)) {
            update_option('piokok_username', $piokok_username);
        }
    }
}
add_action('admin_init', 'wp_piokok_feed_save_settings');

// Funzione per lo scraping del profilo Piokok usando Scrape.do
function wp_piokok_feed_scrape_profile() {
    $piokok_username = get_option('piokok_username');
    $api_token = "YOUR-scrape.do-API-TOKEN";

    if (empty($piokok_username)) {
        return;
    }

    $url = "https://piokok.com/profile/$piokok_username";
    $scrape_do_url = "http://api.scrape.do?token=$api_token&url=" . urlencode($url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $scrape_do_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($response === false) {
        echo 'Errore cURL: ' . curl_error($ch);
        curl_close($ch);
        return;
    }

    // Trova immagini nella pagina
    if (preg_match_all('/<img[^>]+src=([\'"])(?<url>.+?)\1[^>]* alt=([\'"])(?<alt>.+?)\3[^>]*>/i', $response, $matches)) {
        $image_urls = $matches['url'];
        $alt_texts = $matches['alt'];
        $downloaded_count = 0;

        foreach ($image_urls as $index => $image_url) {
            $alt_text = htmlspecialchars($alt_texts[$index]);

            $parsed_url = parse_url($image_url);
            $path_parts = pathinfo($parsed_url['path']);

            $existing_attachment = get_page_by_title($path_parts['filename'], OBJECT, 'attachment');

            if (!$existing_attachment) {
                if (strpos($path_parts['basename'], 'thumb') !== false) {
                    continue;
                }

                if ($downloaded_count === 0) {
                    $downloaded_count++;
                    continue;
                }

                $image_data = file_get_contents($image_url);

                if ($image_data !== false) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();

                    $upload_dir = wp_upload_dir();
                    $destination_folder = trailingslashit($upload_dir['path']) . 'piokok_feed/';

                    if (!file_exists($destination_folder)) {
                        mkdir($destination_folder, 0777, true);
                    }

                    $file_name = $destination_folder . $path_parts['basename'];

                    if (wp_mkdir_p(dirname($file_name))) {
                        if (file_put_contents($file_name, $image_data) !== false) {
                            $file_type = wp_check_filetype($file_name, null);
                            $attachment = array(
                                'post_mime_type' => $file_type['type'],
                                'post_title' => $path_parts['filename'],
                                'post_content' => '',
                                'post_status' => 'inherit',
                            );

                            $attachment_id = wp_insert_attachment($attachment, $file_name);

                            if (!is_wp_error($attachment_id)) {
                                wp_set_post_categories($attachment_id, array(get_category_by_slug('piokok')->term_id));
                                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

                                echo "Image '$file_name' uploaded to WordPress media.\n";
                                $downloaded_count++;

                                if ($downloaded_count >= 9) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    curl_close($ch);
}
?>