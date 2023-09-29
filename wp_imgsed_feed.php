<?php
/*
Plugin Name: WP Imgsed Feed
Description: Display the latest Instagram posts from Imgsed feed - NO API required - Can be used without Instagram access
Version: 1.0
Author: Mario Santella
*/

// Add menu on settings
function wp_imgsed_feed_menu() {
    add_options_page('Settings wp_imgsed_feed', 'wp_imgsed_feed', 'manage_options', 'wp_imgsed_feed', 'wp_imgsed_feed_page');
}

function wp_imgsed_feed_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access Denied');
    }

    // Retrieve saved Instagram username
    $instagram_username = get_option('instagram_username');

    // Show the form
    ?>
    <div class="wrap">
        <h2>WP Imgsed Feed Settings</h2>
        <form method="post" action="">
            <label for="instagram_username">Instagram Username:</label>
            <input type="text" id="instagram_username" name="instagram_username" value="<?php echo esc_attr($instagram_username); ?>">
            <input type="submit" name="submit" class="button-primary" value="Save">
        </form>
    </div>
    <?php
}
add_action('admin_menu', 'wp_imgsed_feed_menu');

// Action to save settings
function wp_imgsed_feed_save_settings() {
    if (isset($_POST['submit'])) {
        $instagram_username = sanitize_text_field($_POST['instagram_username']);

        if (!empty($instagram_username)) {
            update_option('instagram_username', $instagram_username);
        } else {
            // Add logic here to handle empty field error (optional)
        }
    }
}
add_action('admin_init', 'wp_imgsed_feed_save_settings');

// Function for scraping Instagram
function wp_imgsed_feed_scrape_instagram() {
    $instagram_username = get_option('instagram_username');

    if (empty($instagram_username)) {
        // Handle the case where the Instagram username is not defined
        return;
    }

    // URL of the page to scrape
    $page_url = "https://imgsed.com/$instagram_username";

    // Configure cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $page_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow any redirects
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'); // Set the User-Agent as a browser

    // Execute the request and get the HTML content
    $response = curl_exec($ch);

    // Check for errors
    if ($response === false) {
        echo 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        return;
    }

    // Find all images on the page
    if (preg_match_all('/<img[^>]+src=([\'"])(?<url>.+?)\1[^>]* alt=([\'"])(?<alt>.+?)\3[^>]*>/i', $response, $matches)) {
        $image_urls = $matches['url'];
        $alt_texts = $matches['alt'];
        $downloaded_count = 0;

        // Download each image
        foreach ($image_urls as $index => $image_url) {
            // Extract the "alt" attribute as alternative text
            $alt_text = htmlspecialchars($alt_texts[$index]);

            // Remove parameters from the URL
            $parsed_url = parse_url($image_url);
            $path_parts = pathinfo($parsed_url['path']);

            // Check if the image is already present in the WordPress media
            $existing_attachment = get_page_by_title($path_parts['filename'], OBJECT, 'attachment');

            if (!$existing_attachment) {
                // Check if the image is a thumbnail or contains the word "thumb"
                if (strpos($path_parts['basename'], 'thumb') !== false || strpos($path_parts['filename'], 'thumb') !== false) {
                    continue; // Ignore the image
                }

                // Check if it's the first image (Instagram channel logo)
                if ($downloaded_count === 0) {
                    $downloaded_count++;
                    continue;
                }

                // Download the image
                $image_data = file_get_contents($image_url);

                if ($image_data !== false) {
                    // Create an instance of WP_Filesystem to handle the upload
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();

                    // Destination folder in the media
                    $upload_dir = wp_upload_dir();
                    $destination_folder = trailingslashit($upload_dir['path']) . 'imgsed_feed/';

                    // Create the destination folder if it doesn't exist
                    if (!file_exists($destination_folder)) {
                        mkdir($destination_folder, 0777, true);
                    }

                    // Create the full path for the image
                    $file_name = $destination_folder . $path_parts['basename'];

                    // Save the image in the WordPress media
                    if (wp_mkdir_p(dirname($file_name))) {
                        if (file_put_contents($file_name, $image_data) !== false) {
                            // Prepare image data for insertion into media
                            $file_type = wp_check_filetype($file_name, null);
                            $attachment = array(
                                'post_mime_type' => $file_type['type'],
                                'post_title' => $path_parts['filename'],
                                'post_content' => '',
                                'post_status' => 'inherit',
                            );

                            // Insert the image into media
                            $attachment_id = wp_insert_attachment($attachment, $file_name);

                            if (!is_wp_error($attachment_id)) {
                                // Assign the "imgsed" category to the image
                                wp_set_post_categories($attachment_id, array(get_category_by_slug('imgsed')->term_id));

                                // Update the "alt" attribute of the image with alternative text
                                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

                                // Add the "imgsed" string to the Description field
                                $existing_description = get_post_field('post_content', $attachment_id);
                                $updated_description = 'imgsed ' . $existing_description;
                                wp_update_post(array('ID' => $attachment_id, 'post_content' => $updated_description));

                                echo "Image '$file_name' downloaded and successfully uploaded to WordPress media.\n";
                                $downloaded_count++;

                                // Limit to downloading 9 images
                                if ($downloaded_count >= 9) {
                                    break;
                                }
                            } else {
                                echo "Error inserting into WordPress media: " . $attachment_id->get_error_message() . "\n";
                            }
                        } else {
                            echo "Error saving the image: '$file_name'\n";
                        }
                    } else {
                        echo "Error creating the directory: '$file_name'\n";
                    }
                } else {
                    echo "Error downloading the image: '$image_url'\n";
                }
            }
        }
    } else {
        echo "No images found on the page.\n";
    }

    // Close the cURL connection
    curl_close($ch);
}

