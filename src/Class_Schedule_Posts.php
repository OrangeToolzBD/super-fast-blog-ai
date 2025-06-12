<?php
        if ( ! defined( 'ABSPATH' ) ) exit;

        // Activation Hook
        register_activation_hook(__FILE__, 'otslf_schedule_posts_activation');

        function otslf_schedule_posts_activation() {

            $schedule = get_option('otslf_schedule');
            $sameschedule = get_option('otslf_same_schedule');

            // Clear any previously scheduled events
            wp_clear_scheduled_hook('slf_publish_later_same_day');
            wp_clear_scheduled_hook('slf_publish_posts_event');
            wp_clear_scheduled_hook('slf_publish_daily_event');

            if ($schedule === 'sameday') {

                if ($sameschedule === 'later_same_day') {

                    $hours_delay = (int) get_option('otslf_hoursInput');
                    $minutes_delay = (int) get_option('otslf_minutesInput');

                    $delay_in_seconds = ($hours_delay * 3600) + ($minutes_delay * 60);
                    $current_time = time();
                    $next_scheduled_time = $current_time + $delay_in_seconds;

                    if (!wp_next_scheduled('slf_publish_later_same_day')) {
                        wp_schedule_single_event($next_scheduled_time, 'slf_publish_later_same_day');
                        wp_error_log("Scheduled 'slf_publish_later_same_day' event.");
                    } else {
                        wp_error_log("'slf_publish_later_same_day' is already scheduled.");
                    }
                }

            } elseif ($schedule === 'later') {

                $later_days = get_option('otslf_laterdate');
                $oclock = get_option('otslf_oclock');

                if ($later_days !== false && $oclock !== false) {
                    $later_date = gmdate('Y-m-d', strtotime("+$later_days days"));
                    $schedule_time = "$later_date $oclock:00";
                    $time_to_schedule = strtotime($schedule_time);

                    if (!wp_next_scheduled('slf_publish_posts_event')) {
                        wp_schedule_event($time_to_schedule, 'daily', 'slf_publish_posts_event');
                        wp_error_log("Scheduled 'slf_publish_posts_event' at $schedule_time.");
                    } else {
                        wp_error_log("'slf_publish_posts_event' is already scheduled.");
                    }
                }

            } elseif ($schedule === 'recurring') {

                $recurdate = get_option('otslf_recurdate');
                $rcuroclock = get_option('otslf_rcuroclock');

                if ($recurdate !== false && $rcuroclock !== false) {

                    $recur_date = gmdate('Y-m-d', strtotime("+$recurdate days"));
                    $schedule_time = "$recur_date $rcuroclock:00";
                    $time_to_schedule = strtotime($schedule_time);

                    if (!wp_next_scheduled('slf_publish_daily_event')) {
                        wp_schedule_event($time_to_schedule, 'every_two_days', 'slf_publish_daily_event');
                        wp_error_log("Scheduled 'slf_publish_daily_event' at $schedule_time.");
                    } else {
                        wp_error_log("'slf_publish_daily_event' is already scheduled.");
                    }
                }
            }
        }

    // Register deactivation hook
    register_deactivation_hook(__FILE__, 'otslf_schedule_posts_deactivation');

    function otslf_schedule_posts_deactivation() {
        wp_clear_scheduled_hook('slf_publish_later_same_day');
        wp_clear_scheduled_hook('slf_publish_posts_event');
        wp_clear_scheduled_hook('slf_publish_daily_event');
    }



    // Display schedule notice
    function otslf_display_schedule_notice($event_name) {

        global $wpdb;
        $next_schedule = wp_next_scheduled($event_name);
        $cache_key = 'slf_generated_title_latest';
        $cache_group = 'slf_cache_group';
        $result = wp_cache_get($cache_key, $cache_group);

        if ($result === false) {
            // If cache is empty, query the database
            $generated_title = $wpdb->prefix . 'slf_generated_title';
            $query = $wpdb->prepare(
                "SELECT generate_title FROM {$generated_title} ORDER BY id DESC LIMIT %d", 1 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            
            $result = $wpdb->get_var($query);  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            wp_cache_set($cache_key, $result, $cache_group, 3600);
        }

        $user_timezone = get_user_meta(get_current_user_id(), 'timezone_string', true) ?: 'UTC';

        if ($next_schedule) {
            $date_in_utc = new DateTime('@' . $next_schedule);
            $date_in_utc->setTimezone(new DateTimeZone('UTC'));
            $schedule = get_option('otslf_schedule');
            $sameschedule = get_option('otslf_same_schedule'); 
            $formatted_publish_time = '';

            if ($schedule === 'sameday') {
                
                if($sameschedule === 'later_same_day'){ 

                    $hours = (int) get_option('otslf_hoursInput');
                    $minutes = (int) get_option('otslf_minutesInput');
                    $publish_time = $date_in_utc->setTime($hours, $minutes);
                    $date_in_user_timezone = $publish_time->setTimezone(new DateTimeZone($user_timezone));
                    $formatted_publish_time = $date_in_user_timezone->format('Y-m-d g:i a');

                }

            } elseif ($schedule === 'later') {
                
                $laterdate = get_option('otslf_laterdate');
                $oclock = get_option('otslf_oclock');
                $publish_time = $date_in_utc->modify('+' . intval($laterdate) . ' days');
                $time_parts = date_parse($oclock);
                $publish_hour = $time_parts['hour'];
                $publish_minute = $time_parts['minute'];
                $publish_time->setTime($publish_hour, $publish_minute);
                $date_in_user_timezone = $publish_time->setTimezone(new DateTimeZone($user_timezone));
                $formatted_publish_time = $date_in_user_timezone->format('Y-m-d g:i a');

            } elseif ($schedule === 'recurring') {
                
                $recurdate = get_option('otslf_recurdate');
                $rcuroclock = get_option('otslf_rcuroclock');
                $publish_time = $date_in_utc->modify('+' . intval($recurdate) . ' days');
                $time_parts = date_parse($rcuroclock);
                $publish_hour = $time_parts['hour'];
                $publish_minute = $time_parts['minute'];
                $publish_time->setTime($publish_hour, $publish_minute);
                $date_in_user_timezone = $publish_time->setTimezone(new DateTimeZone($user_timezone));
                $formatted_publish_time = $date_in_user_timezone->format('Y-m-d g:i a');
            }

            $next_title = !empty($result) ? esc_html($result) : esc_html__('No more titles available', 'super-fast-blog-ai');
            echo '<div class="notice notice-success is-dismissible">
                    <p>' . sprintf(                 /* translators: %1$s is the scheduled publish time, %2$s is the title of the next post */
                        esc_html__('The schedule is set. Next post will be published on: <strong>%1$s</strong>. Next title: <strong>%2$s</strong>.', 'super-fast-blog-ai'),
                        esc_html($formatted_publish_time),
                        esc_html($next_title)
                    ) . '</p>
            </div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible">
                    <p>' . esc_html__('No scheduled event found.', 'super-fast-blog-ai') . '</p>
                 </div>';
        }
    }

    // Add admin notice action
    add_action('admin_notices', 'otslf_schedule_admin_notices');
    
    function otslf_schedule_admin_notices() {
        
        $schedule = get_option('otslf_schedule');
        $sameschedule = get_option('otslf_same_schedule');

        if ($schedule === 'sameday') {
            if ($sameschedule === 'later_same_day') {
                otslf_display_schedule_notice('slf_publish_later_same_day');
            }
        } elseif ($schedule === 'later') {
            otslf_display_schedule_notice('slf_publish_posts_event');
            
        } elseif ($schedule === 'recurring') {
            otslf_display_schedule_notice('slf_publish_daily_event');
        }
    }