<?php
/*
Plugin Name: Generative AI Plugin
Description: Generate similar content from OpenAI
Version: 1.0
License: GPL2
*/

// Enqueue scripts and styles
function generative_ai_enqueue_scripts() {
    wp_enqueue_script('similar-content', plugins_url('js/similar-content.js', __FILE__), array('jquery'), null, true);
    wp_enqueue_style('similar-content-style', plugins_url('css/similar-content.css', __FILE__));

    $default_prompts = array();

    for ($i = 1; $i <= 5; $i++) {
        $default_prompt = get_option('generative_ai_default_prompt_' . $i, '');
        $default_prompts[] = !empty($default_prompt) ? $default_prompt : '';
    }

    wp_localize_script('similar-content', 'similarContentAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'default_prompts' => $default_prompts,
    ));
}
add_action('wp_enqueue_scripts', 'generative_ai_enqueue_scripts');

  


// Enqueue admin styles and scripts
function generative_ai_enqueue_admin_scripts() {
    // Enqueue your CSS file for the admin area
    wp_enqueue_style('generative-ai-admin-styles', plugins_url('css/generative-ai-styles.css', __FILE__));


}
add_action('admin_enqueue_scripts', 'generative_ai_enqueue_admin_scripts');
function enqueue_admin_custom_js() {
    wp_enqueue_script(
        'admin-custom-js',
        plugin_dir_url(__FILE__) . 'js/admin-prompt.js', // Update the path to your JS file accordingly
        array('jquery'),
        null,
        true
    );
}
add_action('admin_enqueue_scripts', 'enqueue_admin_custom_js');

// Add settings page to WordPress admin menu
function generative_ai_add_settings_page() {
    add_menu_page('Generative AI Settings', 'Generative AI', 'manage_options', 'generative-ai-settings', 'generative_ai_settings_page');
}
add_action('admin_menu', 'generative_ai_add_settings_page');

// Add submenu page for Popup Settings
function generative_ai_add_popup_settings_page() {
    add_submenu_page(
        'generative-ai-settings', // Parent menu slug
        'Popup Settings', // Page title
        'Popup Settings', // Menu title
        'manage_options', // Capability required to access the page
        'generative-ai-popup-settings', // Menu slug
        'generative_ai_popup_settings_page' // Callback function to display the page content
    );
}
add_action('admin_menu', 'generative_ai_add_popup_settings_page');

// Display Popup Settings page content
function generative_ai_popup_settings_page() {
    ?>
    <div class="wrap generative-ai-form">
        <h1>Popup Settings</h1>
        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php settings_fields('generative_ai_popup_settings_group'); ?>
            <?php do_settings_sections('generative_ai_popup_settings_group'); ?>
            <!-- Add form fields for logo upload, site name, disclaimer text, and URL here -->
            <div class="form-group form-col-grid">
                <div class="form-col">
                    <div class="form-div">
                        <label for="logo_upload">Logo Upload:</label>
                        <input type="file" id="logo_upload" name="logo_upload">
                        <?php $logo_url = esc_attr(get_option('logo_upload')); ?>
                        <?php if ($logo_url) : ?>
                            <img src="<?php echo $logo_url; ?>" alt="Logo" style="max-width: 200px;">
                            <label for="remove_logo">
                                <input type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                Remove Logo
                            </label>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-lable-input">
                        <label for="site_name">Site Name:</label>
                        <input type="text" id="site_name" name="site_name" value="<?php echo esc_attr(get_option('site_name')); ?>">
                    </div>

                    <div class="form-lable-input">
                        <div class="form-group">
                            <label for="disclaimer_text">Footer Text:</label>
                            <?php 
                                $content = get_option('disclaimer_text');
                                $editor_id = 'disclaimer_text';
                                wp_editor($content, $editor_id);
                            ?>
                        </div>
                    </div>
                    
                    <?php submit_button('Save Settings'); ?>
                </div>
            </div>
        </form>
    </div>
    <?php
}

// Register settings for Popup Settings page
function generative_ai_register_popup_settings() {
    register_setting('generative_ai_popup_settings_group', 'site_name');
    register_setting('generative_ai_popup_settings_group', 'disclaimer_text');
    register_setting('generative_ai_popup_settings_group', 'disclaimer_url');
    
    // Handle logo upload separately
    register_setting('generative_ai_popup_settings_group', 'logo_upload', 'handle_logo_upload');
}

