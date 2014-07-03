<?php

/**
 * Task list manager class
 *
 * @author Tareq Hasan
 */
class CPM_Task {

    private static $_instance;

    public function __construct() {
        add_filter( 'init', array($this, 'register_post_type') );
        add_filter( 'cpm_task_complete_response', array($this, 'mytask_count'), 10, 4 );
        add_filter( 'cpm_task_open_response', array($this, 'mytask_count'), 10, 4 );

        add_action( 'cpm_after_new_task', array($this, 'mytask_flush_cache') );
        add_action( 'cpm_after_update_task', array($this, 'mytask_flush_cache') );
        add_action( 'cpm_delete_task_after', array($this, 'mytask_flush_cache') );
    }

    function task_count() {

        global $wpdb;

        $logged_in_user_id = get_current_user_id();
        
        $task = $wpdb->get_results(
            "SELECT du.meta_value as due_date, n.meta_value as complete_status
            FROM `$wpdb->posts` AS t
            LEFT JOIN $wpdb->posts AS tl ON tl.ID = t.post_parent
            LEFT JOIN $wpdb->posts AS p ON p.ID = tl.post_parent
            LEFT JOIN $wpdb->postmeta AS m ON m.post_id = t.ID
            LEFT JOIN $wpdb->postmeta AS n ON n.post_id = t.ID
            LEFT JOIN $wpdb->postmeta AS du ON du.post_id = t.ID
            WHERE t.post_type = 'task' AND t.post_status = 'publish' 
            AND m.meta_key = '_assigned' AND m.meta_value = $logged_in_user_id
            AND n.meta_key = '_completed' 
            AND du.meta_key = '_due' 
            AND p.post_title is not null" 

        );


        $counts = array( 'current_task' => 0, 'outstanding' => 0, 'complete' => 0 );
        foreach( $task as $key => $obj ) {

            if( ( empty( $obj->due_date ) || date( 'Y-m-d', strtotime( $obj->due_date ) ) >= date( 'Y-m-d', time() ) )  && $obj->complete_status != 1 ) {
                $counts['current_task'] += 1;
            }

            if( ! empty( $obj->due_date ) && date( 'Y-m-d', strtotime( $obj->due_date ) ) < date( 'Y-m-d', time() )  && $obj->complete_status != 1 ) {
                $counts['outstanding'] += 1;
            }

            if( $obj->complete_status == 1 ) {
                $counts['complete'] += 1;
            }

        }


        return $counts;
    }

    public static function getInstance() {
        if ( !self::$_instance ) {
            self::$_instance = new CPM_Task();
        }

        return self::$_instance;
    }

