<?php
/**
 * Focal Point Editor - Admin UI
 *
 * @package TIMU
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TIMU_Focal_Point_Editor
 *
 * Handles the admin UI for focal point editing.
 */
class TIMU_Focal_Point_Editor {
	
	/**
	 * Initialize the admin UI.
	 */
	public static function init() {
		// Add attachment fields in media modal.
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'add_focal_point_fields' ), 10, 2 );
		
		// Save attachment fields.
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save_focal_point_fields' ), 10, 2 );
		
		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		
		// Add custom meta box to attachment edit screen.
		add_action( 'add_meta_boxes_attachment', array( __CLASS__, 'add_focal_point_meta_box' ) );
		
		// AJAX handlers.
		add_action( 'wp_ajax_timu_save_focal_points', array( __CLASS__, 'ajax_save_focal_points' ) );
		add_action( 'wp_ajax_timu_get_focal_points', array( __CLASS__, 'ajax_get_focal_points' ) );
		add_action( 'wp_ajax_timu_preview_crops', array( __CLASS__, 'ajax_preview_crops' ) );
	}
	
	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		// Only enqueue on post edit screens and media screens.
		if ( ! in_array( $hook, array( 'post.php', 'upload.php', 'media-upload-popup' ), true ) ) {
			return;
		}
		
		// Enqueue styles.
		wp_enqueue_style(
			'timu-focal-point-editor',
			TIMU_PLUGIN_URL . 'assets/css/focal-point-editor.css',
			array(),
			TIMU_VERSION
		);
		
		// Enqueue scripts.
		wp_enqueue_script(
			'timu-focal-point-editor',
			TIMU_PLUGIN_URL . 'assets/js/focal-point-editor.js',
			array( 'jquery' ),
			TIMU_VERSION,
			true
		);
		
		// Localize script with data.
		wp_localize_script(
			'timu-focal-point-editor',
			'timuFocalPoint',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'timu_focal_point_nonce' ),
				'strings' => array(
					'addPoint'       => __( 'Add Focal Point', 'timu' ),
					'removePoint'    => __( 'Remove', 'timu' ),
					'label'          => __( 'Label', 'timu' ),
					'primary'        => __( 'Primary', 'timu' ),
					'secondary'      => __( 'Secondary', 'timu' ),
					'preview'        => __( 'Preview Crops', 'timu' ),
					'savingPoints'   => __( 'Saving focal points...', 'timu' ),
					'pointsSaved'    => __( 'Focal points saved successfully.', 'timu' ),
					'saveFailed'     => __( 'Failed to save focal points.', 'timu' ),
				),
			)
		);
	}
	
	/**
	 * Add focal point meta box to attachment edit screen.
	 *
	 * @param WP_Post $post The attachment post object.
	 */
	public static function add_focal_point_meta_box( $post ) {
		// Only show for images.
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return;
		}
		
		add_meta_box(
			'timu_focal_points',
			__( 'Focal Points', 'timu' ),
			array( __CLASS__, 'render_focal_point_meta_box' ),
			'attachment',
			'normal',
			'high'
		);
	}
	
	/**
	 * Render the focal point meta box.
	 *
	 * @param WP_Post $post The attachment post object.
	 */
	public static function render_focal_point_meta_box( $post ) {
		// Get existing focal points.
		$focal_points = timu_get_focal_points( $post->ID );
		
		// Get image URL.
		$image_url = wp_get_attachment_image_src( $post->ID, 'large' );
		
		if ( ! $image_url ) {
			echo '<p>' . esc_html__( 'Unable to load image.', 'timu' ) . '</p>';
			return;
		}
		
		wp_nonce_field( 'timu_focal_point_nonce', 'timu_focal_point_nonce_field' );
		
		?>
		<div class="timu-focal-point-editor">
			<div class="timu-image-container">
				<img src="<?php echo esc_url( $image_url[0] ); ?>" 
				     alt="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>"
				     class="timu-focal-image"
				     data-attachment-id="<?php echo esc_attr( $post->ID ); ?>">
				<div class="timu-focal-points-overlay"></div>
			</div>
			
			<div class="timu-focal-points-controls">
				<h4><?php esc_html_e( 'Focal Points', 'timu' ); ?></h4>
				<div class="timu-focal-points-list">
					<?php
					if ( ! empty( $focal_points ) ) {
						foreach ( $focal_points as $key => $point ) {
							self::render_focal_point_item( $key, $point );
						}
					}
					?>
				</div>
				
				<button type="button" class="button timu-add-focal-point">
					<?php esc_html_e( 'Add Focal Point', 'timu' ); ?>
				</button>
				
				<button type="button" class="button button-primary timu-save-focal-points">
					<?php esc_html_e( 'Save Focal Points', 'timu' ); ?>
				</button>
				
				<button type="button" class="button timu-preview-crops">
					<?php esc_html_e( 'Preview Crops', 'timu' ); ?>
				</button>
				
				<div class="timu-status-message"></div>
			</div>
			
			<div class="timu-crop-preview-container" style="display:none;">
				<h4><?php esc_html_e( 'Crop Preview', 'timu' ); ?></h4>
				<div class="timu-crop-preview-list"></div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render a focal point item.
	 *
	 * @param string $key   The focal point key.
	 * @param array  $point The focal point data.
	 */
	private static function render_focal_point_item( $key, $point ) {
		?>
		<div class="timu-focal-point-item" data-key="<?php echo esc_attr( $key ); ?>">
			<div class="timu-focal-point-marker" 
			     style="left: <?php echo esc_attr( $point['x'] ); ?>%; top: <?php echo esc_attr( $point['y'] ); ?>%;">
			</div>
			<div class="timu-focal-point-info">
				<label>
					<?php esc_html_e( 'Key:', 'timu' ); ?>
					<input type="text" 
					       class="timu-focal-key" 
					       value="<?php echo esc_attr( $key ); ?>" 
					       readonly>
				</label>
				<label>
					<?php esc_html_e( 'Label:', 'timu' ); ?>
					<input type="text" 
					       class="timu-focal-label" 
					       value="<?php echo esc_attr( $point['label'] ); ?>" 
					       placeholder="<?php esc_attr_e( 'e.g., Main subject', 'timu' ); ?>">
				</label>
				<label>
					<?php esc_html_e( 'X:', 'timu' ); ?>
					<input type="number" 
					       class="timu-focal-x" 
					       value="<?php echo esc_attr( $point['x'] ); ?>" 
					       min="0" 
					       max="100" 
					       step="0.1">
				</label>
				<label>
					<?php esc_html_e( 'Y:', 'timu' ); ?>
					<input type="number" 
					       class="timu-focal-y" 
					       value="<?php echo esc_attr( $point['y'] ); ?>" 
					       min="0" 
					       max="100" 
					       step="0.1">
				</label>
				<button type="button" class="button timu-remove-focal-point">
					<?php esc_html_e( 'Remove', 'timu' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Add focal point fields to media modal.
	 *
	 * @param array   $form_fields The form fields.
	 * @param WP_Post $post        The attachment post object.
	 * @return array Modified form fields.
	 */
	public static function add_focal_point_fields( $form_fields, $post ) {
		// Only for images.
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $form_fields;
		}
		
		$focal_points = timu_get_focal_points( $post->ID );
		$count        = count( $focal_points );
		
		$form_fields['timu_focal_points'] = array(
			'label' => __( 'Focal Points', 'timu' ),
			'input' => 'html',
			'html'  => sprintf(
				'<p>%s</p><p><a href="%s" class="button">%s</a></p>',
				sprintf(
					/* translators: %d: Number of focal points */
					esc_html( _n( '%d focal point defined', '%d focal points defined', $count, 'timu' ) ),
					$count
				),
				esc_url( get_edit_post_link( $post->ID ) . '#timu_focal_points' ),
				esc_html__( 'Edit Focal Points', 'timu' )
			),
		);
		
		return $form_fields;
	}
	
	/**
	 * Save focal point fields (placeholder for media modal).
	 *
	 * @param array $post       The attachment post array.
	 * @param array $attachment The attachment data.
	 * @return array The attachment post array.
	 */
	public static function save_focal_point_fields( $post, $attachment ) {
		// Actual saving is handled via AJAX.
		return $post;
	}
	
	/**
	 * AJAX handler to save focal points.
	 */
	public static function ajax_save_focal_points() {
		// Verify nonce.
		check_ajax_referer( 'timu_focal_point_nonce', 'nonce' );
		
		// Get attachment ID.
		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'timu' ) ) );
		}
		
		// Check permissions.
		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this attachment.', 'timu' ) ) );
		}
		
		// Get focal points data.
		$focal_points = isset( $_POST['focal_points'] ) ? json_decode( stripslashes( $_POST['focal_points'] ), true ) : array();
		
		// Save focal points.
		$result = timu_set_focal_points( $attachment_id, $focal_points );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Focal points saved successfully.', 'timu' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save focal points.', 'timu' ) ) );
		}
	}
	
	/**
	 * AJAX handler to get focal points.
	 */
	public static function ajax_get_focal_points() {
		// Verify nonce.
		check_ajax_referer( 'timu_focal_point_nonce', 'nonce' );
		
		// Get attachment ID.
		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'timu' ) ) );
		}
		
		// Get focal points.
		$focal_points = timu_get_focal_points( $attachment_id );
		
		wp_send_json_success( array( 'focal_points' => $focal_points ) );
	}
	
	/**
	 * AJAX handler to preview crops.
	 */
	public static function ajax_preview_crops() {
		// Verify nonce.
		check_ajax_referer( 'timu_focal_point_nonce', 'nonce' );
		
		// Get parameters.
		$attachment_id    = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		$focal_point_key  = isset( $_POST['focal_point_key'] ) ? sanitize_key( $_POST['focal_point_key'] ) : '';
		
		if ( ! $attachment_id || empty( $focal_point_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'timu' ) ) );
		}
		
		// Get crop previews.
		$previews = timu_preview_focal_point_crops( $attachment_id, $focal_point_key, 800 );
		
		if ( is_wp_error( $previews ) ) {
			wp_send_json_error( array( 'message' => $previews->get_error_message() ) );
		}
		
		wp_send_json_success( array( 'previews' => $previews ) );
	}
}

// Initialize the editor.
TIMU_Focal_Point_Editor::init();
