<?php
/*
Plugin Name: Sermon Workflow Automation
Description: Configure and deploy sermon publishing workflows using n8n.
Version: 1.0
Author: John Hutchinson
*/

defined('ABSPATH') or die('No script kiddies please!');

// 1. Admin Menu and Settings Page
add_action('admin_menu', 'sermon_workflow_menu');
add_action('admin_init', 'n8n_audio_settings_init');

function sermon_workflow_menu() {
    add_menu_page('Sermon Workflow Automation', 'Sermon Workflow Automation', 'manage_options', 'sermon-workflow-automation', 'sermon_generated_content_page', 'dashicons-microphone');
    add_submenu_page('sermon-workflow-automation', 'Generated Content', 'Generated Content', 'manage_options', 'sermon-generated-content', 'sermon_generated_content_page');
    add_submenu_page('sermon-workflow-automation', 'Settings', 'Settings', 'manage_options', 'sermon-workflow-settings', 'sermon_workflow_settings_page');
}

function sermon_workflow_settings_page() {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('save_sermon_workflow')) {
        $posted = $_POST;
        $existing = get_option('sermon_workflow_config', []);
		
		// Save posted options
        update_option('sermon_workflow_config', $_POST);
        echo '<div class="updated"><p>Settings saved.</p></div>';

        // Preserve the API key if missing from POST
        if (empty($posted['n8n_rest_api_key']) && !empty($existing['n8n_rest_api_key'])) {
            $posted['n8n_rest_api_key'] = $existing['n8n_rest_api_key'];
    }
}


    $options = get_option('sermon_workflow_config', []);
    $method = $options['publishing_method'] ?? '';
   ?>
    <div class="wrap">
        <h1>Sermon Workflow Configuration</h1>
        <form method="post">
            <?php wp_nonce_field('save_sermon_workflow'); ?>
            <h2>1. Select Publishing Method</h2>
            <select name="publishing_method" id="publishing_method">
                <option value="">-- Select Method --</option>
                <option value="wordpress_first" <?php selected($method, 'wordpress_first'); ?>>WordPress First</option>
                <option value="sermonaudio_first" <?php selected($method, 'sermonaudio_first'); ?>>SermonAudio.com First</option>
                <option value="youtube_first" <?php selected($method, 'youtube_first'); ?>>YouTube First</option>
            </select>

            <div id="common-settings" style="margin-top:20px;">
                <h2>2. Common Settings</h2>
                <label>n8n Instance URL: <input type="text" name="n8n_url" value="<?php echo esc_attr($options['n8n_url'] ?? ''); ?>"></label><br>
                <label>n8n API Key: <input type="text" name="n8n_api_key" value="<?php echo esc_attr($options['n8n_api_key'] ?? ''); ?>"></label><br>
                <label>Transcription Service: <input type="text" name="transcription_service" value="<?php echo esc_attr($options['transcription_service'] ?? ''); ?>"></label><br>
                <label>OpenAI API Key: <input type="text" name="openai_api_key" value="<?php echo esc_attr($options['openai_api_key'] ?? ''); ?>"></label><br>
            </div>

            <div id="wordpress_first" class="method-settings" style="display:none; margin-top:20px;">
                <h2>WordPress First Settings</h2>
                <label>Trigger Post Type: <input type="text" name="wp_post_type" value="<?php echo esc_attr($options['wp_post_type'] ?? 'sermon'); ?>"></label><br>
                <label>Audio Meta Key: <input type="text" name="audio_meta_key" value="<?php echo esc_attr($options['audio_meta_key'] ?? ''); ?>"></label><br>
            </div>

            <p><input type="submit" value="Save Settings" class="button button-primary"></p>
        </form>
    </div>
    <script>
        function toggleMethodSettings() {
            const method = document.getElementById('publishing_method').value;
            document.querySelectorAll('.method-settings').forEach(div => div.style.display = 'none');
            if (method) {
                document.getElementById(method).style.display = 'block';
            }
        }
        document.getElementById('publishing_method').addEventListener('change', toggleMethodSettings);
        window.addEventListener('DOMContentLoaded', toggleMethodSettings);
    </script>
    <?php
}

function n8n_audio_settings_init() {
    // Reserved for future extensions
}