function handle_logo_upload($option) {
    // Handle logo removal
    if (!empty($_POST['remove_logo'])) {
        return '';
    }

    if (!empty($_FILES['logo_upload']['name'])) {
        $file = $_FILES['logo_upload'];
        
        if (!empty($file['error'])) {
            add_settings_error('logo_upload', 'upload_error', $file['error'], 'error');
            return get_option('logo_upload');
        }

        if (strpos($file['type'], 'image') === false) {
            add_settings_error('logo_upload', 'invalid_file', 'Invalid file format. Please upload an image file.', 'error');
            return get_option('logo_upload');
        }

        $upload_overrides = array('test_form' => false);
        $upload = wp_handle_upload($file, $upload_overrides);

        if (!empty($upload['error'])) {
            add_settings_error('logo_upload', 'upload_error', $upload['error'], 'error');
            return get_option('logo_upload');
        }

        return $upload['url'];
    }

    return get_option('logo_upload');
}

add_action('admin_init', 'generative_ai_register_popup_settings');

// Settings page content
function generative_ai_settings_page() {
    ?>
    <div class="wrap generative-ai-form">
        <h1>Generative AI Settings</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('generative_ai_settings_group'); ?>
            <?php do_settings_sections('generative_ai_settings_group'); ?>
            <div class="form-col-grid">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="form-col">
                        <h2>API Settings: Button <?php echo $i; ?> Generator</h2>
                        <div class="form-div">
                            <div class="form-lable-input">
                                <div class="lable">API Endpoint <?php echo $i; ?></div>
                                <div class="form-input"><input type="text" name="generative_ai_api_endpoint_<?php echo $i; ?>" value="<?php echo esc_attr(get_option('generative_ai_api_endpoint_' . $i)); ?>" /></div>
                            </div>
                            <div class="form-lable-input">
                                <div class="lable">API Key <?php echo $i; ?></div>
                                <div class="form-input"><input type="text" name="generative_ai_api_key_<?php echo $i; ?>" value="<?php echo esc_attr(get_option('generative_ai_api_key_' . $i)); ?>" /></div>
                            </div>
							<div class="form-lable-input">
                                <div class="lable">Model Name <?php echo $i; ?></div>
                                <div class="form-input"><input type="text" name="generative_ai_model_name_<?php echo $i; ?>" value="<?php echo esc_attr(get_option('generative_ai_model_name_' . $i)); ?>" /></div>
                            </div>
                            <div class="form-lable-input">
                                <div class="lable">Default Prompt <?php echo $i; ?></div>
                                <div class="form-input"><textarea class="generative-ai-default-prompt" name="generative_ai_default_prompt_<?php echo $i; ?>" rows="4" cols="50"><?php echo esc_attr(get_option('generative_ai_default_prompt_' . $i)); ?></textarea></div>
                            </div>
                            <div class="form-lable-input">
                                <div class="lable">Button Label <?php echo $i; ?></div>
                                <div class="form-input"><input type="text" name="generative_ai_button_label_<?php echo $i; ?>" value="<?php echo esc_attr(get_option('generative_ai_button_label_' . $i)); ?>" /></div>
                            </div>
                            <div class="form-lable-input">
                                <div class="lable">Show Button on Website?</div>
                                <div class="form-input"><input type="checkbox" name="generative_ai_show_on_website_<?php echo $i; ?>" value="1" <?php checked(1, get_option('generative_ai_show_on_website_' . $i), true); ?> /></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="submit-button">
                <?php submit_button(); ?>
            </div>
        </form>
    </div>
    <?php
}

// Register settings
function generative_ai_register_settings() {
    for ($i = 1; $i <= 5; $i++) {
        register_setting('generative_ai_settings_group', 'generative_ai_api_endpoint_' . $i);
        register_setting('generative_ai_settings_group', 'generative_ai_api_key_' . $i);
		register_setting('generative_ai_settings_group', 'generative_ai_model_name_' . $i);
        register_setting('generative_ai_settings_group', 'generative_ai_default_prompt_' . $i);
        register_setting('generative_ai_settings_group', 'generative_ai_button_label_' . $i);
        register_setting('generative_ai_settings_group', 'generative_ai_show_on_website_' . $i);
    }
}
add_action('admin_init', 'generative_ai_register_settings');
function get_openai_similar_content_ajax() {
    $button_number = isset($_POST['button_number']) ? intval($_POST['button_number']) : 1;
    $api_key = get_option('generative_ai_api_key_' . $button_number);
    $model_name = get_option('generative_ai_model_name_' . $button_number);
    $api_url = get_option('generative_ai_api_endpoint_' . $button_number);
    $default_prompt = isset($_POST['default_prompt']) ? sanitize_textarea_field($_POST['default_prompt']) : '';
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

    if (empty($api_key) || empty($api_url)) {
        wp_send_json_error('Error: API key or endpoint not found.');
        return;
    }

    // Use 'gpt-4' as the default model if no model name is specified
    if (empty($model_name)) {
        $model_name = 'gpt-4';
    }

    // Combine the prompt and content
    $prompt = $default_prompt . "\n\n" . $content;

    // Prepare the JSON payload
    $payload = array(
        'model' => $model_name,
        'messages' => array(
            array('role' => 'user', 'content' => $prompt)
        ),
        'stream' => true,  
    );

    // Convert payload to JSON
    $json_payload = json_encode($payload);
// Log the payload
error_log('Payload: ' . $json_payload);
    $args = array(
        'body' => $json_payload,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'timeout' => 50, // Increase timeout to handle large requests
    );

    $response = wp_remote_post($api_url, $args);

    if (is_wp_error($response)) {
        error_log('Error: Unable to connect to the server. ' . $response->get_error_message());
        wp_send_json_error('Error: Unable to connect to the server. Please try again later.');
        return;
    }

    $body = wp_remote_retrieve_body($response);

    // Process streaming response
    if (strpos($body, 'data: ') !== false) {
        $lines = explode("\n", $body);
        $content = '';

        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $data = json_decode(substr($line, 6), true);

                if (isset($data['choices'][0]['delta']['content'])) {
                    $content .= $data['choices'][0]['delta']['content'];
                }
            }
        }

        wp_send_json_success(trim($content));
    } else {
        error_log('No similar content found. Response: ' . print_r($data, true));
        wp_send_json_error('No output content found. Please refresh it.');
    }
}

