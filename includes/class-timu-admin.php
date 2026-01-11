<?php
/**
 * TIMU Admin Interface
 * 
 * Handles admin UI for copyright management and verification.
 * 
 * @package Module_Images_Support
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class TIMU_Admin {
    
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_attachment_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_timu_verify_ownership', array( $this, 'ajax_verify_ownership' ) );
        add_action( 'wp_ajax_timu_generate_dmca', array( $this, 'ajax_generate_dmca' ) );
        add_action( 'wp_ajax_timu_embed_ownership', array( $this, 'ajax_embed_ownership' ) );
    }
    
    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Copyright Protection Settings', 'module-images-support' ),
            __( 'Copyright Protection', 'module-images-support' ),
            'manage_options',
            'timu-copyright-settings',
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting( 'timu_settings_group', 'timu_settings' );
        
        add_settings_section(
            'timu_general_section',
            __( 'General Settings', 'module-images-support' ),
            array( $this, 'render_general_section' ),
            'timu-copyright-settings'
        );
        
        add_settings_field(
            'enable_metadata',
            __( 'Enable EXIF/IPTC Metadata', 'module-images-support' ),
            array( $this, 'render_checkbox_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'enable_metadata', 'label' => __( 'Embed copyright metadata in EXIF/IPTC fields', 'module-images-support' ) )
        );
        
        add_settings_field(
            'enable_dct_fingerprint',
            __( 'Enable DCT Fingerprinting', 'module-images-support' ),
            array( $this, 'render_checkbox_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'enable_dct_fingerprint', 'label' => __( 'Embed ownership ID in DCT frequency domain', 'module-images-support' ) )
        );
        
        add_settings_field(
            'enable_lsb_fingerprint',
            __( 'Enable LSB Fingerprinting', 'module-images-support' ),
            array( $this, 'render_checkbox_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'enable_lsb_fingerprint', 'label' => __( 'Embed ownership ID using LSB steganography', 'module-images-support' ) )
        );
        
        add_settings_field(
            'copyright_text',
            __( 'Copyright Text', 'module-images-support' ),
            array( $this, 'render_text_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'copyright_text', 'placeholder' => '© 2026 Company Name' )
        );
        
        add_settings_field(
            'creator_name',
            __( 'Creator Name', 'module-images-support' ),
            array( $this, 'render_text_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'creator_name', 'placeholder' => 'Photographer Name' )
        );
        
        add_settings_field(
            'rights_usage',
            __( 'Rights Usage', 'module-images-support' ),
            array( $this, 'render_text_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'rights_usage', 'placeholder' => 'All Rights Reserved' )
        );
        
        add_settings_field(
            'usage_terms',
            __( 'Usage Terms', 'module-images-support' ),
            array( $this, 'render_text_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'usage_terms', 'placeholder' => 'CC BY 4.0' )
        );
        
        add_settings_field(
            'license_url',
            __( 'License URL', 'module-images-support' ),
            array( $this, 'render_text_field' ),
            'timu-copyright-settings',
            'timu_general_section',
            array( 'field' => 'license_url', 'placeholder' => 'https://creativecommons.org/licenses/by/4.0/' )
        );
    }
    
    /**
     * Render general section.
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configure dual-layer copyright mapping for your images.', 'module-images-support' ) . '</p>';
    }
    
    /**
     * Render checkbox field.
     * 
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $settings = get_option( 'timu_settings', array() );
        $value = isset( $settings[ $args['field'] ] ) ? $settings[ $args['field'] ] : false;
        ?>
        <label>
            <input type="checkbox" 
                   name="timu_settings[<?php echo esc_attr( $args['field'] ); ?>]" 
                   value="1" 
                   <?php checked( $value, true ); ?>>
            <?php echo esc_html( $args['label'] ); ?>
        </label>
        <?php
    }
    
    /**
     * Render text field.
     * 
     * @param array $args Field arguments.
     */
    public function render_text_field( $args ) {
        $settings = get_option( 'timu_settings', array() );
        $value = isset( $settings[ $args['field'] ] ) ? $settings[ $args['field'] ] : '';
        ?>
        <input type="text" 
               name="timu_settings[<?php echo esc_attr( $args['field'] ); ?>]" 
               value="<?php echo esc_attr( $value ); ?>" 
               placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
               class="regular-text">
        <?php
    }
    
    /**
     * Render settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'timu_settings_group' );
                do_settings_sections( 'timu-copyright-settings' );
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e( 'Protection Layer Information', 'module-images-support' ); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Method', 'module-images-support' ); ?></th>
                        <th><?php esc_html_e( 'Survives JPG Recompression', 'module-images-support' ); ?></th>
                        <th><?php esc_html_e( 'Survives Cropping', 'module-images-support' ); ?></th>
                        <th><?php esc_html_e( 'Survives Resizing', 'module-images-support' ); ?></th>
                        <th><?php esc_html_e( 'Human Detectable', 'module-images-support' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>EXIF/IPTC</strong></td>
                        <td>✅</td>
                        <td>✅</td>
                        <td>✅</td>
                        <td>✅</td>
                    </tr>
                    <tr>
                        <td><strong>DCT Fingerprint</strong></td>
                        <td>✅</td>
                        <td>❌</td>
                        <td>❌</td>
                        <td>❌</td>
                    </tr>
                    <tr>
                        <td><strong>LSB Fingerprint</strong></td>
                        <td>❌</td>
                        <td>✅ (10%)</td>
                        <td>✅</td>
                        <td>❌</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Add meta boxes to attachment edit screen.
     */
    public function add_attachment_meta_boxes() {
        add_meta_box(
            'timu_copyright_info',
            __( 'Copyright Protection', 'module-images-support' ),
            array( $this, 'render_copyright_meta_box' ),
            'attachment',
            'side',
            'default'
        );
    }
    
    /**
     * Render copyright meta box.
     * 
     * @param WP_Post $post Attachment post object.
     */
    public function render_copyright_meta_box( $post ) {
        $ownership_layers = get_post_meta( $post->ID, '_timu_ownership_layers', true );
        $owner_id = get_post_meta( $post->ID, '_timu_owner_id', true );
        $copyright_info = get_post_meta( $post->ID, '_timu_copyright_info', true );
        
        ?>
        <div class="timu-copyright-info">
            <h4><?php esc_html_e( 'Embedded Protection Layers', 'module-images-support' ); ?></h4>
            <ul>
                <li>
                    <strong><?php esc_html_e( 'Metadata:', 'module-images-support' ); ?></strong>
                    <?php echo ! empty( $ownership_layers['metadata'] ) ? '✓' : '✗'; ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'DCT Fingerprint:', 'module-images-support' ); ?></strong>
                    <?php echo ! empty( $ownership_layers['dct_fingerprint'] ) ? '✓' : '✗'; ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'LSB Fingerprint:', 'module-images-support' ); ?></strong>
                    <?php echo ! empty( $ownership_layers['lsb_fingerprint'] ) ? '✓' : '✗'; ?>
                </li>
            </ul>
            
            <?php if ( $owner_id ) : ?>
                <p><strong><?php esc_html_e( 'Owner ID:', 'module-images-support' ); ?></strong> <?php echo esc_html( $owner_id ); ?></p>
            <?php endif; ?>
            
            <p>
                <button type="button" class="button timu-verify-ownership" data-attachment-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Verify Ownership', 'module-images-support' ); ?>
                </button>
                
                <button type="button" class="button timu-embed-ownership" data-attachment-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Embed/Re-embed Layers', 'module-images-support' ); ?>
                </button>
            </p>
            
            <p>
                <button type="button" class="button timu-generate-dmca" data-attachment-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Generate DMCA Evidence', 'module-images-support' ); ?>
                </button>
            </p>
            
            <div class="timu-result" style="margin-top: 10px;"></div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts.
     * 
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook === 'post.php' || $hook === 'upload.php' ) {
            wp_enqueue_script(
                'timu-admin',
                TIMU_PLUGIN_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                TIMU_VERSION,
                true
            );
            
            wp_localize_script(
                'timu-admin',
                'timuAdmin',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'timu_admin_nonce' ),
                )
            );
        }
    }
    
    /**
     * AJAX handler for verifying ownership.
     */
    public function ajax_verify_ownership() {
        check_ajax_referer( 'timu_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'module-images-support' ) ) );
        }
        
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        
        if ( ! $attachment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'module-images-support' ) ) );
        }
        
        $verification = TIMU_Ownership::verify_attachment_ownership( $attachment_id );
        
        wp_send_json_success( $verification );
    }
    
    /**
     * AJAX handler for generating DMCA evidence.
     */
    public function ajax_generate_dmca() {
        check_ajax_referer( 'timu_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'module-images-support' ) ) );
        }
        
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        $infringing_url = isset( $_POST['infringing_url'] ) ? sanitize_url( $_POST['infringing_url'] ) : '';
        
        if ( ! $attachment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'module-images-support' ) ) );
        }
        
        $html = TIMU_DMCA::generate_evidence_report_html( $attachment_id, $infringing_url );
        
        wp_send_json_success( array( 'html' => $html ) );
    }
    
    /**
     * AJAX handler for embedding ownership.
     */
    public function ajax_embed_ownership() {
        check_ajax_referer( 'timu_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'module-images-support' ) ) );
        }
        
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        
        if ( ! $attachment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'module-images-support' ) ) );
        }
        
        $post = get_post( $attachment_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'module-images-support' ) ) );
        }
        
        $settings = get_option( 'timu_settings', array() );
        $user = get_userdata( $post->post_author );
        
        $copyright_info = array(
            'copyright' => ! empty( $settings['copyright_text'] ) ? $settings['copyright_text'] : sprintf( '© %s %s', gmdate( 'Y' ), get_bloginfo( 'name' ) ),
            'creator' => ! empty( $settings['creator_name'] ) ? $settings['creator_name'] : ( $user ? $user->display_name : '' ),
            'rights_usage' => isset( $settings['rights_usage'] ) ? $settings['rights_usage'] : 'All Rights Reserved',
            'usage_terms' => isset( $settings['usage_terms'] ) ? $settings['usage_terms'] : '',
            'license_url' => isset( $settings['license_url'] ) ? $settings['license_url'] : '',
        );
        
        $results = TIMU_Ownership::embed_full_ownership_chain( $attachment_id, $post->post_author, $copyright_info );
        
        wp_send_json_success( array( 'results' => $results ) );
    }
}