    function current_user_task( $user_id ) {
        global $wpdb;

        if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'outstanding' ) {

            $query1 = "AND n.meta_key = '_completed' AND n.meta_value = '0'";
            $query2 = "AND due.meta_value != '' AND STR_TO_DATE( due.meta_value, '%Y-%m-%d') < STR_TO_DATE( NOW(), '%Y-%m-%d')";
        } else if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'complete' ) {

            $query1 = "AND n.meta_key = '_completed' AND n.meta_value = '1'";
            $query2 = '';
        } else {
            $query1 = "AND n.meta_key = '_completed' AND n.meta_value = '0'";
            $query2 = "AND ( due.meta_value = '' OR STR_TO_DATE( due.meta_value, '%Y-%m-%d') >= STR_TO_DATE( NOW(), '%Y-%m-%d') ) ";
        }

        $que = "SELECT t.post_title as task, t.comment_count as comment_count, t.ID as task_id, tl.post_title as list, tl.ID as task_list_id, 
                    p.post_title as project_title, p.ID as project_id, m.meta_value as assigned_to, n.meta_value as completed, due.meta_value as due_date,
                    strday.meta_value as start_date
                FROM `$wpdb->posts` AS t
                LEFT JOIN $wpdb->posts AS tl ON t.post_parent = tl.ID
                LEFT JOIN $wpdb->posts AS p ON tl.post_parent = p.ID
                LEFT JOIN $wpdb->postmeta AS m ON m.post_id = t.ID
                LEFT JOIN $wpdb->postmeta AS n ON n.post_id = t.ID
                LEFT JOIN $wpdb->postmeta AS due ON due.post_id = t.ID
                LEFT JOIN $wpdb->postmeta AS strday ON strday.post_id = t.ID
                WHERE t.post_type = 'task' AND t.post_status = 'publish' 
                    AND m.meta_key = '_assigned' AND m.meta_value = $user_id
                    $query1
                    AND strday.meta_key = '_start'
                    AND due.meta_key = '_due' $query2
                    AND p.post_title is not null
                ORDER BY project_id DESC";


        $tasks = $wpdb->get_results( $que );

        $project = array();
        foreach ($tasks as $task) {
            $projects[$task->project_id]['tasks'][] = $task;
            $projects[$task->project_id]['title'] = $task->project_title;
        }
        $projects = isset( $projects ) ? $projects : '';

        return $projects;
    }

    
    /**
     * Counts my task
     * 
     * @param type $response
     * @param type $task_id
     * @param type $list_id
     * @param type $project_id
     * @return type
     */
    function mytask_count( $response = null, $task_id = null, $list_id = null, $project_id = null ) {
        $user_id = get_current_user_id();
        $cache_key = 'cpm_mytask_count_' . $user_id;
        $task = wp_cache_get( $cache_key );

        if ( $task === false ) {
            $task = $this->task_count();
            wp_cache_set( $cache_key, $task );
        }

        $response['current_task'] = $task['current_task'];
        $response['outstanding'] = $task['outstanding'];
        $response['complete'] = $task['complete'];

        return $response;
    }

    function get_mytasks( $user_id ) {

        $cache_key = 'cpm_mytask_' . $user_id;
        $project = wp_cache_get( $cache_key );

        if ( $project === false ) {
            $project = $this->current_user_task( $user_id );
            wp_cache_set( $cache_key, $project );
        }

        return $project;
    }

    function mytask_flush_cache( $task_id ) {
        $user_id = get_current_user_id();
        wp_cache_delete( 'cpm_mytask_' .$task_id.$user_id );
        wp_cache_delete( 'cpm_mytask_count_' .$task_id.$user_id );
    }

    function register_post_type() {
        register_post_type( 'task_list', array(
            'label' => __( 'Task List', 'cpm' ),
            'description' => __( 'Task List', 'cpm' ),
            'public' => false,
            'show_in_admin_bar' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_in_admin_bar' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => array('slug' => 'task-list'),
            'query_var' => true,
            'supports' => array('title', 'editor'),
            'labels' => array(
                'name' => __( 'Task List', 'cpm' ),
                'singular_name' => __( 'Task List', 'cpm' ),
                'menu_name' => __( 'Task List', 'cpm' ),
                'add_new' => __( 'Add Task List', 'cpm' ),
                'add_new_item' => __( 'Add New Task List', 'cpm' ),
                'edit' => __( 'Edit', 'cpm' ),
                'edit_item' => __( 'Edit Task List', 'cpm' ),
                'new_item' => __( 'New Task List', 'cpm' ),
                'view' => __( 'View Task List', 'cpm' ),
                'view_item' => __( 'View Task List', 'cpm' ),
                'search_items' => __( 'Search Task List', 'cpm' ),
                'not_found' => __( 'No Task List Found', 'cpm' ),
                'not_found_in_trash' => __( 'No Task List Found in Trash', 'cpm' ),
                'parent' => __( 'Parent Task List', 'cpm' ),
            ),
        ) );

        register_post_type( 'task', array(
            'label' => __( 'Task', 'cpm' ),
            'description' => __( 'Tasks', 'cpm' ),
            'public' => false,
            'show_in_admin_bar' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_in_admin_bar' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => array('slug' => 'task'),
            'query_var' => true,
            'supports' => array('title', 'editor'),
            'labels' => array(
                'name' => __( 'Tasks', 'cpm' ),
                'singular_name' => __( 'Task', 'cpm' ),
                'menu_name' => __( 'Task', 'cpm' ),
                'add_new' => __( 'Add Task', 'cpm' ),
                'add_new_item' => __( 'Add New Task', 'cpm' ),
                'edit' => __( 'Edit', 'cpm' ),
                'edit_item' => __( 'Edit Task', 'cpm' ),
                'new_item' => __( 'New Task', 'cpm' ),
                'view' => __( 'View Task', 'cpm' ),
                'view_item' => __( 'View Task', 'cpm' ),
                'search_items' => __( 'Search Task', 'cpm' ),
                'not_found' => __( 'No Task Found', 'cpm' ),
                'not_found_in_trash' => __( 'No Task Found in Trash', 'cpm' ),
                'parent' => __( 'Parent Task', 'cpm' ),
            ),
        ) );
    }

    /**
     * Add a new task list
     *
     * @param int $project_id project id
     * @param int $list_id list id for update purpose
     * @return int task list id
     */
    function add_list( $project_id, $list_id = 0 ) {
        $postdata = $_POST;
        $is_update = $list_id ? true : false;
        $tasklist_privacy = isset( $postdata['tasklist_privacy'] ) ? $postdata['tasklist_privacy'] : 'no';

        $data = array(
            'post_parent' => $project_id,
            'post_title' => $postdata['tasklist_name'],
            'post_content' => $postdata['tasklist_detail'],
            'post_type' => 'task_list',
            'post_status' => 'publish'
        );

        if ( $list_id ) {
            $data['ID'] = $list_id;
            $list_id = wp_update_post( $data );
        } else {
            $list_id = wp_insert_post( $data );
        }

        if ( $list_id ) {
            update_post_meta( $list_id, '_milestone', $postdata['tasklist_milestone'] );

            update_post_meta( $list_id, '_tasklist_privacy', $tasklist_privacy );

            if ( $is_update ) {
                do_action( 'cpm_tasklist_update', $list_id, $project_id, $data );
            } else {
                do_action( 'cpm_tasklist_new', $list_id, $project_id, $data );
            }
        }

        return $list_id;
    }

    /**
     * Update a task list
     *
     * @param int $project_id
     * @param int $list_id
     * @return int list id
     */
    function update_list( $project_id, $list_id ) {
        return $this->add_list( $project_id, $list_id );
    }

    /**
     * Add a single task
     *
     * @param int $list_id task list id
     * @return int $task_id task id for update purpose
     */
    function add_task( $list_id, $task_id = 0 ) {
        $postdata = $_POST;
        $files = isset( $postdata['cpm_attachment'] ) ? $postdata['cpm_attachment'] : array();
        $task_privacy = isset( $postdata['task_privacy'] ) ? $postdata['task_privacy'] : 'no';
        $is_update = $task_id ? true : false;

        $content = trim( $postdata['task_text'] );
        $assigned = $postdata['task_assign'];
        $due = empty( $postdata['task_due'] ) ? '' : cpm_date2mysql( $postdata['task_due'] );
        $start = empty( $postdata['task_start'] ) ? '' : cpm_date2mysql( $postdata['task_start'] );

        $data = array(
            'post_parent' => $list_id,
            'post_title' => trim( substr( $content, 0, 40 ) ), //first 40 character
            'post_content' => $content,
            'post_type' => 'task',
            'post_status' => 'publish'
        );

        $data = apply_filters( 'cpm_task_params', $data );

        if ( $task_id ) {
            $data['ID'] = $task_id;
            $task_id = wp_update_post( $data );
        } else {
            $task_id = wp_insert_post( $data );
        }

        if ( $task_id ) {
            update_post_meta( $task_id, '_assigned', $assigned );
            update_post_meta( $task_id, '_due', $due );
            if(cpm_get_option( 'task_start_field' ) == 'on') {
                update_post_meta( $task_id, '_start', $start );
            } else {
                update_post_meta( $task_id, '_start', '' );
            }
            update_post_meta( $task_id, '_task_privacy', $task_privacy );

            //initially mark as uncomplete
            if ( !$is_update ) {
                update_post_meta( $task_id, '_completed', 0 );
            }

            //if there is any file, update the object reference
            if ( count( $files ) > 0 ) {
                $comment_obj = CPM_Comment::getInstance();

                foreach ($files as $file_id) {
                    $comment_obj->associate_file( $file_id, $task_id );
                }
            }

            if ( $is_update ) {
                do_action( 'cpm_task_update', $list_id, $task_id, $data );
            } else {
                do_action( 'cpm_task_new', $list_id, $task_id, $data );
            }
        }

        return $task_id;
    }

    /**
     * Update a single task
     *
     * @param int $list_id
     * @param int $task_id
     * @return int task id
     */
    function update_task( $list_id, $task_id ) {
        return $this->add_task( $list_id, $task_id );
    }

    /**
     * Get all task list by project
     *
     * @param int $project_id
     * @return object object array of the result set
     */
    function get_task_lists( $project_id, $privacy = false ) {

        $args = array(
            'post_type' => 'task_list',
            'numberposts' => -1,
            'order' => 'ASC',
            'orderby' => 'menu_order',
            'post_parent' => $project_id
        );

        if ( $privacy === false ) {
            $args['meta_query'] =  array(
                array(
                    'key' => '_tasklist_privacy',
                    'value' => 'yes',
                    'compare' => '!='
                ),
            );
        }

        $args = apply_filters( 'cpm_get_tasklist', $args );

        $lists = get_posts( $args );
        foreach ($lists as $list) {
            $this->set_list_meta( $list );
        }

        return $lists;
    }

    /**
     * Get a single task list by a task list id
     *
     * @param int $list_id
     * @return object object array of the result set
     */
    function get_task_list( $list_id ) {
        $task_list = get_post( $list_id );
        $this->set_list_meta( $task_list );

        return $task_list;
    }

    /**
     * Set meta info for a task list
     *
     * @param object $task_list
     */
    function set_list_meta( &$task_list ) {
        $task_list->due_date = get_post_meta( $task_list->ID, '_due', true );
        $task_list->milestone = get_post_meta( $task_list->ID, '_milestone', true );
        $task_list->private = get_post_meta( $task_list->ID, '_tasklist_privacy', true );
    }

    function get_tasks_by_access_role( $list_id, $project_id = null ) {

        if ( cpm_user_can_access( $project_id ) ) {
            //for manager lavel
            $tasks = $this->get_tasks( $list_id );
        } else {
            if ( cpm_user_can_access( $project_id, 'todo_view_private' ) ) {
                //for settings role true
                $tasks = $this->get_tasks( $list_id, true );
            } else {
                //for settings role false
                $tasks = $this->get_tasks( $list_id, false );
            }
        }

        return $tasks;
    }

    /**
     * Get all tasks based on a task list
     *
     * @param int $list_id
     * @return object object array of the result set
     */
    function get_tasks( $list_id, $privacy = null ) {

        $args = array('post_parent' => $list_id, 'numberposts' => -1, 'post_type' => 'task', 'order' => 'ASC', 'orderby' => 'menu_order');

        if ( $privacy === false ) {
            $args['meta_query'] =  array(
                array(
                    'key' => '_task_privacy',
                    'value' => 'yes',
                    'compare' => '!='
                ),

                /*array(
                    'key' => '_assigned',
                    'value' => get_current_user_id(),
                    'compare' => '='
                ),*/
            );
        } else if( $privacy === true ) {
            /*$args['meta_query'] =  array(

                array(
                    'key' => '_assigned',
                    'value' => get_current_user_id(),
                    'compare' => '='
                ),
            );*/
        }

        $args = apply_filters( 'cpm_get_task', $args );

        $tasks = get_children( $args );

        foreach ($tasks as $key => $task) {
            $this->set_task_meta( $task );
        }

        return $tasks;
    }

    /**
     * Set all the meta values to a single task
     *
     * @param object $task
     */
    function set_task_meta( &$task ) {
        $task->completed = get_post_meta( $task->ID, '_completed', true );
        $task->completed_by = get_post_meta( $task->ID, '_completed_by', true );
        $task->completed_on = get_post_meta( $task->ID, '_completed_on', true );
        $task->assigned_to = get_post_meta( $task->ID, '_assigned', true );
        $task->due_date = get_post_meta( $task->ID, '_due', true );
        $task->start_date = get_post_meta( $task->ID, '_start', true );
        $task->task_privacy = get_post_meta( $task->ID, '_task_privacy', true );
    }

    /**
     * Get all tasks based on a milestone
     *
     * @param int $list_id
     * @return object object array of the result set
     */
    function get_tasklist_by_milestone( $milestone_id, $privacy = false ) {

        $args = array(
            'post_type' => 'task_list',
            'numberposts' => -1,
            'order' => 'ASC',
            'orderby' => 'menu_order',
        );

        $args['meta_query'][] = array(
            'key' => '_milestone',
            'value' => $milestone_id,
        );

        if ( $privacy === false ) {
            $args['meta_query'][] =  array(
                'key' => '_tasklist_privacy',
                'value' => 'yes',
                'compare' => '!='
            );
        } 

        $tasklists = get_posts( $args );
        foreach ($tasklists as $list) {
            $this->set_list_meta( $list );
        }

        return $tasklists;
    }

    /**
     * Get a single task detail
     *
     * @param int $task_id
     * @return object object array of the result set
     */
    function get_task( $task_id ) {
        $task = get_post( $task_id );
        $this->set_task_meta( $task );

        return $task;
    }

    /**
     * Get all comment on a task list
     *
     * @param int $list_id
     * @return object object array of the result set
     */
    function get_comments( $list_id ) {
        return CPM_Comment::getInstance()->get_comments( $list_id );
    }

    /**
     * Get all comments on a single task
     *
     * @param int $task_id
     * @return object object array of the result set
     */
    function get_task_comments( $task_id ) {
        return CPM_Comment::getInstance()->get_comments( $task_id );
    }

    /**
     * Mark a task as complete
     *
     * @param int $task_id task id
     */
    function mark_complete( $task_id ) {
        update_post_meta( $task_id, '_completed', 1 );
        update_post_meta( $task_id, '_completed_by', get_current_user_id() );
        update_post_meta( $task_id, '_completed_on', current_time( 'mysql' ) );

        do_action( 'cpm_task_complete', $task_id );
    }

    /**
     * Mark a task as uncomplete/open
     *
     * @param int $task_id task id
     */
    function mark_open( $task_id ) {
        update_post_meta( $task_id, '_completed', 0 );
        update_post_meta( $task_id, '_completed_on', current_time( 'mysql' ) );

        do_action( 'cpm_task_open', $task_id );
    }

    /**
     * Delete a single task
     *
     * @param int $task_id
     * @param bool $force
     */
    function delete_task( $task_id, $force = false ) {
        do_action( 'cpm_task_delete', $task_id, $force );

        wp_delete_post( $task_id, $force );
    }

    /**
     * Delete a task list
     *
     * @param int $list_id
     * @param bool $force
     */
    function delete_list( $list_id, $force = false ) {
        do_action( 'cpm_tasklist_delete', $list_id, $force );

        //get child tasks and delete them
        $tasks = $this->get_tasks( $list_id );
        if ( $tasks ) {
            foreach ($tasks as $task) {
                $this->delete_task( $task->ID, true );
            }
        }
        
        wp_delete_post( $list_id, $force );
    }

    /**
     * Get the overall completeness for a task list
     *
     * @param int $list_id
     * @return array
     */
    function get_completeness( $list_id, $project_id=null ) {

        $tasks = $this->get_tasks_by_access_role( $list_id , $project_id );

        return array(
            'total' => count( $tasks ),
            'completed' => array_sum( wp_list_pluck( $tasks, 'completed' ) )
        );
    }

}