// Register the function in the cron
function wp_imgsed_feed_schedule_cron() {
    if (!wp_next_scheduled('wp_imgsed_feed_scrape_cron')) {
        wp_schedule_event(time(), 'every_six_hours', 'wp_imgsed_feed_scrape_cron');
    }
}
add_action('wp', 'wp_imgsed_feed_schedule_cron');

// Action for periodic scraping
function wp_imgsed_feed_do_scrape_cron() {
    wp_imgsed_feed_scrape_instagram();
}
add_action('wp_imgsed_feed_scrape_cron', 'wp_imgsed_feed_do_scrape_cron');

// Define the 6-hour interval
function wp_imgsed_feed_custom_cron_intervals($schedules) {
    $schedules['every_six_hours'] = array(
        'interval' => 6 * 60 * 60,
        'display' => __('Every 6 hours', 'textdomain')
    );
    return $schedules;
}
add_filter('cron_schedules', 'wp_imgsed_feed_custom_cron_intervals');

// Shortcode to display the latest 9 images with alternative text
function wp_imgsed_feed_shortcode($atts) {
    // Set the number of images to retrieve
    $atts = shortcode_atts(array(
        'count' => 9,
    ), $atts);

    // Query images from the media library with 'imgsed' in the description
    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => intval($atts['count']),
        'post_mime_type' => 'image',
        's' => 'imgsed', // Search for 'imgsed' in post content
        'orderby' => 'date',
        'order' => 'DESC',
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $output = '<div style="display: flex; flex-wrap: wrap; justify-content: flex-start;">';
        $count = 0;

        while ($query->have_posts()) {
            $query->the_post();
            $image_url = wp_get_attachment_url();
            $alt_text = get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true);
            $instagram_username = get_option('instagram_username');

            // Check if the "alt" attribute is empty before printing the image
            if (!empty($alt_text)) {
                $output .= '<div style="flex: 0 0 calc(33.33% - 10px); margin: 5px; box-sizing: border-box;">';
                $output .= '<a href="https://www.instagram.com/' . esc_attr($instagram_username) . '" target="_blank" title="' . esc_attr($alt_text) . '">';
                $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt_text) . '" width="320" height="320" style="max-width: 100%; height: auto; display: block;">';
                $output .= '</a>';
                $output .= '</div>';
            }

            $count++;
        }

        $output .= '</div>'; // Close the main container
        wp_reset_postdata();
    } else {
        $output = 'No images found with the specified description.';
    }

    return $output;
}
add_shortcode('wp_imgsed_feed', 'wp_imgsed_feed_shortcode');