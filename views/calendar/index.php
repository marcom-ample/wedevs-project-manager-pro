<h1><?php _e( 'Calendar', 'cpm'); ?></h1>

<?php
if ( cpm_get_option( 'task_start_field' ) == 'on' ) {
    $eventDurationEditable = 'true';
} else {
    $eventDurationEditable = 'false';
}

if ( !is_admin() ) {
    $fornt_instant = 'cpmf_url:' . json_encode( get_permalink() );
} else {
    $fornt_instant = 'url:' . json_encode( admin_url() );
}
?>

<div id='calendar' class="cpm-calendar">
    <div class="cpm-calender-loading"></div>
</div>

<script>
    jQuery(document).ready(function($) {
        var date = new Date();
        var d = date.getDate();
        var m = date.getMonth();
        var y = date.getFullYear();
        
        
        var calendar = $('#calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            editable: true,
            eventStartEditable: true,
            eventDurationEditable: <?php echo $eventDurationEditable; ?>,

            events: {
                url: CPM_Vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpm_get_events',
                    _wpnonce: CPM_Vars.nonce,
                    <?php echo $fornt_instant; ?>

                },
                beforeSend: function(e) {
                    $('#calendar .cpm-calender-loading').addClass('active');
                },
                success: function(res) {
                   $('#calendar .cpm-calender-loading').removeClass('active');
                },
                error: function() {
                    alert('There was an error while fetching events!');
                }
            },

            eventRender: function(event, element, calEvent) {

                if(element.hasClass('cpm-calender-todo')) {
                    
                    var current = new Date(),
                        currentYear = current.getFullYear(),
                        currentMonth = current.getMonth(),
                        currentDay = current.getDate(),
                        currentTime = new Date( currentYear,currentMonth,currentDay );
                        
                    var end = null;
                    if( event.end === null) {
                        end = new Date( event.start );
                    } else {
                        end = new Date( event.end );
                    }

                    var endYear = end.getFullYear(),
                        endMonth = end.getMonth(),
                        endDay = end.getDate(),
                        endTime = new Date( endYear, endMonth, endDay );

                    if( currentTime.getTime() <= endTime.getTime() ) {
                        // console.log('current time choto');
                       element.removeClass('cpm-expire-task');
                       element.addClass('cpm-task-running');
                        
                    } else {
                        // console.log('current time boro');
                        element.removeClass('cpm-task-running');
                        element.addClass('cpm-expire-task');
                    }

                    if(event.complete_status == 'yes') {
                        element.removeClass('cpm-task-running');
                        element.removeClass('cpm-expire-task');
                        element.addClass('cpm-complete-task');
                    }
                }

                if( event.img != 'undefined' && element.hasClass('cpm-calender-todo') ) {
                    element.find('.fc-event-title').before( $("<span class=\"fc-event-icons\">"+event.img+"</span>") ); 
                }
            },

            eventDrop: function( event, dayDelta, minuteDelta, allDay, revertFunc, jsEvent, ui, view ) { 
                CpmUpdateStartEndMeata(event);
            },
            eventResize: function(event,dayDelta,minuteDelta,revertFunc) {
                CpmUpdateStartEndMeata(event);
            },
        });
        
        function CpmUpdateStartEndMeata(event) {
            if(event.start != null) {
                var start_date = new Date(event.start),
                    start_date = $.datepicker.formatDate('dd M yy', start_date);
            } else {
                start_date = '';
            }
            
            if(event.end != null) {
                var end_date = new Date( event.end ),
                end_date = $.datepicker.formatDate('dd M yy', end_date);
            } else {
                var end_date = '';
            }
        
            var data = {
                action: 'cpm_calender_update_duetime',
                _wpnonce: CPM_Vars.nonce,
                task_id: event.id,
                start_date: start_date,
                end_date : end_date

            };
            
            $.post(CPM_Vars.ajaxurl, data );
        }
        
    });
</script>