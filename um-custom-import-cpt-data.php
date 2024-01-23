<?php
/*
Plugin Name: UM Import CPT Data
Description: A custom plugin with a settings page and import data to directory cpt posts.
Version: 1.0
Text Domain: um-import-cpt
Author: Umesh Ladumor
*/


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
* Currently plugin version.
* Rename this for your plugin and update it as you release new versions.
*/
define( 'UM_CUSTOM_IMPORT_VERSION', '1.0.0' );
define( 'UM_CUSTOM_IMPORT_URL', plugin_dir_url(__FILE__) );
define( 'UM_CUSTOM_IMPORT_PATH', plugin_dir_path(__FILE__) );


// Enqueue scripts for media upload and color picker
function um_custom_cpt_enqueue_scripts() {
    wp_enqueue_media();
    wp_enqueue_script('custom-plugin-media-upload', UM_CUSTOM_IMPORT_URL . 'assets/js/admin/media-upload.js', array('jquery'), null, true);
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('custom-plugin-color-picker', UM_CUSTOM_IMPORT_URL . 'assets/js/admin/color-picker.js', array('wp-color-picker'), null, true);
    wp_enqueue_style( 'custom_css', UM_CUSTOM_IMPORT_URL . 'assets/css/admin/main.css', array(), null, 'all' );
}
add_action('admin_enqueue_scripts', 'um_custom_cpt_enqueue_scripts');


// Add admin menu page in admin side
add_action( 'admin_menu', 'csv_upload_options_page' );

function csv_upload_options_page() {
    add_menu_page(
        __( 'CSV Upload Settings', 'um-import-cpt' ),
        __( 'CSV Upload', 'um-import-cpt' ),
        'manage_options',
        'csv_upload_settings',
        'csv_upload_settings_page',
        'dashicons-database-import',
        6
    );

    add_submenu_page(
        'csv_upload_settings',
        __( 'CSV Settings', 'um-import-cpt' ),
        __( 'CSV Settings', 'um-import-cpt' ),
        'manage_options', 
        'csv_settings_page',
        'csv_settings_page_callback'
    );
}


// Add option settings fields form
function csv_upload_settings_page() {
	?>
    <div class="wrap">
        <h2>CSV Upload Settings</h2>
        <?php
        settings_errors(); 
        ?>
        <form method="post" enctype="multipart/form-data" action="options.php">
            <?php
            settings_fields('csv_upload_settings');
            do_settings_sections('csv_upload_settings');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function csv_settings_page_callback() {
    // Check if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify nonce for security
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'csv_settings_nonce')) {
            // Update the post name option
            $post_name = sanitize_text_field($_POST['post_name']);
            update_option('post_name', $post_name);
            echo '<div class="updated"><p>' . __('Post name saved successfully.', 'um-import-cpt') . '</p></div>';
        }
    }

    // Display the form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <form method="post" action="">
            <?php
            // Output nonce field for security
            wp_nonce_field('csv_settings_nonce', '_wpnonce');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post_name"><?php _e('Post Name', 'um-import-cpt'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="post_name" id="post_name" value="<?php echo esc_attr(get_option('post_name')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Enter the name of the custom post name.', 'um-import-cpt'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Changes', 'um-import-cpt')); ?>
        </form>
    </div>
    <?php
}



// Add option section, fields settings and register settings.
function csv_upload_init() {
    add_settings_section('csv_upload_settings_section', 'CSV Upload Settings', '__return_false', 'csv_upload_settings');
    add_settings_field('csv_upload_csv_field', 'Upload CSV File', 'csv_upload_csv_field_callback', 'csv_upload_settings', 'csv_upload_settings_section');
    register_setting('csv_upload_settings', 'csv_upload_csv_field', 'csv_upload_validate_file');
}
add_action('admin_init', 'csv_upload_init');

// validate function for upload fields
function csv_upload_validate_file($file) {
    if (!empty($_FILES['csv_upload_csv_field']['name'])) {
        $uploaded_file = $_FILES['csv_upload_csv_field'];

        // Set up the array of supported file types
        $supported_types = array('text/csv', 'application/vnd.ms-excel');

        // Check if the uploaded file is a supported type
        if (!in_array($uploaded_file['type'], $supported_types)) {
            add_settings_error(
                'csv_upload_settings',
                'csv_upload_invalid_file',
                'Please upload a valid CSV file.',
                'error'
            );
            return get_option('csv_upload_csv_field'); // Restore previous value
        }

        // Upload the file and get the attachment ID
        //$upload_overrides = array('test_form' => false);
        //$file_id = media_handle_upload('csv_upload_csv_field', 0, array(), $upload_overrides);

        $file_id = $uploaded_file;

        // Check for errors during the file upload
        if (is_wp_error($file_id)) {
            add_settings_error(
                'csv_upload_settings',
                'csv_upload_error',
                'Error uploading CSV file. Please try again.',
                'error'
            );
            return get_option('csv_upload_csv_field'); // Restore previous value
        }

        // Update the file option with the attachment ID
        $file['file'] = $file_id;

        // Call function for uploaded file data
        process_uploaded_csv_data($file_id);

    } else {
        // Check if the file upload field is not empty
        add_settings_error(
            'csv_upload_settings',
            'csv_upload_invalid_file',
            'Please select a file.',
            'error'
        );
        
    }

    return $file;
}

