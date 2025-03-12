<?php
/**
 *
 *  orange log post title
 *
 **/

namespace Orange\AiBlog\Src;

class Class_Logschedulepost {

    private $language;

    public function __construct()
        {
           add_action('wp_ajax_otslf_title_log_list', [$this, 'otslf_title_log_list']);  
           add_action('wp_ajax_otslf_delete_multiple_blog_title', [$this, 'otslf_delete_multiple_blog_title']);     
        }    
    
        public function otslf_title_log_list(){            
        ?>
          
            <div id="show-error" style="display:none;"> </div>
            <div id="success-message" style="display:none;"> </div>
            <div id="log_wrapper" class="log-schedule-post">
               <h1><?php esc_html_e('Log Schedule Post', 'super-fast-blog-ai');?></h1>  
            <div id="log_content"> 
                <?php 
                    
                    global $wpdb;
                    $publish    = get_option('otslf_schedule');
                    $schedule_table = $wpdb->prefix . 'slf_schedule_post_title_log';
                    $cache_key  = 'slf_schedule_post_title_log_all';
                    $results = wp_cache_get($cache_key, 'slf_schedule_post_title_log');
					if ($results === false) {
						$query = "SELECT wpost.ID, wpost.post_title, slflog.id, slflog.status, slflog.postid, 
										 slflog.charaters, slflog.modelused, slflog.indicat, slflog.log_time 
								  FROM {$wpdb->posts} AS wpost
								  RIGHT JOIN {$wpdb->prefix}slf_schedule_post_title_log AS slflog
								  ON wpost.ID = slflog.postid
								  ORDER BY wpost.id DESC";
						$results = $wpdb->get_results($query, OBJECT);
						if ($results) {
							wp_cache_set($cache_key, $results, 'slf_schedule_post_title_log', 3600);
						}
					}

                    echo '<table id="titlelog" class="display" style="width:100%">
                    <thead>
                            <tr>
                                <th><input type="checkbox" id="select_title" name="select_title[]"/></th>  
                                <th>Title</th> 
                                <th>Tokens</th> 
                                <th>Estimated</th>
                                <th>Status</th> 
                                <th>Model</th> 
                                <th>Created Date & Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>';
                    echo '<tbody>';
                
                    foreach ($results as $result) {
                        
                        $edit_url = admin_url('post.php?post=' . $result->postid . '&action=edit');
                        
                        $this->language = get_option('otslf_language_select');

                        /* $token = $result->charaters / 4;
                        $estimate = $token * (0.015 / 1000); */

                        $token = 0;
                        $lan_1 = ['en', 'ru', 'pt', 'es', 'it', 'fr', 'af', 'bs', 'bg', 'hr', 'cs', 'el', 'da', 'nl', 
                                'et', 'fil', 'fi', 'he', 'hi', 'hu', 'id', 'ja', 'lv', 'lt', 'ms', 'no', 'fa', 'pl', 
                                'ro', 'sr', 'sk', 'sl', 'sv', 'th', 'tr', 'uk', 'vi'];
                        $lan_2 = ['de'];
                        $lan_3 = ['zh', 'zt', 'ja'];
                        $lan_4 = ['ko', 'ar', 'bn'];

                        if (in_array($this->language, $lan_1)) {
                            $token = $result->charaters / 4;
                        } elseif (in_array($this->language, $lan_2)) {
                            $token = $result->charaters / 4.5;
                        } elseif (in_array($this->language, $lan_3)) {
                            $token = $result->charaters / 1.5;
                        } elseif (in_array($this->language, $lan_4)) {
                            $token = $result->charaters / 3;
                        } else {
                            $token = 0;
                        }

                        $estimate = $token * (0.015 / 1000);

                        echo '<tr> 
                            <td><input type="checkbox" id="select_title" name="select_title[]" value="' . esc_attr($result->ID). '"></td>
                            <td><a href="' . esc_url($edit_url) . '">' . esc_html($result->post_title) . '</a></td>
                            <td>' . esc_html($token) . '</td>
                            <td>' . esc_html('$' . round($estimate, 5)) . '</td>
                            <td>';
                        
                            switch ($result->status) {
                                case 'sameday':
                                    echo '<div class="instantpub">' . esc_html__( 'Publish', 'super-fast-blog-ai' ) . '</div>';
                                    break;
                                case 'later':
                                    echo '<div class="hourpub">' . esc_html__( 'Scheduled', 'super-fast-blog-ai' ) . '</div>';
                                    break;
                                
                                case 'recurring':
                                    echo '<div class="instantpub">' . esc_html__( 'Recurring', 'super-fast-blog-ai' ) . '</div>';
                                    break;
                            
                                default:
                                    break;
                            }
                            

                        echo '</td>';
                        echo '<td>' . esc_html($result->modelused) . '</td>';
                        $logdate = current_time($result->log_time);
                        $formatted_date = date_i18n('D, M j, Y : h:i A', strtotime($logdate));
                        echo '<td>' . esc_html($formatted_date) . '</td>';
                        echo '<td>
                        <button type="button" class="logedit"><a href="' . esc_url($edit_url) . '"><svg width="18" height="16" viewBox="0 0 18 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.708 5.625C14.783 2.6 11.967.858 9 .858c-1.484 0-2.925.434-4.242 1.242-1.316.817-2.5 2.008-3.466 3.525-.834 1.308-.834 3.433 0 4.742C3.216 13.4 6.033 15.133 9 15.133c1.483 0 2.925-.433 4.242-1.241 1.316-.817 2.5-2.009 3.466-3.525.834-1.3.834-3.434 0-4.742M9 11.367A3.363 3.363 0 0 1 5.633 8c0-1.858 1.5-3.367 3.367-3.367A3.363 3.363 0 0 1 12.367 8c0 1.858-1.5 3.367-3.367 3.367" fill="#8C8C8C"/><path d="M9 5.617a2.38 2.38 0 0 0 0 4.758A2.386 2.386 0 0 0 11.383 8 2.394 2.394 0 0 0 9 5.617" fill="#8C8C8C"/></svg></a></button>
                        <button type="button" class="delbtn" data-title="'.esc_attr($result->id).'"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M17.558 4.358a93 93 0 0 0-4.033-.308v-.008l-.183-1.084c-.125-.766-.309-1.916-2.259-1.916H8.9c-1.942 0-2.125 1.1-2.258 1.908l-.175 1.067c-.775.05-1.55.1-2.325.175l-1.7.166a.626.626 0 0 0-.567.684.62.62 0 0 0 .683.558l1.7-.167c4.367-.433 8.767-.266 13.184.175h.066a.63.63 0 0 0 .625-.566.64.64 0 0 0-.575-.684m-1.533 2.425a1.05 1.05 0 0 0-.758-.325H4.733a1.04 1.04 0 0 0-1.041 1.109l.516 8.55c.092 1.266.209 2.85 3.117 2.85h5.35c2.908 0 3.025-1.575 3.117-2.85l.516-8.542a1.08 1.08 0 0 0-.283-.792m-4.642 8.009H8.608a.63.63 0 0 1-.625-.625.63.63 0 0 1 .625-.625h2.775a.63.63 0 0 1 .625.625.63.63 0 0 1-.625.625m.7-3.334H7.917a.63.63 0 0 1-.625-.625.63.63 0 0 1 .625-.625h4.166a.63.63 0 0 1 .625.625.63.63 0 0 1-.625.625" fill="#8C8C8C"/></svg> </button> </td>';
                      
                        echo  '</tr>';
                    }
                      
                    echo '</tbody>
                    </table>';
                    echo '<div class="row-2">';
                    echo '<button type="button" class="btnstyle" id="delete-selected"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><path d="M11.403 6.027a5.32 5.32 0 0 1-4.114-4.114.548.548 0 0 0-1.073 0 5.32 5.32 0 0 1-4.114 4.114.548.548 0 0 0 0 1.072 5.32 5.32 0 0 1 4.114 4.115.548.548 0 0 0 1.072 0 5.32 5.32 0 0 1 4.115-4.115.548.548 0 0 0 0-1.072m-.906 9.049a2.51 2.51 0 0 1-1.938-1.938.548.548 0 0 0-1.072 0 2.51 2.51 0 0 1-1.939 1.938.548.548 0 0 0 0 1.072 2.51 2.51 0 0 1 1.939 1.939.548.548 0 0 0 1.072 0 2.51 2.51 0 0 1 1.938-1.939.548.548 0 0 0 0-1.072m7.401-5.072a3.39 3.39 0 0 1-2.62-2.62.548.548 0 0 0-1.072 0 3.39 3.39 0 0 1-2.62 2.62.548.548 0 0 0 0 1.073 3.39 3.39 0 0 1 2.62 2.62.548.548 0 0 0 1.072 0 3.39 3.39 0 0 1 2.62-2.62.548.548 0 0 0 0-1.073" fill="#fff"/></svg> 
                    Delete Title
                    </button>';
                    echo '<div id="alert" style="display:none;"></div>';
                    echo '<div id="success-message" style="display:none;"></div>';
                    echo '</div>';
                    
                echo '</div>'; 
            echo '</div>';
        }        