// 2. Audio Upload Trigger (for WordPress First method)
add_action('wp_handle_upload', 'sermon_workflow_audio_upload_trigger');

function sermon_workflow_audio_upload_trigger($upload) {
    $options = get_option('sermon_workflow_config');
    if (($options['publishing_method'] ?? '') !== 'wordpress_first') {
        return $upload;
    }

    if (strpos($upload['type'], 'audio/') !== 0) {
        return $upload;
    }
    // create the api routes to send dynamically to n8n
    $create_api_endpoint = trailingslashit(get_site_url()) . 'wp-json/sermon/v1/create';

    $payload = [
        'file_name' => basename($upload['file']),
        'file_url' => $upload['url'],
        'file_type' => $upload['type'],
        'upload_time' => current_time('mysql'),
        'post_type' => $options['wp_post_type'] ?? '',
        'audio_meta_key' => $options['audio_meta_key'] ?? '',
        'wordpress_api_key' => $options['n8n_rest_api_key'] ?? '',
        'transcription_service' => $options['transcription_service'] ?? '',
        'openai_api_key' => $options['openai_api_key'] ?? '',
        'site_url' => get_site_url(),
        'create_api_endpoint' => $create_api_endpoint 
    ];

    $response = wp_remote_post($options['n8n_url'] ?? '', [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($payload),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('[Sermon Workflow] n8n webhook failed: ' . $response->get_error_message());
    }

    return $upload;
}

// 3. Generated Content Page (dynamic table from DB)
function sermon_generated_content_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'sermon_generated';

    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

    echo '<div class="wrap"><h1>Generated Content</h1>';

    // CSS for truncation + modal styling of excerpt
    echo '<style>
        .wp-list-table td {
            max-width: 250px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            vertical-align: top;
        }
        .excerpt-cell {
            cursor: pointer;
            color: #0073aa;
        }
        .excerpt-cell:hover {
            text-decoration: underline;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            background: white;
            padding: 20px;
            border-radius: 4px;
            max-width: 600px;
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
        }
        .modal-close {
            float: right;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            margin-top: -10px;
            margin-right: -10px;
        }
    </style>';

    // Modal markup
    echo '<div class="modal-overlay" id="excerpt-modal">
            <div class="modal-box">
                <span class="modal-close" id="modal-close">&times;</span>
                <div id="modal-content"></div>
            </div>
          </div>';

    echo '<table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Title</th><th>Excerpt</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="sermon-content-body">';

    if ($rows) {
        foreach ($rows as $row) {
            $status_class = strtolower($row->approval_status);
            $safe_excerpt = esc_html($row->excerpt);
            $link = esc_url($row->link);
            $content_type = esc_html($row->content_type);
            echo "<tr data-id='{$row->id}'>
                    <td class='sermon-title'>" . esc_html($row->title) . "</td>
                    <td class='excerpt-cell' data-full=\"{$safe_excerpt}\">{$safe_excerpt}</td>
                    <td class='{$link}'>" {$link} "</td>
                    <td class='{$content_type}'>" {$content_type} "</td>
                    <td class='{$status_class}'>" . esc_html($row->approval_status) . "</td>
                    <td>
                        <button class='button approve-btn'>Approve</button>
                        <button class='button reject-btn'>Reject</button>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='4'>No generated content found.</td></tr>";
    }

    echo '</tbody></table></div>';
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Excerpt click behavior
            const modal = document.getElementById('excerpt-modal');
            const modalContent = document.getElementById('modal-content');
            const modalClose = document.getElementById('modal-close');

            document.querySelectorAll('.excerpt-cell').forEach(cell => {
                cell.addEventListener('click', function () {
                    const fullText = this.dataset.full;
                    modalContent.textContent = fullText;
                    modal.style.display = 'flex';
                });
            });

            modalClose.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            window.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Approve/Reject logic
            document.querySelectorAll('.approve-btn, .reject-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const row = this.closest('tr');
                    const id = row.dataset.id;
                    const action = this.classList.contains('approve-btn') ? 'approve' : 'reject';
                    const title = row.querySelector('.sermon-title').textContent.trim();

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'sermon_content_approval',
                            sermon_id: id,
                            sermon_title: title,
                            approval_action: action,
                            _wpnonce: '<?php echo wp_create_nonce("sermon_approve_nonce"); ?>'
                        })
                    })
                    .then(res => res.json())
                    .then(data => {

                        if (data.success) {
                            const statusCell = row.querySelector('td:nth-child(3)');
                            statusCell.textContent = data.status;
                            statusCell.className = data.status.toLowerCase();
                            location.reload(); // Reload to reflect changes

                        } else {
                            alert("Error: " + data.data.message);
                        }
                    });
                });
            });
        });
    </script>
    <?php
}