// Upload field display 
function csv_upload_csv_field_callback() {
    echo '<input type="file" name="csv_upload_csv_field" accept=".csv">';
}

// Uploaded file for process data and add posts 
function process_uploaded_csv_data($file_id) {

    // get custom post name by admin side
    $custom_post_name = esc_attr(get_option('post_name'));

    //$file_path = get_attached_file($file_id);
	$file_path = $file_id['tmp_name'];

    // Parse the CSV file
    $csv_data = array_map('str_getcsv', file($file_path));

    // Define the CSV header (assumes the first row is header)
    $csv_header = array_shift($csv_data);

    // Create custom post types based on CSV data
    foreach ($csv_data as $post_data) {
        //$post_data = array_combine($csv_header, $row);
        $post_title = sanitize_text_field($post_data[0]);
        $post_content = sanitize_textarea_field($post_data[3]);
        $post_category = sanitize_text_field($post_data[2]);
        $featured_image_url = esc_url_raw($post_data[1]);
        $gallery_image_urls = sanitize_text_field($post_data[4]);
        $selected_type = sanitize_text_field($post_data[5]);
        $author_name = sanitize_text_field($post_data[6]);
        $author_email = sanitize_email($post_data[7]);

        // If user email is empty, set the admin as the post author
        if (empty($author_email)) {
            $post_author = 1; // User ID 1 corresponds to the admin
        } else {
            // Check if the user with the provided email exists
            $user = get_user_by('email', $author_email);

            // If the user doesn't exist, register the user and assign the Editor role
            if (!$user) {
                $random_password = wp_generate_password(12, false);

                $user_id = wp_create_user($author_email, $random_password, $author_email);

                if (!is_wp_error($user_id)) {
                    // Update user display name
                    wp_update_user(array('ID' => $user_id, 'display_name' => $author_name));

                    // Assign the Editor role to the newly registered user
                    $user = new WP_User($user_id);
                    $user->add_role('editor');
                }
            }
            
            $post_author = $user->ID;
        }

        // Insert directory posts
        if(!empty($post_data[0])) {
            // Adjust the post data as needed, customize the post type and meta keys accordingly
            $post_args = array(
                'post_type'   => 'um_'.$custom_post_name,  // Customize post type as needed
                'post_status' => 'publish',
                'post_title'  => $post_title,
                'post_content' => $post_content,
                'post_author'  => $post_author,
                
            );

            $post_id = wp_insert_post($post_args);

            // for feature image 
            if ($post_id && !empty($featured_image_url)) {
                $attachment_id = upload_and_attach_image($featured_image_url, $post_id);
                if ($attachment_id) {
                    update_post_meta($post_id, '_thumbnail_id', $attachment_id);
                } 
            }

            // Add categories to the post
            if ($post_id && !empty($post_category)) {
                $categories = explode(',', $post_category);

                foreach ($categories as $category_name) {
                    $category_name = trim($category_name);

                    // Check if category exists
                    $category = get_term_by('name', $category_name, 'category');

                    // If category doesn't exist, create it
                    if (!$category) {
                        $new_category = wp_insert_term($category_name, 'category');
                        if (!is_wp_error($new_category)) {
                            $category_id = $new_category['term_id'];
                        }
                    } else {
                        $category_id = $category->term_id;
                    }

                    // Assign category to the post
                    wp_set_post_categories($post_id, array($category_id), true);
                }
            }

            // Add images to the ACF gallery field
            if ($post_id && !empty($gallery_image_urls)) {
                $gallery_images = array();
        
                $image_urls = explode(',', $gallery_image_urls);
                foreach ($image_urls as $image_url) {
                    $image_url = esc_url_raw(trim($image_url));
                    $attachment_id = upload_and_attach_image($image_url, $post_id);
        
                    if ($attachment_id) {
                        $gallery_images[] = $attachment_id;
                    }
                }
        
                // Update ACF gallery field
                update_field('gallery_images', $gallery_images, $post_id);
            }

            // Update ACF radio button field 'type'
            if ($post_id && !empty($selected_type)) {
                update_field('type', $selected_type, $post_id);
            }

        } else {
            add_settings_error(
                'csv_upload_settings',
                'csv_upload_invalid_file',
                'Please check your csv file or name column should be fill.',
                'error'
            );

        }

    }
}

// Uploaded images to set attachment and add to media.
function upload_and_attach_image($image_url, $post_id) {
    // Download the image from the URL and store it in the media library
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);

    $upload = wp_upload_bits($filename, null, $image_data);

    if (!$upload['error']) {
        $file_path = $upload['file'];
        $file_type = wp_check_filetype($file_path, null);

        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (!is_wp_error($attachment_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            return $attachment_id;
        }
    }

    return false;
}

// Register custom post type as directory.
function register_directory_custom_post_type() {

    // get custom post name by admin side
    $custom_post_name = esc_attr(get_option('post_name'));
    
    register_post_type('um_'.$custom_post_name,
        array(
            'labels'      => array(
                'name'          => __($custom_post_name, 'textdomain'),
                'singular_name' => __($custom_post_name, 'textdomain'),
            ),
            'public'      => true,
            'has_archive' => true,
            'supports' => array( 'title', 'editor', 'custom-fields','thumbnail', 'author' ),
            'taxonomies' => array( 'category'),
        )
    );
}
add_action('init', 'register_directory_custom_post_type');


?>