            public function otslf_delete_log_title() {
                // Verify nonce for security
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai-seo-content-nonce' ) ) {
                    wp_send_json_error( __( 'Invalid nonce.', 'super-fast-blog-ai' ) );
                }
            
                // Check if ID is present and valid
                $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
                if ( ! $id ) {
                    wp_send_json_error( __( 'ID is missing or invalid.', 'super-fast-blog-ai' ) );
                }
            
                global $wpdb;
                $schedule_post_table = $wpdb->prefix . 'slf_schedule_post_title_log';
                $cache_key = 'schedule_post_title_log_' . $id;
                wp_cache_delete( $cache_key, 'slf_schedule_post_title_log' );
            
                $deleted = $wpdb->query(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->prepare("DELETE FROM {$schedule_post_table} WHERE id = %d",$id)
                );
            
                if ( false !== $deleted ) {
                    wp_send_json_success( __( 'Record deleted successfully.', 'super-fast-blog-ai' ) );
                } else {
                    wp_send_json_error( __( 'Failed to delete the record.', 'super-fast-blog-ai' ) );
                }
            }




            /* ===================================  
                     Multiple Title Delete 
               ==================================*/
                                

            public function otslf_delete_multiple_blog_title() {
                check_ajax_referer('ai-seo-content-nonce', 'nonce');
                global $wpdb;

                $table_name = $wpdb->prefix . 'slf_schedule_post_title_log';
                $ids = isset($_POST['id']) ? array_map('intval', $_POST['id']) : [];

                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    $query = "DELETE FROM {$table_name} WHERE id IN ($placeholders)";

                    foreach ($ids as $id) {
                        $cache_key = 'schedule_post_title_log_' . $id;
                        wp_cache_delete($cache_key, 'slf_schedule_post_title_log');
                    }
                    $deleted = $wpdb->query($wpdb->prepare($query, $ids));       // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                      if ($deleted) {                                           // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        foreach ($ids as $id) {
                            wp_cache_set('schedule_post_title_log_' . $id, false, 'slf_schedule_post_title_log'); 
                        }
                    }
                }
            
                if ($deleted !== false) {
                    wp_send_json_success(['message' => 'Selected titles deleted successfully.']);
                } else {
                    wp_send_json_error(['message' => 'Failed to delete selected titles.']);
                }
            }     
    }