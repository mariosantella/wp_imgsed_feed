# WP Imgsed Feed Plugin

The WP Imgsed Feed plugin allows you to display the latest Instagram posts from an Imgsed feed on your WordPress website without requiring access to the Instagram API. You can use this plugin to showcase Instagram images with alternative text.

## Installation

1. **Download the Plugin:**

   - Download the `wp-imgsed-feed.zip` file from the [GitHub repository](https://github.com/mariosantella/wp_imgsed_feed) or from the WordPress Plugin Directory.

2. **Upload the Plugin:**

   - Log in to your WordPress admin dashboard.

   - Navigate to `Plugins` > `Add New`.

   - Click the `Upload Plugin` button at the top of the page.

   - Choose the `wp-imgsed-feed.zip` file you downloaded and click `Install Now`.

3. **Activate the Plugin:**

   - After installation, click the `Activate` button to enable the plugin.

## Configuration

Once the plugin is activated, follow these steps to configure it:

1. **Access Plugin Settings:**

   - In your WordPress admin dashboard, go to `Settings` > `wp_imgsed_feed`.

2. **Set Instagram Username:**

   - Enter your Instagram username in the "Username Instagram" field.

   - Click the "Save" button to save your settings.

## Display Instagram Feed

You can display the Instagram feed on your WordPress site using a shortcode.

1. **Insert the Shortcode:**

   - Create or edit a WordPress post or page where you want to display the Instagram feed.

   - Insert the following shortcode into your content:

     ```
     [wp_imgsed_feed]
     ```

   - You can also specify the number of images to display using the `count` attribute. For example, to display 6 images, use `[wp_imgsed_feed count="6"]`.

2. **Update the Post/Page:**

   - Update or publish the post/page to display the Instagram feed.

## Automatic Updates

The plugin will automatically scrape and update your Instagram feed at intervals you have configured. By default, it scrapes every 6 hours. You can change this interval if needed.

## Usage Notes

- Images from your Instagram feed will be downloaded and stored in your WordPress media library with the "imgsed" category.

- The alternative text (alt text) of the images will be set based on your Instagram posts.

- Images with the word "thumb" in their filenames are ignored to avoid displaying thumbnails.

- The plugin is designed to retrieve and display the latest 9 images from your Instagram feed.

- Images are displayed in a responsive grid on your WordPress site.

## Troubleshooting

- If you encounter any issues or have questions, please [visit our support page](https://github.com/mariosantella/wp_imgsed_feed/issues) for assistance.

Enjoy displaying your Instagram feed on your WordPress website with the WP Imgsed Feed plugin!