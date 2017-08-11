<?php
/*
Plugin Name: Disclosure Picker
Plugin URI: http://joshuawagneronline.com/disclosure-picker/
Description: Adds an option in pages and posts to append a disclosure to keep in line with the FTC guidelines for blogging.
Version: 1.1
Author: Josh Wagner
Author URI: http://joshuawagneronline.com/
Author Email: josh@joshuawagneronline.com
License:

  Copyright 2012 Josh Wagner (josh@joshuawagneronline.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

class DisclosurePicker {
	 
	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	
	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {
		
		// Load plugin text domain
		add_action( 'init', array( $this, 'disclosure_picker_textdomain' ) );

		// Register admin styles and scripts
		//add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) ); // May add this later
		//add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) ); // May add scripts later
	
		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		//add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_scripts' ) ); // May add scripts later 
	
		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( $this, 'uninstall' ) );
		
	    /*
	     * Set up the actions and filters for the Disclosure Picker Plugin
	     */
            
	    add_action( 'add_meta_boxes', array( $this, 'add_disclosure_meta_box' ) );
            add_action( 'save_post', array( $this, 'disclosure_picker_save_postdata' ) );
	    add_filter( 'the_content', array( $this, 'add_disclosure_content' ) );

	} // end constructor
	
	/**
	 * Fired when the plugin is activated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function activate( $network_wide ) {
		// TODO:	Define activation functionality here
            // pull all posts
            $allposts = get_posts('numberposts=-1&post_type=post&post_status=any');
            
            // loop through and setup the post meta
            foreach( $allposts as $postinfo) {
                
                /**
                 * If the disclosure_dropdown meta doesn't exist, initialize with option 'None'
                 * Otherwise, do nothing. Will save data if this is a reactivation.
                 */ 
                if ( ! get_post_meta($postinfo->ID, 'disclosure_dropdown', true) ) {
                    add_post_meta($postinfo->ID, 'disclosure_dropdown', 'None');
                }
            } //end foreach
	} // end activate
	
	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function deactivate( $network_wide ) {
		// TODO:	Define deactivation functionality here		
	} // end deactivate
	
	/**
	 * Fired when the plugin is uninstalled.
	 * Deletes all post meta in the disclosure_dropdown key
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function uninstall( $network_wide ) {
            // pull all posts
            $allposts = get_posts('numberposts=-1&post_type=post&post_status=any');
            
            // loop through and delete the post meta
            foreach( $allposts as $postinfo) {
                delete_post_meta($postinfo->ID, 'disclosure_dropdown');
            }
	} // end uninstall

	/**
	 * Loads the plugin text domain for translation
	 */
	public function disclosure_picker_textdomain() {
            
		load_plugin_textdomain( 'disclosure-picker-locale', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		
	} // end disclosure_picker_textdomain

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {
	
		wp_enqueue_style( 'disclosure-picker-admin-styles', plugins_url( 'disclosure-picker/css/admin.css' ) );
	
	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */	
	public function register_admin_scripts() {
	
		wp_enqueue_script( 'disclosure-picker-admin-script', plugins_url( 'disclosure-picker/js/admin.js' ) );
	
	} // end register_admin_scripts
	
	/**
	 * Registers and enqueues plugin-specific styles.
	 */
	public function register_plugin_styles() {
	
		wp_enqueue_style( 'disclosure-picker-plugin-styles', plugins_url( 'disclosure-picker/css/display.css' ) );
	
	} // end register_plugin_styles
	
	/**
	 * Registers and enqueues plugin-specific scripts.
	 */
	public function register_plugin_scripts() {
	
		wp_enqueue_script( 'disclosure-picker-plugin-script', plugins_url( 'disclosure-picker/js/display.js' ) );
	
	} // end register_plugin_scripts
	
	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/
	
	/**
	 * Creates the meta box in the edit admin of posts and pages
	 */
        function add_disclosure_meta_box() {
            add_meta_box( 
                'disclosure_sectionid',
                __( 'Add Disclosure to Post', 'disclosure-picker-locale' ),
                array( &$this, 'render_disclosure_meta_box' ),
                'post' 
            );
            add_meta_box(
                'disclosure_sectionid',
                __( 'Add Disclosure to Page', 'disclosure-picker-locale' ), 
                array( &$this, 'render_disclosure_meta_box' ),
                'page'
            );
        }
        
        /**
         * Renders the actual meta box in the admin.
         * Uses HTML form dropdown with all the acceptable options.
         *
         * @param       $post       The post object from inside The Loop
         */ 
        function render_disclosure_meta_box( $post ) {
            // Use nonce for verification
            wp_nonce_field( plugin_basename( __FILE__ ), 'disclosure_picker_noncename' );
            
            /**
             * Get the $post->ID once and store in a variable
             * No need to call multiple times later
             */ 
            $post_ID = $post->ID;
            
            // The actual fields for data entry
            echo '<p><label for="disclosure_dropdown">';
               _e("Choose your disclosure:", 'disclosure-picker-locale' );
            echo '</label> ';
            echo '<input type="hidden" id="disclosure_dropdown" name="disclosure_dropdown" />';
            
            // Close php to start using HTML for the dropdown proper
            ?>
            
            <select name="disclosure_dropdown" id="disclosure_dropdown">
                <option<?php selected( get_post_meta($post_ID, 'disclosure_dropdown', true), __( 'None', 'disclosure-picker-locale' ) ); ?>><?php _e( "None", 'disclosure-picker-locale' ); ?></option>
                <option<?php selected( get_post_meta($post_ID, 'disclosure_dropdown', true), __( 'No Material Connection', 'disclosure-picker-locale' ) ); ?>><?php _e( "No Material Connection", 'disclosure-picker-locale' ); ?></option>
                <option<?php selected( get_post_meta($post_ID, 'disclosure_dropdown', true), __( 'Affiliate Links', 'disclosure-picker-locale' ) ); ?>><?php _e( "Affiliate Links", 'disclosure-picker-locale' ); ?></option>
                <option<?php selected( get_post_meta($post_ID, 'disclosure_dropdown', true), __( 'Review or Sample Copy', 'disclosure-picker-locale' ) ); ?>><?php _e( "Review or Sample Copy", 'disclosure-picker-locale' ); ?></option>
                <option<?php selected( get_post_meta($post_ID, 'disclosure_dropdown', true), __( 'Sponsored Post', 'disclosure-picker-locale' ) ); ?>><?php _e( "Sponsored Post", 'disclosure-picker-locale' ); ?></option>
                <option<?php selected( get_post_meta($post_ID, 'disclosure_dropdown', true), __( 'Employee Relationship', 'disclosure-picker-locale' ) ); ?>><?php _e( "Employee Relationship", 'disclosure-picker-locale' ); ?></option>
            </select>
	    
	    <?php
	    echo '</p>';
	    
	    // The fields for the position
	    echo '<p><label for="disclosure_position">';
               _e("Choose position for disclosure:", 'disclosure-picker-locale' );
            echo '</label> ';
            echo '<input type="hidden" id="disclosure_position" name="disclosure_position" />';
	    
	    // HTML for the position dropdown
	    ?>
	    
	    <select name="disclosure_position" id="disclosure_position">
		<option<?php selected( get_post_meta($post_ID, 'disclosure_position', true), __( 'Above the Content', 'disclosure-picker-locale' ) ); ?>><?php _e( "Above the Content", 'disclosure-picker-locale' ); ?></option>
		<option<?php selected( get_post_meta($post_ID, 'disclosure_position', true), __( 'Below the Content', 'disclosure-picker-locale' ) ); ?>><?php _e( "Below the Content", 'disclosure-picker-locale' ); ?></option>
	    </select>
            
            <?php
	    echo '</p>';
            // opening php after HTML for form is done
	} // end render_disclosure_meta_box
        
        /**
 	 * Saves post meta
	 */
	function disclosure_picker_save_postdata() {
            // verify if this is an auto save routine. 
            // If it is our form has not been submitted, so we dont want to do anything
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
                return;
        
            // verify this came from the our screen and with proper authorization,
            // because save_post can be triggered at other times
        
            if ( ! wp_verify_nonce( $_POST['disclosure_picker_noncename'], plugin_basename( __FILE__ ) ) )
                return;
          
            // Check permissions
            if ( 'page' == $_POST['post_type'] ) 
            {
                if ( ! current_user_can( 'edit_page', $post_id ) )
                    return;
            }
            else
            {
                if ( ! current_user_can( 'edit_post', $post_id ) )
                    return;
            }
        
            // OK, we're authenticated: we need to find and save the data
        
            //if saving in a custom table, get post_ID
            $post_ID = $_POST['post_ID'];
            $disclosure_dropdown = $_POST['disclosure_dropdown'];
	    $disclosure_position = $_POST['disclosure_position'];
            
	    // Set disclosure
            if ( get_post_meta( $post_ID, 'disclosure_dropdown', false ) )
                update_post_meta( $post_ID, 'disclosure_dropdown', $disclosure_dropdown );
            else
                add_post_meta( $post_ID, 'disclosure_dropdown', $disclosure_dropdown );
	    // Set position
	    if ( get_post_meta( $post_ID, 'disclosure_position', false ) )
                update_post_meta( $post_ID, 'disclosure_position', $disclosure_position );
            else
                add_post_meta( $post_ID, 'disclosure_position', $disclosure_position );
	} // end disclosure_picker_save_postdata
	
	/**
	 * Actually renders the disclosure content after the WP content.
	 * Basically, we're adding an HTML section to the tail end of the content
	 * containing the disclosure the user has decided upon in the post or
	 * page edit screen.
	 * 
	 * @param       $content        The content object of the post we are working with
	 */
	function add_disclosure_content( $content ) {
            global $post; // grabbing the $post object from the current query
            $post_ID = $post->ID;
            $disclosure_pick = get_post_meta($post_ID, 'disclosure_dropdown', true); // Find which disclosure is wanted
	    $disclosure_position_pick = get_post_meta($post_ID, 'disclosure_position', true); // Find desired position
            
            // Set up array that contains the picks and the actual text those picks represent
            $disclosure_options = array(
                                            __( 'None', 'disclosure-picker-locale' )                      =>   '',
                                            __( 'No Material Connection', 'disclosure-picker-locale' )    =>   __( 'Disclosure of Material Connection: I have not received any compensation for writing this post. I have no material connection to the brands, products, or services that I have mentioned. I am disclosing this in accordance with the Federal Trade Commission\'s <a href="http://www.access.gpo.gov/nara/cfr/waisidx_03/16cfr255_03.html" target="_blank">16 CFR, Part 255</a>: "Guides Concerning the Use of Endorsements and Testimonials in Advertising."', 'disclosure-picker-locale' ),
                                            __( 'Affiliate Links', 'disclosure-picker-locale' )           =>   __( 'Disclosure of Material Connection: Some of the links in the post above are "affiliate links." This means if you click on the link and purchase the item, I will receive an affiliate commission. Regardless, I only recommend products or services I use personally and believe will add value to my readers. I am disclosing this in accordance with the Federal Trade Commission\'s <a href="http://www.access.gpo.gov/nara/cfr/waisidx_03/16cfr255_03.html" target="_blank">16 CFR, Part 255</a>: "Guides Concerning the Use of Endorsements and Testimonials in Advertising."', 'disclosure-picker-locale' ),
                                            __( 'Review or Sample Copy', 'disclosure-picker-locale' )     =>   __( 'Disclosure of Material Connection: I received one or more of the products or services mentioned above for free in the hope that I would mention it on my blog. Regardless, I only recommend products or services I use personally and believe will be good for my readers. I am disclosing this in accordance with the Federal Trade Commission\'s <a href="http://www.access.gpo.gov/nara/cfr/waisidx_03/16cfr255_03.html" target="_blank">16 CFR, Part 255</a>: "Guides Concerning the Use of Endorsements and Testimonials in Advertising."', 'disclosure-picker-locale' ),
                                            __( 'Sponsored Post', 'disclosure-picker-locale' )            =>   __( 'Disclosure of Material Connection: This is a "sponsored post." The company who sponsored it compensated me via a cash payment, gift, or something else of value to write it. Regardless, I only recommend products or services I use personally and believe will be good for my readers. I am disclosing this in accordance with the Federal Trade Commission\'s <a href="http://www.access.gpo.gov/nara/cfr/waisidx_03/16cfr255_03.html" target="_blank">16 CFR, Part 255</a>: "Guides Concerning the Use of Endorsements and Testimonials in Advertising."', 'disclosure-picker-locale' ),
                                            __( 'Employee Relationship', 'disclosure-picker-locale' )     =>   __( 'Disclosure of Material Connection: I am employed by a company that in involved with this product or service. Regardless, I only recommend products or services that I use personally and believe will be good for my readers. I am disclosing this in accordance with the Federal Trade Commission\'s <a href="http://www.access.gpo.gov/nara/cfr/waisidx_03/16cfr255_03.html" target="_blank">16 CFR, Part 255</a>: "Guides Concerning the Use of Endorsements and Testimonials in Advertising."', 'disclosure-picker-locale' )
                                            );
            
            /**
             * Set up the HTML formatted text to be used.
             * If the user picks the "None" option, we don't need to add any content to the end.
             */
            if ( $disclosure_options[$disclosure_pick] != '' ) {
                // HTML includes class="disclosure" inside a <small></small> tags for formatting and purpose
                $disclosure_addition = '<p class="disclosure well well-small"><small>' . $disclosure_options[$disclosure_pick] . '</small></p>';
            
                if( is_single() || is_page() ) { // Only render in single posts or pages, not in any index or archive
		    // Add to content based on desired position
                    if ( $disclosure_position_pick == 'Below the Content' )
			$content = $content . $disclosure_addition;
		    elseif ( $disclosure_position_pick == 'Above the Content' )
			$content = $disclosure_addition . $content;
                }
            }
            
            return $content;
	} // end add_disclosure_content
  
} // end class

$plugin_name = new DisclosurePicker();