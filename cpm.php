<?php
/**
 * Plugin Name: WP Project Manager PRO
 * Plugin URI: http://wedevs.com/plugin/wp-project-manager/
 * Description: A WordPress Project Management plugin. Simply it does everything and it was never been easier with WordPress.
 * Author: Tareq Hasan
 * Author URI: http://tareq.weDevs.com
 * Version: 0.5.3
 * License: GPL2
 */
/**
 * Copyright (c) 2013 Tareq Hasan (email: tareq@wedevs.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */
/**
 * Autoload class files on demand
 *
 * @param string $class requested class name
 */
function cpm_autoload( $class ) {
    $name = explode( '_', $class );
    if ( isset( $name[1] ) ) {
        $class_name = strtolower( $name[1] );
        $filename = dirname( __FILE__ ) . '/class/' . $class_name . '.php';
        if ( file_exists( $filename ) ) {
            require_once $filename;
        }
    }
}

spl_autoload_register( 'cpm_autoload' );

require_once dirname( __FILE__ ) . '/includes/functions.php';

/**
 * Project Manager bootstrap class
 *
 * @author Tareq Hasan
 */
class WeDevs_CPM {

    function __construct() {

        $this->version = '0.5';
        $this->db_version = '0.5';

        $this->constants();
        $this->instantiate();

        add_action( 'admin_menu', array($this, 'admin_menu') );
        add_action( 'admin_init', array($this, 'admin_includes') );
        add_action( 'plugins_loaded', array($this, 'load_textdomain') );
        register_activation_hook( __FILE__, array($this, 'install') );
    }

    /**
     * Instantiate all the required classes
     *
     * @since 0.1
     */
    function instantiate() {
        CPM_Project::getInstance();
        CPM_Message::getInstance();
        CPM_Task::getInstance();
        CPM_Milestone::getInstance();

        new CPM_Activity();
        new CPM_Ajax();
        new CPM_Notification();

        // instantiate admin settings only on admin page
        if ( is_admin() ) {
            new CPM_Admin();
            new CPM_Updates();
        }
    }

    /**
     * Runs the setup when the plugin is installed
     *
     * @since 0.3.1
     */
    function install() {

        $this->plugin_upgrades();

        update_option( 'cpm_version', $this->version );
        update_option( 'cpm_db_version', $this->db_version );
    }


    /**
     * Do upgrade tasks
     *
     * @return void
     */
    function plugin_upgrades() {

        $version = get_option( 'cpm_db_version' );

        if ( version_compare( $this->db_version, $version, '<=' ) ) {
            return;
        }

        switch ($this->db_version) {
            case '0.5':
                $upgrade = new CPM_Upgrade();
                $upgrade->upgrade_to_0_5();
                break;

            default:
                break;
        }
    }