add_action('wp_ajax_get_similar_content', 'get_openai_similar_content_ajax');
add_action('wp_ajax_nopriv_get_similar_content', 'get_openai_similar_content_ajax');




// Shortcode to display similar content button
// Shortcode to display similar content button
function similar_content_shortcode($atts) {
    global $post;

    $atts = shortcode_atts(array(  
        'label' => 'Click for similar content',
        'button_number' => 1, // Default button number
    ), $atts, 'similarcontent');

    // Get the plain text content of the post
    $content = $post ? wp_strip_all_tags($post->post_content) : '';

    return '<div class="all-buttons-wrapper">
                <button class="similar-content-button submit wp-block-button__link wp-element-button" 
                        data-content="' . esc_attr($content) . '" 
                        data-button-number="' . esc_attr($atts['button_number']) . '">' . 
                        esc_html($atts['label']) . 
                '<div id="loader" class="loader" style="display: none;"></div></button>
                <div id="similar-content-result-' . esc_attr($atts['button_number']) . '" class="similar-content-result"></div>
            </div>';
}
add_shortcode('similarcontent', 'similar_content_shortcode');

// Function to insert similar content shortcode after post title
function insert_similar_content_shortcode_after_post_title($content) {
    if (in_the_loop() && is_main_query() && is_single()) {
        global $post;

        // Array of post IDs to check
       $allowed_post_ids = array(242076, 241983, 99); // Add more post IDs as needed

        // Check if the post ID is in the allowed list
     //  if (in_array($post->ID, $allowed_post_ids)) {
            $shortcodes = '';
            for ($i = 1; $i <= 5; $i++) {
                $label = get_option('generative_ai_button_label_' . $i);
                $show_on_website = get_option('generative_ai_show_on_website_' . $i);

                if (!empty($label) && $show_on_website) {
                    $shortcodes .= '[similarcontent label="' . esc_attr($label) . '" button_number="' . $i . '"] ';
                }
            }

            if (!empty($shortcodes)) {
                $content = '<div class="all-buttons-wrapper">' . do_shortcode($shortcodes) . '</div>' . $content;
            }
        //}
    }
    return $content;
}
add_filter('the_content', 'insert_similar_content_shortcode_after_post_title');



function add_modal_to_footer() {
    $logo_url = esc_url(get_option('logo_upload'));
    $disclaimer_text = wp_kses_post(get_option('disclaimer_text'));
    $home_url = esc_url(get_home_url());
    $site_name = esc_html(get_option('site_name'));
// Use plugins_url to get the correct path to the image in the plugin folder
    $copy_icon_url = plugins_url('img/copy.png', __FILE__);
    // Check if logo URL is empty and set the content accordingly
    $logo_or_name = $logo_url ? '<a href="' . $home_url . '"><img src="' . $logo_url . '" alt="Logo"></a>' : '<a href="' . $home_url . '" class="site-name-popup">' . $site_name . '</a>';

    echo ' 
    <!-- Modal HTML structure -->
    <div id="similarContentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="popup-logo">' . $logo_or_name . '</div>
            <div class="title-close">
                <h2 class="main-title"></h2>
            </div>
            <div class="popup-content">
                <div class="popup-inner modal-body">
                    <!-- Content will be injected here by AJAX -->
                </div>
                <div class="copy-button">
                    <a class="copy" href="#">
                       <img src="' . $copy_icon_url . '" alt="Copy Icon"> Copy
                    </a>    <span class="copy-message" style="display:none; margin-left:10px; color:green;">Content copied!</span>

                </div>
            </div>
            <div class="desclim"> 
          ' .$disclaimer_text. '
            </div>
        </div>
    </div>
    ';
}

add_action('wp_footer', 'add_modal_to_footer');

