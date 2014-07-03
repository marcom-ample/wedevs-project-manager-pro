<?php
class CPM_Notification {
    private static $_instance;
    function __construct() {
        //notify users
        add_action( 'cpm_project_new', array($this, 'project_new'), 10, 2 );
        add_action( 'cpm_project_update', array($this, 'project_update'), 10, 2 );

        add_action( 'cpm_comment_new', array($this, 'new_comment'), 10, 3 );
        add_action( 'cpm_message_new', array($this, 'new_message'), 10, 2 );
        
        add_action( 'cpm_task_new', array($this, 'new_task'), 10, 3 );
        add_action( 'cpm_task_update', array($this, 'new_task'), 10, 3 );
    }

    public static function getInstance() {
        if ( !self::$_instance ) {
            self::$_instance = new CPM_Notification();
        }

        return self::$_instance;
    }

    function prepare_contacts() {
        $to = array();
        $bcc_status = cpm_get_option('email_bcc_enable');
        if ( isset( $_POST['notify_user'] ) && is_array( $_POST['notify_user'] ) ) {

            foreach ($_POST['notify_user'] as $user_id) {
                $user_info = get_user_by( 'id', $user_id );
                
                if ( $user_info && $bcc_status == 'on' ) {
                    $to[] = sprintf( '%s <%s>', $user_info->display_name, $user_info->user_email );
                } else if ( $user_info && $bcc_status != 'on' ) {
                    $to[] = sprintf( '%s', $user_info->user_email );
                }
            }
        }

        return $to;
    }

