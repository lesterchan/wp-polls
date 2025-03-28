<?php
/**
 * Frontend Poll Creation Functionality
 *
 * @package WP-Polls
 * @since 2.79.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend Poll Creation Class
 *
 * Handles all frontend poll creation functionality.
 *
 * @package WP-Polls
 * @since 2.79.0
 */
class WP_Polls_Frontend {

    /**
     * Initialize the frontend functionality.
     *
     * @since 2.79.0
     * @return void
     */
    public static function init() {
        // Register shortcodes
        add_shortcode( 'polls_create', array( __CLASS__, 'display_poll_creation_form' ) );
        
        // Register AJAX handlers
        add_action( 'wp_ajax_polls_create', array( __CLASS__, 'process_poll_creation' ) );
        add_action( 'wp_ajax_nopriv_polls_create', array( __CLASS__, 'handle_unauthorized_creation' ) );
        
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
    }
    
    /**
     * Enqueue scripts and styles for frontend poll creation.
     *
     * @since 2.79.0
     * @return void
     */
    public static function enqueue_scripts() {
        // Only enqueue on pages with the shortcode
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'polls_create' ) ) {
            wp_enqueue_style( 'polls-frontend-css', WP_POLLS_PLUGIN_URL . '/polls-frontend.css', array(), WP_POLLS_VERSION );
            wp_enqueue_script( 'polls-frontend-js', WP_POLLS_PLUGIN_URL . '/polls-frontend.js', array( 'jquery', 'jquery-ui-sortable' ), WP_POLLS_VERSION, true );
            
            wp_localize_script( 'polls-frontend-js', 'pollsCreateL10n', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'polls-create-nonce' ),
                'error_question' => __( 'Please enter a poll question.', 'wp-polls' ),
                'error_answers' => __( 'Please enter at least two poll answers.', 'wp-polls' ),
                'success_message' => __( 'Your poll has been created successfully!', 'wp-polls' ),
                'error_message' => __( 'There was an error creating your poll. Please try again.', 'wp-polls' ),
                'confirm_delete' => __( 'Are you sure you want to delete this answer?', 'wp-polls' ),
            ) );
        }
    }
    
    /**
     * Display the poll creation form.
     *
     * @since 2.79.0
     * @param array $atts Shortcode attributes.
     * @return string Poll creation form HTML.
     */
    public static function display_poll_creation_form( $atts ) {
        $atts = shortcode_atts( array(
            'title' => __( 'Create a New Poll', 'wp-polls' ),
            'login_message' => __( 'You must be logged in to create a poll.', 'wp-polls' ),
            'require_login' => 'yes',
        ), $atts, 'polls_create' );
        
        // Check if user needs to be logged in
        if ( 'yes' === $atts['require_login'] && ! is_user_logged_in() ) {
            return '<div class="polls-frontend-message polls-login-message">' . esc_html( $atts['login_message'] ) . '</div>';
        }
        
        ob_start();
        ?>
        <div class="polls-frontend-container">
            <h2 class="polls-frontend-title"><?php echo esc_html( $atts['title'] ); ?></h2>
            
            <div class="polls-frontend-message" style="display: none;"></div>
            
            <form id="polls-create-form" class="polls-frontend-form">
                <?php wp_nonce_field( 'polls-create-nonce', 'polls_create_nonce' ); ?>
                
                <div class="polls-frontend-section">
                    <h3><?php esc_html_e( 'Poll Question', 'wp-polls' ); ?></h3>
                    <div class="polls-frontend-field">
                        <label for="poll-question"><?php esc_html_e( 'Question', 'wp-polls' ); ?></label>
                        <input type="text" id="poll-question" name="poll_question" placeholder="<?php esc_attr_e( 'Enter your poll question here', 'wp-polls' ); ?>" required>
                    </div>
                    
                    <div class="polls-frontend-field">
                        <label for="poll-type"><?php esc_html_e( 'Poll Type', 'wp-polls' ); ?></label>
                        <select id="poll-type" name="poll_type">
                            <option value="standard"><?php esc_html_e( 'Standard Poll (Radio Buttons)', 'wp-polls' ); ?></option>
                            <option value="multiple"><?php esc_html_e( 'Multiple Choice (Checkboxes)', 'wp-polls' ); ?></option>
                            <option value="ranked"><?php esc_html_e( 'Ranked Choice (Drag to rank)', 'wp-polls' ); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="polls-frontend-section" id="poll-multiple-options" style="display: none;">
                    <h3><?php esc_html_e( 'Multiple Choice Options', 'wp-polls' ); ?></h3>
                    <div class="polls-frontend-field">
                        <label for="poll-multiple-max"><?php esc_html_e( 'Maximum Number of Choices', 'wp-polls' ); ?></label>
                        <select id="poll-multiple-max" name="poll_multiple_max">
                            <?php for ( $i = 2; $i <= 10; $i++ ) : ?>
                                <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="polls-frontend-section">
                    <h3><?php esc_html_e( 'Poll Answers', 'wp-polls' ); ?></h3>
                    <div id="poll-answers-container">
                        <div class="poll-answer-row" data-id="1">
                            <span class="poll-answer-number">1</span>
                            <input type="text" name="poll_answers[]" placeholder="<?php esc_attr_e( 'Enter answer here', 'wp-polls' ); ?>" required>
                            <button type="button" class="poll-answer-remove" style="visibility: hidden;">&times;</button>
                        </div>
                        <div class="poll-answer-row" data-id="2">
                            <span class="poll-answer-number">2</span>
                            <input type="text" name="poll_answers[]" placeholder="<?php esc_attr_e( 'Enter answer here', 'wp-polls' ); ?>" required>
                            <button type="button" class="poll-answer-remove" style="visibility: hidden;">&times;</button>
                        </div>
                    </div>
                    
                    <button type="button" id="poll-add-answer" class="button"><?php esc_html_e( 'Add Another Answer', 'wp-polls' ); ?></button>
                </div>
                
                <div class="polls-frontend-section">
                    <h3><?php esc_html_e( 'Poll Duration', 'wp-polls' ); ?></h3>
                    <div class="polls-frontend-field">
                        <input type="checkbox" id="poll-expiry" name="poll_expiry">
                        <label for="poll-expiry"><?php esc_html_e( 'Set an end date for this poll', 'wp-polls' ); ?></label>
                    </div>
                    
                    <div id="poll-expiry-options" style="display: none;">
                        <div class="polls-frontend-field">
                            <label for="poll-expiry-days"><?php esc_html_e( 'Poll Duration (in days)', 'wp-polls' ); ?></label>
                            <select id="poll-expiry-days" name="poll_expiry_days">
                                <?php for ( $i = 1; $i <= 30; $i++ ) : ?>
                                    <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="polls-frontend-submit">
                    <button type="submit" id="poll-submit" class="button button-primary"><?php esc_html_e( 'Create Poll', 'wp-polls' ); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Process poll creation form submission.
     *
     * @since 2.79.0
     * @return void Sends JSON response.
     */
    public static function process_poll_creation() {
        check_ajax_referer( 'polls-create-nonce', 'nonce' );
        
        // Check if user is allowed to create polls
        if ( ! self::user_can_create_polls() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to create polls.', 'wp-polls' ),
            ) );
        }
        
        // Get and sanitize form data
        $question = isset( $_POST['poll_question'] ) ? sanitize_text_field( wp_unslash( $_POST['poll_question'] ) ) : '';
        $poll_type = isset( $_POST['poll_type'] ) ? sanitize_key( $_POST['poll_type'] ) : 'standard';
        $poll_answers = isset( $_POST['poll_answers'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['poll_answers'] ) ) : array();
        
        // Filter empty answers
        $poll_answers = array_filter( $poll_answers, 'strlen' );
        
        // Validate required fields
        if ( empty( $question ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a poll question.', 'wp-polls' ),
            ) );
        }
        
        if ( count( $poll_answers ) < 2 ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter at least two poll answers.', 'wp-polls' ),
            ) );
        }
        
        // Set up poll data
        $poll_data = array(
            'question'  => $question,
            'timestamp' => current_time( 'timestamp' ),
            'active'    => 1,
            'answers'   => $poll_answers,
        );
        
        // Add poll type (standard, multiple, ranked)
        if ( 'multiple' === $poll_type ) {
            $max_choices = isset( $_POST['poll_multiple_max'] ) ? intval( $_POST['poll_multiple_max'] ) : 2;
            $poll_data['multiple'] = min( max( 2, $max_choices ), count( $poll_answers ) );
        } else {
            $poll_data['multiple'] = 0;
        }
        
        // Store type in database
        $poll_data['type'] = $poll_type;
        
        // Add expiry date if set
        if ( isset( $_POST['poll_expiry'] ) && 'on' === $_POST['poll_expiry'] ) {
            $expiry_days = isset( $_POST['poll_expiry_days'] ) ? intval( $_POST['poll_expiry_days'] ) : 7;
            $poll_data['expiry'] = current_time( 'timestamp' ) + ( $expiry_days * DAY_IN_SECONDS );
        } else {
            $poll_data['expiry'] = 0;
        }
        
        // Create the poll
        $poll_id = WP_Polls_Data::add_poll( $poll_data );
        
        if ( $poll_id ) {
            // Track this poll as created by current user
            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $user_polls = get_user_meta( $user_id, 'wp_polls_created', true );
                
                if ( empty( $user_polls ) || ! is_array( $user_polls ) ) {
                    $user_polls = array();
                }
                
                // Add this poll to the user's polls
                $user_polls[] = $poll_id;
                
                // Update user meta
                update_user_meta( $user_id, 'wp_polls_created', $user_polls );
            }
            
            // Poll created successfully
            wp_send_json_success( array(
                'message' => __( 'Your poll has been created successfully!', 'wp-polls' ),
                'poll_id' => $poll_id,
                'html'    => do_shortcode( '[poll id="' . $poll_id . '"]' ),
            ) );
        } else {
            // Error creating poll
            wp_send_json_error( array(
                'message' => __( 'There was an error creating your poll. Please try again.', 'wp-polls' ),
            ) );
        }
    }
    
    /**
     * Handle unauthorized poll creation.
     *
     * @since 2.79.0
     * @return void Sends JSON response.
     */
    public static function handle_unauthorized_creation() {
        wp_send_json_error( array(
            'message' => __( 'You must be logged in to create a poll.', 'wp-polls' ),
            'login_url' => wp_login_url( add_query_arg( array() ) ),
        ) );
    }
    
    /**
     * Check if the current user can create polls.
     *
     * @since 2.79.0
     * @return bool True if user can create polls, false otherwise.
     */
    public static function user_can_create_polls() {
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return false;
        }
        
        // Get the user's capabilities
        $user = wp_get_current_user();
        
        // Allow admins and editors to create polls by default
        if ( user_can( $user, 'manage_polls' ) || user_can( $user, 'edit_posts' ) ) {
            return true;
        }
        
        // Allow filtering for custom roles
        return apply_filters( 'wp_polls_user_can_create_polls', false, $user );
    }
}

// Initialize frontend functionality
add_action( 'plugins_loaded', array( 'WP_Polls_Frontend', 'init' ) );