    /**
     * Load plugin textdomain
     *
     * @since 0.3
     */
    function load_textdomain() {
        load_plugin_textdomain( 'cpm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Define some constants required by the plugin
     *
     * @since 0.1
     */
    function constants() {
        define( 'CPM_PLUGIN_PATH', dirname( __FILE__ ) );
        define( 'CPM_PLUGIN_URI', plugins_url( '', __FILE__ ) );
    }

    /**
     * Load all the plugin scripts and styles only for the
     * project area
     *
     * @since 0.1
     */
    static function admin_scripts() {
        $upload_size = intval( cpm_get_option( 'upload_limit') ) * 1024 * 1024;

        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-autocomplete');
        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'cpm_prettyPhoto', plugins_url( 'assets/js/jquery.prettyPhoto.js', __FILE__ ), array( 'jquery' ), false, true );
        wp_enqueue_script( 'chosen', plugins_url( 'assets/js/chosen.jquery.min.js', __FILE__ ), array('jquery'), false, true );
        wp_enqueue_script( 'validate', plugins_url( 'assets/js/jquery.validate.min.js', __FILE__ ), array('jquery'), false, true );
        wp_enqueue_script( 'plupload-handlers' );
        wp_enqueue_script( 'cpm_admin', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery', 'cpm_prettyPhoto' ), false, true );
        wp_enqueue_script( 'cpm_task', plugins_url( 'assets/js/task.js', __FILE__ ), array('jquery'), false, true );
        wp_enqueue_script( 'cpm_uploader', plugins_url( 'assets/js/upload.js', __FILE__ ), array('jquery', 'plupload-handlers'), false, true );
        
        wp_localize_script( 'cpm_admin', 'CPM_Vars', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cpm_nonce' ),
            'is_admin' => is_admin() ? 'yes' : 'no',
            'plupload' => array(
                'browse_button' => 'cpm-upload-pickfiles',
                'container' => 'cpm-upload-container',
                'max_file_size' => $upload_size . 'b',
                'url' => admin_url( 'admin-ajax.php' ) . '?action=cpm_ajax_upload&nonce=' . wp_create_nonce( 'cpm_ajax_upload' ),
                'flash_swf_url' => includes_url( 'js/plupload/plupload.flash.swf' ),
                'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
                'filters' => array(array('title' => __( 'Allowed Files' ), 'extensions' => '*')),
                'resize' => array('width' => (int) get_option( 'large_size_w' ), 'height' => (int) get_option( 'large_size_h' ), 'quality' => 100)
            )
        ) );
    
        wp_enqueue_style( 'cpm_admin', plugins_url( 'assets/css/admin.css', __FILE__ ) );
        wp_enqueue_style( 'cpm_prettyPhoto', plugins_url( 'assets/css/prettyPhoto.css', __FILE__ ) );
        wp_enqueue_style( 'jquery-ui', plugins_url( 'assets/css/jquery-ui-1.9.1.custom.css', __FILE__ ) );
        wp_enqueue_style( 'chosen', plugins_url( 'assets/css/chosen.css', __FILE__ ) );
    
    }

    static function my_task_scripts() {
        self::admin_scripts();
        wp_enqueue_script( 'cpm_mytask', plugins_url( 'assets/js/mytask.js', __FILE__ ), array('jquery', 'cpm_task'), false, true );  
    }

    static function calender_scripts() {
        self::admin_scripts();

        wp_enqueue_script( 'fullcalendar', plugins_url( 'assets/js/fullcalendar.min.js', __FILE__ ), array('jquery'), false, true );
        wp_enqueue_style( 'fullcalendar', plugins_url( 'assets/css/fullcalendar.css', __FILE__ ) );*/
    }

    /**
     * Includes some required helper files
     *
     * @since 0.1
     */
    function admin_includes() {
        require_once CPM_PLUGIN_PATH . '/includes/urls.php';
        require_once CPM_PLUGIN_PATH . '/includes/html.php';
        require_once CPM_PLUGIN_PATH . '/includes/shortcodes.php';
    }

    /**
     * Register the plugin menu
     *
     * @since 0.1
     */
    function admin_menu() {
        $capability = 'read'; //minimum level: subscriber

        $count_task = CPM_Task::getInstance()->mytask_count();
        $current_task = isset( $count_task['current_task'] ) ? $count_task['current_task'] : 0;
        $outstanding = isset( $count_task['outstanding'] ) ? $count_task['outstanding'] : 0;
        $active_task =  $current_task + $outstanding;

        $mytask_text = __( 'My Tasks', 'cpm' );
        if ( $active_task ) {
            $mytask_text = sprintf( __( 'My Tasks %s', 'cpm' ), '<span class="awaiting-mod count-1"><span class="pending-count">' . $active_task . '</span></span>');
        }

        $hook = add_menu_page( __( 'Project Manager', 'cpm' ), __( 'Project Manager', 'cpm' ), $capability, 'cpm_projects', array($this, 'admin_page_handler'), 'dashicons-networking', 3 );
        add_submenu_page( 'cpm_projects', __( 'Projects', 'cpm' ), __( 'Projects', 'cpm' ), $capability, 'cpm_projects', array($this, 'admin_page_handler') );
        $hook_my_task = add_submenu_page( 'cpm_projects', __( 'My Tasks', 'cpm' ), $mytask_text, $capability, 'cpm_task', array($this, 'my_task') );
        $hook_calender = add_submenu_page( 'cpm_projects', __( 'Calendar', 'cpm' ), __( 'Calendar', 'cpm' ), $capability, 'cpm_calendar', array($this, 'admin_page_handler') );

        if ( current_user_can( 'manage_options' ) ) {
            add_submenu_page( 'cpm_projects', __( 'Categories', 'cpm' ), __( 'Categories', 'cpm' ), $capability, 'edit-tags.php?taxonomy=project_category' );
        }
        add_submenu_page( 'cpm_projects', __( 'Add-ons', 'cpm' ), __( 'Add-ons', 'cpm' ), 'manage_options', 'cpm_addons', array($this, 'admin_page_addons') );
        add_action( 'admin_print_styles-' . $hook, array($this, 'admin_scripts') );
        add_action( 'admin_print_styles-' . $hook_my_task, array($this, 'my_task_scripts') );
        add_action( 'admin_print_styles-' . $hook_calender, array($this, 'calender_scripts') );

    }

    /**
     * Render my tasks page
     *
     * @since 0.5
     * @return void
     */
    function my_task() {
        require_once dirname (__FILE__) . '/views/task/my-task.php';
    }

    /**
     * Main function that renders the admin area for all the project
     * related markup.
     *
     * @since 0.1
     */
    function admin_page_handler() {

        echo '<div class="wrap cpm">';

        $page = (isset( $_GET['page'] )) ? $_GET['page'] : '';
        $tab = (isset( $_GET['tab'] )) ? $_GET['tab'] : '';
        $action = (isset( $_GET['action'] )) ? $_GET['action'] : '';

        $project_id = (isset( $_GET['pid'] )) ? (int) $_GET['pid'] : 0;
        $message_id = (isset( $_GET['mid'] )) ? (int) $_GET['mid'] : 0;
        $tasklist_id = (isset( $_GET['tl_id'] )) ? (int) $_GET['tl_id'] : 0;
        $task_id = (isset( $_GET['task_id'] )) ? (int) $_GET['task_id'] : 0;
        $milestone_id = (isset( $_GET['ml_id'] )) ? (int) $_GET['ml_id'] : 0;

        $file_dir = dirname( __FILE__ );
        $file_dir = apply_filters( 'cpm_tab_file_dir', $file_dir );

        $default_file = dirname( __FILE__ ) . '/views/project/index.php';
        switch ($page) {
            case 'cpm_projects':

                switch ($tab) {
                    case 'settings':
                        switch( $action ) {
                            case 'index':
                                $file = dirname( __FILE__ ) . '/views/project/settings.php';
                                break;
                        }
                        break;
                    case 'project':

                        switch ($action) {
                            case 'index':
                                $file = dirname( __FILE__ ) . '/views/project/index.php';
                                break;

                            case 'single':
                                $file = dirname( __FILE__ ) . '/views/project/single.php';
                                break;

                            default:
                                $file = dirname( __FILE__ ) . '/views/project/index.php';
                                break;
                        }

                        break;

                    case 'message':
                        switch ($action) {
                            case 'index':
                                $file = dirname( __FILE__ ) . '/views/message/index.php';
                                break;

                            case 'single':
                                $file = dirname( __FILE__ ) . '/views/message/single.php';
                                break;

                            default:
                                $file = dirname( __FILE__ ) . '/views/message/index.php';
                                break;
                        }

                        break;

                    case 'task':
                        switch ($action) {
                            case 'index':
                                $file = dirname( __FILE__ ) . '/views/task/index.php';
                                break;

                            case 'single':
                                $file = dirname( __FILE__ ) . '/views/task/single.php';
                                break;

                            case 'task_single':
                                $file = dirname( __FILE__ ) . '/views/task/task-single.php';
                                break;

                            default:
                                $file = dirname( __FILE__ ) . '/views/task/index.php';
                                break;
                        }

                        break;

                    case 'milestone':
                        switch ($action) {
                            case 'index':
                                $file = dirname( __FILE__ ) . '/views/milestone/index.php';
                                break;

                            default:
                                $file = dirname( __FILE__ ) . '/views/milestone/index.php';
                                break;
                        }

                        break;

                    case 'files':
                        $file = dirname( __FILE__ ) . '/views/files/index.php';
                        break;


                    default:
                        $file = dirname( __FILE__ ) . '/views/project/index.php';
                        break;
                }
                break;

            case 'cpm_calendar':
                $file = $file_dir . '/views/calendar/index.php';
                break;

            default:
                break;
        }

        $file = apply_filters( 'cpm_tab_file', $file, $project_id, $page, $tab, $action );

        if ( file_exists( $file )) {
            require_once $file;
        } else {
            require_once $default_file;
        }

        echo '</div>';
    }

    function admin_page_addons() {
        include dirname( __FILE__ ) . '/includes/add-ons.php';
    }

}

$wedevs_cpm = new WeDevs_CPM();

/**
 * Add filters for text displays on Project Manager texts
 *
 * @since 0.4
 */
function cpm_content_filter() {
    add_filter( 'cpm_get_content', 'wptexturize' );
    add_filter( 'cpm_get_content', 'convert_smilies' );
    add_filter( 'cpm_get_content', 'convert_chars' );
    add_filter( 'cpm_get_content', 'wpautop' );
    add_filter( 'cpm_get_content', 'shortcode_unautop' );
    add_filter( 'cpm_get_content', 'prepend_attachment' );
}

add_action( 'plugins_loaded', 'cpm_content_filter' );