    /**
     * Notify users about the new project creation
     *
     * @uses `cpm_new_project` hook
     * @param int $project_id
     */
    function project_new( $project_id, $data ) {

        if ( isset( $_POST['project_notify'] ) && $_POST['project_notify'] == 'yes' ) {
            $project_users = CPM_Project::getInstance()->get_users( $project_id );
            $users = array();

            if( is_array( $project_users ) && count($project_users) ) {
                foreach ($project_users as $user_id => $role_array ) {
                    $users[$user_id] = sprintf( '%s <%s>', $role_array['name'], $role_array['email'] );
                }
            }

            //if any users left, get their mail addresses and send mail
            if ( $users ) {
                
                $template_vars = array(
                    '%SITE%' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), 
                    '%PROJECT_NAME%' => $data['post_title'],
                    '%PROJECT_DETAILS%' => $data['post_content'],
                    '%PROJECT_URL%' => cpm_url_project_details( $project_id )
                );
                
                $subject = cpm_get_option( 'new_project_sub' );
                $message = cpm_get_option( 'new_project_body' );
                
                // subject
                foreach ($template_vars as $key => $value) {
                    $subject = str_replace( $key, $value, $subject );
                }
                
                // message
                foreach ($template_vars as $key => $value) {
                    $message = str_replace( $key, $value, $message );
                }

                $this->send( implode(', ', $users), $subject, $message );
            }
        }
    }

    /**
     * Notify users about the update project creation
     *
     * @uses `cpm_new_project` hook
     * @param int $project_id
     */
    function project_update( $project_id, $data ) {

        if ( isset( $_POST['project_notify'] ) && $_POST['project_notify'] == 'yes' ) {
            $project_users = CPM_Project::getInstance()->get_users( $project_id );
            $users = array();

            if( is_array( $project_users ) && count($project_users) ) {
                foreach ($project_users as $user_id => $role_array ) {
                    $users[$user_id] = sprintf( '%s <%s>', $role_array['name'], $role_array['email'] );
                }
            }

            //if any users left, get their mail addresses and send mail
            if ( $users ) {
                
                $template_vars = array(
                    '%SITE%' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), 
                    '%PROJECT_NAME%' => $data['post_title'],
                    '%PROJECT_DETAILS%' => $data['post_content'],
                    '%PROJECT_URL%' => cpm_url_project_details( $project_id )
                );
                
                $subject = cpm_get_option( 'update_project_sub' );
                $message = cpm_get_option( 'update_project_body' );
                
                // subject
                foreach ($template_vars as $key => $value) {
                    $subject = str_replace( $key, $value, $subject );
                }
                
                // message
                foreach ($template_vars as $key => $value) {
                    $message = str_replace( $key, $value, $message );
                }

                $this->send( implode(', ', $users), $subject, $message );
            }
        }
    }

    function complete_task( $list_id, $task_id, $data, $project_id ) {

        $project_users = CPM_Project::getInstance()->get_users( $project_id );
        $users = array();

        if( is_array( $project_users ) && count($project_users) ) {
            foreach ($project_users as $user_id => $role_array ) {
                if( $role_array['role'] == 'manager' ) {
                    $users[$user_id] = sprintf( '%s <%s>', $role_array['name'], $role_array['email'] );
                }
            }
        }
        if ( $users ) {
            $template_vars = array(
                '%SITE%' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), 
                '%PROJECT_NAME%' => get_post_field( 'post_title', $project_id ),
                '%PROJECT_URL%' => cpm_url_project_details( $project_id ),
                '%TASKLIST_URL%' => cpm_url_single_tasklist($project_id, $list_id),
                '%TASK_URL%' => cpm_url_single_task( $project_id, $list_id, $task_id ),
                '%TASK%' => $data->post_content,
                '%IP%' => get_ipaddress()
            );

            $subject = cpm_get_option( 'complete_task_sub' );
            $message = cpm_get_option( 'completed_task_body' );

            // subject
            foreach ($template_vars as $key => $value) {
                $subject = str_replace( $key, $value, $subject );
            }

            // message
            foreach ($template_vars as $key => $value) {
                $message = str_replace( $key, $value, $message );
            }

            $this->send( implode(', ', $users), $subject, $message );
        }
    }

    function new_message( $message_id, $project_id ) {
        $users = $this->prepare_contacts();

        if ( !$users ) {
            return;
        }

        $pro_obj = CPM_Project::getInstance();
        $msg_obj = CPM_Message::getInstance();

        $project = $pro_obj->get( $project_id );
        $msg = $msg_obj->get( $message_id );
        $author = wp_get_current_user();

        $template_vars = array(
            '%SITE%' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), 
            '%PROJECT_NAME%' => $project->post_title,
            '%PROJECT_URL%' => cpm_url_project_details( $project_id ),
            '%AUTHOR%' => $author->display_name,
            '%AUTHOR_EMAIL%' => $author->user_email,
            '%MESSAGE_URL%' => cpm_url_single_message( $project_id, $message_id ),
            '%MESSAGE%' => $msg->post_content,
            '%IP%' => get_ipaddress()
        );


        $subject = cpm_get_option( 'new_message_sub' );
        $message = cpm_get_option( 'new_message_body' );

        // subject
        foreach ($template_vars as $key => $value) {
            $subject = str_replace( $key, $value, $subject );
        }

        // message
        foreach ($template_vars as $key => $value) {
            $message = str_replace( $key, $value, $message );
        }


        $this->send( implode( ', ', $users ), $subject, $message );
    }

    /**
     * Send email to all about a new comment
     *
     * @param int $comment_id
     * @param array $comment_info the post data
     */
    function new_comment( $comment_id, $project_id, $data ) {
        $users = $this->prepare_contacts();
        
        if ( !$users ) {
            return;
        }

        $msg_obj = CPM_Message::getInstance();
        $parent_post = get_post( $data['comment_post_ID'] );
        $author = wp_get_current_user();
        $comment_url = '';
        
        switch ($parent_post->post_type) {
            case 'message':
                $comment_url = cpm_url_single_message( $project_id, $data['comment_post_ID'] );
                break;
            
            case 'task_list':
                $comment_url = cpm_url_single_tasklist( $project_id, $parent_post->ID );
                break;
            
            case 'task':
                $comment_url = cpm_url_single_task( $project_id, $parent_post->post_parent, $parent_post->ID );
                break;
        }
        
        $template_vars = array(
            '%SITE%' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), 
            '%PROJECT_NAME%' => get_post_field( 'post_title', $project_id ),
            '%PROJECT_URL%' => cpm_url_project_details( $project_id ),
            '%AUTHOR%' => $author->display_name,
            '%AUTHOR_EMAIL%' => $author->user_email,
            '%COMMENT_URL%' => $comment_url,
            '%COMMENT%' => $data['comment_content'],
            '%IP%' => get_ipaddress()
        );

        $subject = cpm_get_option( 'new_comment_sub' );
        $message = cpm_get_option( 'new_comment_body' );

        // subject
        foreach ($template_vars as $key => $value) {
            $subject = str_replace( $key, $value, $subject );
        }

        // message
        foreach ($template_vars as $key => $value) {
            $message = str_replace( $key, $value, $message );
        }

        $this->send( implode( ', ', $users ), $subject, $message );
    }

    function new_task( $list_id, $task_id, $data ) {

        //notification is not selected or no one is assigned
        if ( $_POST['task_assign'] == '-1' ) {
            return;
        }

        $project_id = 0;
	
    	if( isset( $_POST['project_id'] )) {
    		$project_id = intval( $_POST['project_id'] );
    	}
        $user = get_user_by( 'id', intval( $_POST['task_assign'] ) );
        $to = sprintf( '%s <%s>', $user->display_name, $user->user_email );

        $template_vars = array(
            '%SITE%' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), 
            '%PROJECT_NAME%' => get_post_field( 'post_title', $project_id ),
            '%PROJECT_URL%' => cpm_url_project_details( $project_id ),
            '%AUTHOR%' => $user->display_name,
            '%AUTHOR_EMAIL%' => $user->user_email,
            '%TASKLIST_URL%' => cpm_url_single_tasklist($project_id, $list_id),
            '%TASK_URL%' => cpm_url_single_task( $project_id, $list_id, $task_id ),
            '%TASK%' => $data['post_content'],
            '%IP%' => get_ipaddress()
        );

        $subject = cpm_get_option( 'new_task_sub' );
        $message = cpm_get_option( 'new_task_body' );

        // subject
        foreach ($template_vars as $key => $value) {
            $subject = str_replace( $key, $value, $subject );
        }

        // message
        foreach ($template_vars as $key => $value) {
            $message = str_replace( $key, $value, $message );
        }

        $this->send( $to, $subject, $message );
    }

    function send( $to, $subject, $message ) {

        $bcc_status = cpm_get_option( 'email_bcc_enable' );
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        
        $reply = 'no-reply@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );
        $reply_to = "Reply_To: <$reply>";
        $content_type = 'Content-Type: ' .cpm_get_option( 'email_type');
        $charset = 'Charset: UTF-8';
        $from_email = cpm_get_option( 'email_from' );
        $from = "From: $blogname <$from_email>";
        
        if( $bcc_status == 'on' ) {
            $bcc = 'Bcc: '. $to;
            $headers = array(
                $bcc,
                $reply_to,
                $content_type,
                $charset,
                $from
            );

            wp_mail( $reply, $subject, $message, $headers);
        } else {
            $to = explode( ',', $to );

            foreach ( $to as $key => $email) {
                $headers = array(
                    $reply_to,
                    $content_type,
                    $charset,
                    $from
                );
                wp_mail( $email, $subject, $message, $headers );
            }
        } 
    }

    

}
