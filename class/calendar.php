<?php

/**
 * Calendar
 *
 * @author Tareq Hasan (http://tareq.weDevs.com)
 */
class CPM_Calendar {

    private static $_instance;

    public function __construct() {
        
    }

    public static function getInstance() {
        if ( !self::$_instance ) {
            self::$_instance = new CPM_Calendar();
        }

        return self::$_instance;
    }

    function get_events() {
        $projects = CPM_Project::getInstance()->get_projects();
        
        if ( cpm_get_option( 'task_start_field' ) == 'on' ) {
            $enable_start = true;
        } else {
            $enable_start = false;
        }

        $events = array();
        if ( $projects ) {
            foreach ($projects as $project) {
                $project_id = $project->ID;
                
                //Get Milestones
                $milestones = CPM_Milestone::getInstance()->get_by_project( $project_id );
                if ( $milestones ) {
                    foreach ($milestones as $milestone) {
                        //Milestone Event
                        $events[] = array(
                            'id' => $milestone->ID,
                            'title' => $milestone->post_title,
                            'start' => $milestone->due_date,
                            'url' => cpm_url_milestone_index( $project_id ),
                            'color' => '#32b1c8',
                            'className' => ($milestone->completed == 1) ? 'milestone competed' : 'milestone'
                        );
                    }
                }
                
                //Get Tasks
                if ( cpm_user_can_access( $project_id, 'tdolist_view_private' ) ) {
                    $task_lists = CPM_Task::getInstance()->get_task_lists( $project_id, true );
                } else {
                    $task_lists = CPM_Task::getInstance()->get_task_lists( $project_id );
                }

                if ( $task_lists ) {
                    foreach ($task_lists as $task_list) {
                        $tasks = CPM_Task::getInstance()->get_tasks_by_access_role( $task_list->ID, $project_id );

                        foreach ($tasks as $task) {
                            //Tasks Event
                            if ( $enable_start ) {
                                
                                if ( isset( $task->start_date ) && !empty( $task->start_date ) ) {
                                    $start_date = $task->start_date;
                                } else {
                                    $start_date = $task->due_date;
                                }
                                
                                $events[] = array(
                                    'id' => $task->ID,
                                    'img' => ($task->assigned_to == -1) ? '' : get_avatar( $task->assigned_to, 16 ),
                                    'title' => $task->post_title,
                                    'start' => $start_date,
                                    'end' => $task->due_date,
                                    'complete_status' => ($task->completed == 1 ) ? 'yes' : 'no',
                                    'url' => cpm_url_single_task( $project_id, $task_list->ID, $task->ID ),
                                    'color' => 'transparent',
                                    'textColor' => '#c86432',
                                    'className' => ( date( 'Y-m-d', time() ) < $task->due_date ) ? 'cpm-calender-todo cpm-task-running' : 'cpm-calender-todo cpm-expire-task',
                                );
                                
                            } else {

                                $events[] = array(
                                    'id' => $task->ID,
                                    'img' => ($task->assigned_to == -1) ? '' : get_avatar( $task->assigned_to, 16 ),
                                    'title' => $task->post_title,
                                    'start' => $task->due_date,
                                    'complete_status' => ($task->completed == 1 ) ? 'yes' : 'no',
                                    'url' => cpm_url_single_task( $project_id, $task_list->ID, $task->ID ),
                                    'color' => 'transparent',
                                    'textColor' => '#c86432',
                                    'className' => ( date( 'Y-m-d', time() ) < $task->due_date ) ? 'cpm-calender-todo cpm-task-running' : 'cpm-calender-todo cpm-expire-task',
                                );
                            }
                        }
                    }
                }
            }
        }

        return $events;
    }

}