// 4. AJAX Handler for Approve/Reject
add_action('wp_ajax_sermon_content_approval', 'handle_sermon_content_approval');

function handle_sermon_content_approval() {
    check_ajax_referer('sermon_approve_nonce');

    global $wpdb;

    $id = intval($_POST['sermon_id']);
    $title = sanitize_text_field($_POST['sermon_title'] ?? '');
    $action = sanitize_text_field($_POST['approval_action']);
    $status = $action === 'approve' ? 'Approved' : 'Rejected';

    // Update approval status by ID in custom table (same as before)
    $updated = $wpdb->update(
        $wpdb->prefix . 'sermon_generated',
        ['approval_status' => $status],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'Update failed.']);
        return;
    }

    // Publish the post by title if approved
    if ($status === 'Approved' && !empty($title)) {
        $post = get_page_by_title($title, OBJECT, 'post'); // Change 'post' if custom post type

        if ($post && $post->post_status !== 'publish') {
            wp_update_post([
                'ID' => $post->ID,
                'post_status' => 'publish'
            ]);
        }
    }

    wp_send_json_success(['status' => $status]);
}


// 5. Create the custom table on plugin activation
register_activation_hook(__FILE__, 'create_sermon_generated_table');

function create_sermon_generated_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sermon_generated';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title TEXT NOT NULL,
        excerpt TEXT NOT NULL,
        approval_status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending'
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        content_type ENUM('facebook_post', 'wp_blog_post', 'church_content_sermon_transcript') NOT NULL DEFAULT 'wp_blog_post',
        link TEXT NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'sermon_generate_api_key');

function sermon_generate_api_key() {
    $options = get_option('sermon_workflow_config', []);
    if (empty($options['n8n_rest_api_key'])) {
        $options['n8n_rest_api_key'] = bin2hex(random_bytes(16)); // 32 char hex key
        update_option('sermon_workflow_config', $options);
    }
}

function sermon_workflow_verify_api_key() {
    $headers = getallheaders();

    // Normalize header keys to lowercase for compatibility
    $key = '';
    foreach ($headers as $header => $value) {
        if (strtolower($header) === 'x-api-key') {
            $key = $value;
            break;
        }
    }

    return $key === $options['n8n_rest_api_key'];
}


add_action('rest_api_init', function () {
    register_rest_route('sermon/v1', '/create', [
        'methods' => 'POST',
        'callback' => 'sermon_create_generated_entry',
        'permission_callback' => '__return_true', // Secured manually below
    ]);
});


function sermon_create_generated_entry($request) {
    $params = $request->get_json_params();
    $api_key = $request->get_header('x-api-key');
    $options = get_option('sermon_workflow_config', []);

    // 1. Secure it with your n8n API key
    if (empty($api_key) || $api_key !== ($options['n8n_rest_api_key'] ?? '')) {
        return new WP_REST_Response(['error' => 'Unauthorized'], 401);
    }

    // 2. Validate input
    if (empty($params['title']) || empty($params['excerpt']) || empty($params['link'])) {
        return new WP_REST_Response(['error' => 'Missing title, excerpt, or link'], 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sermon_generated';

    $result = $wpdb->insert($table, [
        'title' => sanitize_text_field($params['title']),
        'excerpt' => sanitize_text_field($params['excerpt']),
        'approval_status' => 'Pending',
        'created_at' => current_time('mysql'),
    ]);

    if ($result === false) {
        return new WP_REST_Response(['error' => 'Database insert failed'], 500);
    }

    return new WP_REST_Response(['success' => true, 'id' => $wpdb->insert_id], 201);
}
