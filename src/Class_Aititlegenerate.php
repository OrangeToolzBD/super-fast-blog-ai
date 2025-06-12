<?php
/**
 *
 *  orange post title generate
 *
 **/
namespace Orange\AiBlog\Src;

use OpenAI\OpenAIClient;
use OpenAI\OpenAIConfig;
use Orange\AiBlog\Src\Class_Aitools;


class Class_Aititlegenerate
{
    private $apikey;
    private $featuredimage;
    private $contentimage;
    private $pixabyapikey;
    private $wordcount;
    private $aimodel;
    private $faq;
    private $subheading;
    private $htaging;
    private $number_h;
    private $email_notification;
    private $schedule;
    private $slflanguage;
    private $slfstyle;
    private $slftone;
    private $tlanguage;
    private $unplash;
    private $seokey;
    private $seometa;
    private $interlink;
    private $interlinktype;
    private $imgaccess;
    private $table_content;
    private $proscons;

    public function __construct() {

      add_action('wp_ajax_otslf_generate_blog_title', [$this, 'otslf_generate_blog_title']);
      add_action('wp_ajax_otslf_instant_generate_post_content', [$this, 'otslf_instant_generate_post_content']);

      add_action('wp_ajax_otslf_delete_blog_title', [$this, 'otslf_delete_blog_title']);
      add_action('wp_ajax_otslf_update_blog_titles', [$this, 'otslf_update_blog_titles']);
     
      add_action('wp_ajax_otslf_title_generate_seo_keyword', [$this, 'otslf_title_generate_seo_keyword']);
      add_action('wp_ajax_otslf_title_generate_meta_description', [$this, 'otslf_title_generate_meta_description']);  
      
      add_action('wp_ajax_otslf_delete_selected_titles', [$this,'otslf_delete_selected_titles']);

      $this->apikey = trim(get_option('otslf_api_key'));
      $this->featuredimage = get_option('otslf_featured_image');
      $this->contentimage = get_option('otslf_content_image');
      $this->pixabyapikey = get_option('otslf_image_generate_api_key');
      @$this->slflanguage =  get_option('otslf_language_select');  
      @$this->slftone =  get_option('otslf_written_select');
      @$this->slfstyle =  get_option('otslf_language_tone');
      $this->wordcount = get_option('otslf_word_count');
      $this->aimodel = get_option('otslf_model');      
      $this->faq = get_option('otslf_list_faq'); 
      $this->subheading = get_option('otslf_sub_heading');
      $this->htaging = get_option('otslf_htaging');
      $this->number_h = get_option('otslf_number_h');
      $this->email_notification = get_option('otslf_email_notification');
      $this->schedule = get_option('otslf_schedule');
      $this->seokey = get_option('otslf_seokeyword');
      $this->seometa = get_option('otslf_meta_des');
      $this->imgaccess = get_option('otslf_unsplash_generate_api_key');
      $this->table_content = get_option('otslf_table_con');
      $this->proscons = get_option('otslf_poscon');

        if($this->schedule === 'later_same_day') {
            
            $hours_delay = (int) get_option('otslf_hoursInput'); 
            $minutes_delay = (int) get_option('otslf_minutesInput'); 

            // Calculate the delay in seconds
            $delay_in_seconds = ($hours_delay * 3600) + ($minutes_delay * 60);
            // Get the current time
            $current_time = time();
            // Calculate the scheduled time (current time + user-defined delay)
            $next_scheduled_time = $current_time + $delay_in_seconds;
                if (!wp_next_scheduled('otslf_publish_scheduled_posts')) {
                    wp_schedule_single_event($next_scheduled_time, 'otslf_publish_scheduled_posts');
                    add_action('otslf_publish_scheduled_posts', [$this, 'otslf_publish_same_day_later_posts']);
            }

        } elseif ($this->schedule === 'later') {

            $later_days = get_option('otslf_laterdate'); 
            $oclock = get_option('otslf_oclock');        
            // Check if both inputs are set and valid
            if ($later_days !== false && $oclock !== false) {     
                $later_date = gmdate('Y-m-d', strtotime("+$later_days days"));        
                // Combine the dynamically generated date and user-specified time
                $schedule_time = "$later_date $oclock:00";
                $time_to_schedule = strtotime($schedule_time);
                if (!wp_next_scheduled('otslf_publish_posts_event')) {
                    wp_schedule_event($time_to_schedule, 'daily', 'otslf_publish_posts_event');
                    add_action('otslf_publish_posts_event', [$this, 'otslf_publish_scheduled_posts']);
                }
            }    

        } elseif ($this->schedule === 'recurring') {

            $recurdate = get_option('otslf_recurdate');
            $rcuroclock = get_option('otslf_rcuroclock');

            if ($recurdate !== false && $rcuroclock !== false) {
                // Calculate the date by adding the user-specified number of days to the current date
                $recur_date = gmdate('Y-m-d', strtotime("+$recurdate days"));

                $schedule_time = "$recur_date $rcuroclock:00";
                // Convert the combined string into a timestamp
                $time_to_schedule = strtotime($schedule_time);
               // Schedule the recurring event if not already scheduled
                if (!wp_next_scheduled('otslf_publish_scheduled_posts_daily')) {
                    wp_schedule_event($time_to_schedule, 'daily', 'otslf_publish_scheduled_posts_daily');
                    add_action('otslf_publish_scheduled_posts_daily', [$this, 'otslf_publish_daily_scheduled_posts']);
                }
            }
        }
    }
        
            public function otslf_aititel_generate() {
            ?>
                <div class="row-1">
                    <div class="col-3">
                        <?php echo '<h1>' . esc_html__('Blog Title Generate', 'super-fast-blog-ai') . '</h1>'; ?>  
                    </div>
                    <div class="col-4">
                        <div id="show-error" style="display:none;"></div>
                        <div id="success-message" style="display:none;"> </div>
                    </div>
                </div>
                  
                    <div class="row-1 ai-titles-generat"> 
                        <div class="col-1 colbg left-column">   
                            <form id="blog-title-generator" method="post" action="">
                                <div id="vcontent"> 
                                <div class="col-title"> 
                                    <?php echo '<h2>' . esc_html__('Title Generate', 'super-fast-blog-ai') .'<span class="redmark">*</span>' .'</h2>'; ?>
                                </div> 
                                <textarea id="blog_title" name="blog_title" rows="4" cols="100" style="width: 100%; resize: none;" placeholder="What's on your mind ? Start typing here...."></textarea>
                                <small class="error-message" id="prompterror"></small>
                                    <div class="row-1 blog-title-row"> 
                                        <div class="col-5"> <?php  $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_language(); ?> </div>
                                        <div class="col-5"> <?php  $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_writtenstyle(); ?> </div>
                                        <div class="col-5"> <?php  $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_writone(); ?> </div>   
                                        <div class="col-5"> <?php  $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_titlevariant(); ?> </div>  
                                    </div>   
                                    <div class="row-1"> 
                                    <button type="button" disabled class="generate_btn disabled" id="generate_button"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><path d="M11.403 6.027a5.32 5.32 0 0 1-4.114-4.114.548.548 0 0 0-1.073 0 5.32 5.32 0 0 1-4.114 4.114.548.548 0 0 0 0 1.072 5.32 5.32 0 0 1 4.114 4.115.548.548 0 0 0 1.072 0 5.32 5.32 0 0 1 4.115-4.115.548.548 0 0 0 0-1.072m-.906 9.049a2.51 2.51 0 0 1-1.938-1.938.548.548 0 0 0-1.072 0 2.51 2.51 0 0 1-1.939 1.938.548.548 0 0 0 0 1.072 2.51 2.51 0 0 1 1.939 1.939.548.548 0 0 0 1.072 0 2.51 2.51 0 0 1 1.938-1.939.548.548 0 0 0 0-1.072m7.401-5.072a3.39 3.39 0 0 1-2.62-2.62.548.548 0 0 0-1.072 0 3.39 3.39 0 0 1-2.62 2.62.548.548 0 0 0 0 1.073 3.39 3.39 0 0 1 2.62 2.62.548.548 0 0 0 1.072 0 3.39 3.39 0 0 1 2.62-2.62.548.548 0 0 0 0-1.073" fill="#fff"/></svg> 
                                    <span><?php esc_html_e('Generate Title', 'super-fast-blog-ai')?></span>
                                    </button>          
                                    </div>                          
                                </div>
                            </form>
                        </div>

                        <div class="col-2 colbg right-column" id="right-scroll"> 
                        <div class="col-title"> 
                            <h2><?php esc_html_e('Title Variations', 'super-fast-blog-ai'); ?></h2>                                               
                        </div> 
                        <hr class="horizontal"> </hr> 
                        <!-- Start according menu --> 
                            <?php
                                global $wpdb;
                                $cache_key = 'slf_generated_titles';
                                $results = wp_cache_get($cache_key, 'slf_cache_group');
                                
                                if ($results === false) {
                        
                                    $results = $wpdb->get_results(                                               // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                                        "SELECT promt_title, id, ctime, protid, 
                                        GROUP_CONCAT(CONCAT(id, '||', generate_title) ORDER BY id ASC SEPARATOR '\n') AS titles
                                        FROM {$wpdb->prefix}slf_generated_title
                                        GROUP BY promt_title 
                                        ORDER BY ctime DESC"
                                    );

                                    wp_cache_set($cache_key, $results, 'slf_cache_group', 3600);
                                }
                                
                                // Process the results
                                foreach ($results as $result) {
                                    
                                    if (!is_null($result->titles) && trim($result->titles) !== '') {
                                        $titles = array_filter(explode("\n", $result->titles)); // Remove empty titles
                                        if (count($titles) > 0) {        
                                ?>
                                    
                                <div class="topwrapper">
                                    <?php if (isset($result->protid)) : ?>
                                        <input type="checkbox" class="pro-title" name="pro-title" value="<?php echo esc_attr($result->protid); ?>" />
                                    <?php endif; ?>
                                    <div class="ai_accordion">
                                        <div class="ai_accordion-item">
                                            <div class="ai_accordion-header">
                                                <label><?php echo esc_html($result->promt_title); ?></label>
                                                <label class="date-show">
                                                    <?php
                                                        $date = current_time($result->ctime);
                                                        $formatted_date = date_i18n("D, M j, Y", strtotime($date));
                                                        echo esc_html($formatted_date); 
                                                    ?>
                                                </label>
                                                <span class="arrow ai_rotated">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4.667 9.722 8 6.667l3.332 3.055" stroke="#666" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>                                               
                                                </span>
                                            </div>
                                            <div class="ai_accordion-content show">
                                                <div class="ai_inner-content">
                                                    <ul class="ai_accordion-list">
                                                        <?php
                                                            //$titles = explode("\n", $result->titles);
                                                            
                                                            $i = 1;
                                                            foreach ($titles as $title) { 
                                                                list($id, $text) = explode('||', $title, 2);   
                                                                    ?>
                                                                <div class="tooltip">
                                                                    <li><?php echo esc_html($i); $i++; ?>
                                                                    <?php if (isset($title)) : ?>
                                                                        <input type="text" id="blog_titles" name="blog_titles" value="<?php echo esc_attr($text); ?>">
                                                                    <?php endif; ?>
                                                                    </li>
                                                                    <div class="tooltiptext">
                                                                    <button type="button" class="update-title gtbutten" name="update-title" title="<?php echo esc_attr(__('Update', 'super-fast-blog-ai')); ?>" data-title="<?php echo esc_attr($id); ?>">
                                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="m14.114 4.162-2.276-2.27a1.9 1.9 0 0 0-1.346-.559H3.239a1.91 1.91 0 0 0-1.906 1.906v9.522c0 1.05.855 1.906 1.906 1.906h9.529c1.05 0 1.899-.855 1.899-1.906V5.508a1.9 1.9 0 0 0-.553-1.346M10.808 2.62v1.596c0 .08-.067.148-.155.148h-5.3a.15.15 0 0 1-.154-.148v-1.67h5.293q.173.001.316.074m0 10.835h-5.63a.6.6 0 0 0 .02-.149V9.892c0-.08.068-.148.155-.148h5.3a.15.15 0 0 1 .155.148zm2.646-.694c0 .384-.31.694-.686.694h-.748V9.892c0-.754-.613-1.36-1.367-1.36h-5.3c-.754 0-1.367.606-1.367 1.36v3.414c0 .048.007.101.02.149H3.24a.693.693 0 0 1-.694-.694V3.239c0-.384.31-.693.694-.693h.747v1.67c0 .747.613 1.36 1.367 1.36h5.3c.754 0 1.367-.613 1.367-1.36v-.431l1.232 1.232c.135.128.202.31.202.491z" fill="#666" />
                                                                        </svg>
                                                                    </button>
                                                                    <button type="button" class="copy-title gtbutten" title="<?php echo esc_attr(__('copy', 'super-fast-blog-ai')); ?>" data-title="<?php echo esc_attr($text); ?>">
                                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.516 6.516c-.357.357-.583.976-.583 2.084v2.8c0 1.107.226 1.727.583 2.084s.977.583 2.084.583h2.8c1.108 0 1.727-.226 2.084-.583.358-.357.583-.977.583-2.084V8.6c0-1.108-.225-1.727-.583-2.084-.357-.358-.976-.583-2.084-.583H4.6c-1.107 0-1.727.225-2.084.583m-.849-.849c.693-.692 1.707-.934 2.933-.934h2.8c1.226 0 2.24.242 2.933.934s.934 1.707.934 2.933v2.8c0 1.226-.242 2.24-.934 2.932-.693.693-1.707.935-2.933.935H4.6c-1.226 0-2.24-.242-2.933-.934S.733 12.625.733 11.4V8.6c0-1.226.242-2.24.934-2.933" fill="#666" />
                                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.516 2.516c-.357.357-.583.976-.583 2.084v.133H7.4c1.226 0 2.24.242 2.933.934s.934 1.707.934 2.933v1.467h.133c1.108 0 1.727-.226 2.084-.583.358-.357.583-.977.583-2.084V4.6c0-1.108-.225-1.727-.583-2.084-.357-.358-.976-.583-2.084-.583H8.6c-1.107 0-1.727.225-2.084.583m-.849-.849C6.36.975 7.374.733 8.6.733h2.8c1.226 0 2.24.242 2.933.934s.934 1.707.934 2.933v2.8c0 1.226-.242 2.24-.934 2.932-.693.693-1.707.935-2.933.935h-.733a.6.6 0 0 1-.6-.6V8.6c0-1.108-.225-1.727-.583-2.084-.357-.358-.976-.583-2.084-.583H5.333a.6.6 0 0 1-.6-.6V4.6c0-1.226.242-2.24.934-2.933" fill="#666" />
                                                                        </svg>
                                                                    </button>
                                                                    <button type="button" class="delete-title gtbutten" name="delete_title" title="<?php echo esc_attr(__('Trash', 'super-fast-blog-ai')); ?>" data-title="<?php echo esc_attr($id); ?>">
                                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="m6.405 2.54-.088.526a40 40 0 0 1 1.003-.013q1.183 0 2.368.042l-.094-.552c-.056-.349-.09-.44-.132-.49-.01-.011-.103-.12-.589-.12H7.127c-.493 0-.583.105-.59.113a.3.3 0 0 0-.057.127c-.028.09-.047.2-.075.367m4.51.61q1.575.084 3.144.24a.6.6 0 1 1-.118 1.194 67 67 0 0 0-6.621-.33q-1.95 0-3.9.196h-.002l-1.36.134a.6.6 0 0 1-.117-1.195l1.36-.133q.894-.09 1.79-.14l.13-.775.009-.051c.045-.274.116-.708.407-1.038.345-.39.863-.519 1.49-.519h1.746c.636 0 1.153.14 1.495.534.291.335.36.77.404 1.04l.006.041zm-7.52 2.345a.6.6 0 0 1 .637.56l.433 6.71v.001c.02.27.035.476.07.654a.8.8 0 0 0 .134.344c.084.109.321.303 1.191.303h4.28c.87 0 1.107-.194 1.19-.303a.8.8 0 0 0 .134-.344c.036-.178.052-.384.07-.654v-.001l.434-6.71a.6.6 0 1 1 1.197.077l-.433 6.713v.003l-.001.016c-.018.249-.037.53-.09.793a2 2 0 0 1-.358.838c-.43.559-1.153.772-2.143.772H5.86c-.99 0-1.713-.213-2.143-.772a2 2 0 0 1-.358-.838c-.053-.263-.072-.544-.09-.793v-.016l-.001-.003-.433-6.713a.6.6 0 0 1 .56-.637m2.338 2.838a.6.6 0 0 1 .6-.6h3.334a.6.6 0 0 1 0 1.2H6.333a.6.6 0 0 1-.6-.6M6.287 11a.6.6 0 0 1 .6-.6h2.22a.6.6 0 0 1 0 1.2h-2.22a.6.6 0 0 1-.6-.6" fill="#666" />
                                                                        </svg>
                                                                    </button>
                                                                    <button type="button" class="article-generate generatebtn" data-title="<?php echo esc_attr($text); ?>">
                                                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path d="M7.982 4.22a3.72 3.72 0 0 1-2.88-2.881.383.383 0 0 0-.75 0 3.72 3.72 0 0 1-2.88 2.88.383.383 0 0 0 0 .75 3.72 3.72 0 0 1 2.88 2.88.383.383 0 0 0 .75 0 3.72 3.72 0 0 1 2.88-2.88.383.383 0 0 0 0-.75m-.634 6.334a1.75 1.75 0 0 1-1.357-1.357.383.383 0 0 0-.75 0 1.76 1.76 0 0 1-1.357 1.357.383.383 0 0 0 0 .75 1.75 1.75 0 0 1 1.356 1.358.383.383 0 0 0 .751 0 1.75 1.75 0 0 1 1.357-1.357.384.384 0 0 0 0-.75m5.18-3.551a2.37 2.37 0 0 1-1.834-1.834.383.383 0 0 0-.75 0A2.37 2.37 0 0 1 8.11 7.003a.383.383 0 0 0 0 .75 2.37 2.37 0 0 1 1.834 1.835.383.383 0 0 0 .75 0 2.37 2.37 0 0 1 1.834-1.834.383.383 0 0 0 0-.75" fill="#fff" />
                                                                        </svg>
                                                                        <?php esc_html_e('Generate Article', 'super-fast-blog-ai'); ?>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php } ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    } 
                                    }
                                } 
                                ?>
                                <?php
                                    $generated_title_table = $wpdb->prefix . "slf_generated_title";
                                    $result = $wpdb->get_var($wpdb->prepare("SELECT generate_title FROM {$generated_title_table} ORDER BY id DESC LIMIT %d", 1));  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                                    //$result = $wpdb->get_var($query);                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                                ?>
                                <button type="button" class="btnstyle delete-prompt-btn" id="multidel">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">
                                        <path d="M11.403 6.027a5.32 5.32 0 0 1-4.114-4.114.548.548 0 0 0-1.073 0 5.32 5.32 0 0 1-4.114 4.114.548.548 0 0 0 0 1.072 5.32 5.32 0 0 1 4.114 4.115.548.548 0 0 0 1.072 0 5.32 5.32 0 0 1 4.115-4.115.548.548 0 0 0 0-1.072m-.906 9.049a2.51 2.51 0 0 1-1.938-1.938.548.548 0 0 0-1.072 0 2.51 2.51 0 0 1-1.939 1.938.548.548 0 0 0 0 1.072 2.51 2.51 0 0 1 1.939 1.939.548.548 0 0 0 1.072 0 2.51 2.51 0 0 1 1.938-1.939.548.548 0 0 0 0-1.072m7.401-5.072a3.39 3.39 0 0 1-2.62-2.62.548.548 0 0 0-1.072 0 3.39 3.39 0 0 1-2.62 2.62.548.548 0 0 0 0 1.073 3.39 3.39 0 0 1 2.62 2.62.548.548 0 0 0 1.072 0 3.39 3.39 0 0 1 2.62-2.62.548.548 0 0 0 0-1.073" fill="#fff" />
                                    </svg>                                
                                    <?php esc_html_e('Delete Prompt', 'super-fast-blog-ai'); ?>
                                </button>
                                <?php //}
                                ?>
                        <!-- END according menu -->  
                        </div> <!-- end col-2 -->       
                            
                </div>
            <?php 
            }

            public function otslf_delete_selected_titles() {
                global $wpdb;
                
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai-seo-content-nonce' ) ) {
                    wp_send_json_error( __( 'Invalid nonce.', 'super-fast-blog-ai' ) );
                }
                
                if (isset($_POST['selected_pro_titles']) && is_array($_POST['selected_pro_titles'])) {
                    $selected_titles = array_map('absint', wp_unslash($_POST['selected_pro_titles']));
                    
                    if (empty($selected_titles)) {
                        wp_send_json_error(['message' => esc_html__('Invalid title IDs provided.', 'super-fast-blog-ai')]);
                        wp_die();
                    }

                    $placeholders = implode(',', array_fill(0, count($selected_titles), '%d'));
                    $cache_key = 'slf_generated_titles_' . md5(implode('_', $selected_titles));
                    $cache_group = 'slf_titles_cache';
                    
                    wp_cache_delete($cache_key, $cache_group);
                    
                    $generated_name = $wpdb->prefix . 'slf_generated_title';
                    $query = "DELETE FROM {$generated_name} WHERE protid IN ($placeholders)"; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $result = $wpdb->query($wpdb->prepare($query, $selected_titles));
                    //$result = $wpdb->query($prepared);                                     // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    
                    if ($result !== false) {
                        wp_send_json_success(['message' => esc_html__('Titles deleted successfully.', 'super-fast-blog-ai')]);
                    } else {
                        wp_send_json_error(['message' => esc_html__('Failed to delete titles.', 'super-fast-blog-ai')]);
                    }
                } else {
                    wp_send_json_error(['message' => esc_html__('No titles selected.', 'super-fast-blog-ai')]);
                }
                
                wp_die();
            }


                /* ======================================  
                      Instant article generate  
               ====================================*/  


            public function otslf_generate_blog_title() {

                check_ajax_referer('ai-seo-content-nonce', 'nonce');
            
                if (!isset($_POST['blog_title'], $_POST['wvariation'], $_POST['tlanguage'], $_POST['twstyle'], $_POST['writone'])) {
                    wp_send_json_error('All fields are required', 400);
                }
            
                // Sanitize input fields
                $blog_title = sanitize_text_field(wp_unslash($_POST['blog_title']));
                $wvariation = sanitize_text_field(wp_unslash($_POST['wvariation']));
                $tlanguage  = sanitize_text_field(wp_unslash($_POST['tlanguage']));
                $twstyle    = sanitize_text_field(wp_unslash($_POST['twstyle']));
                $writone    = sanitize_text_field(wp_unslash($_POST['writone']));
            
                // Ensure no field is empty
                if (empty($blog_title) || empty($wvariation) || empty($tlanguage) || empty($twstyle) || empty($writone)) {
                    wp_send_json_error('All fields must have a value', 400);
                }
            
                $prompt = 'Generate ' . $wvariation . ' concise, single-sentence blog post titles based on the input: "' . $blog_title . '".';
                $prompt .= ' Avoid possessive forms such as "’s" ';
                $prompt .= ' Use the tone: ' . $writone . ', style: ' . $twstyle . ', and language: ' . $tlanguage . '.';
                $prompt .= ' Ensure each title is a single sentence, without multiple periods, context handling, clear and easy to understand, relevant to the target audience.';
            
                // Setup the request to OpenAI API
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'body' => wp_json_encode(array(
                        'model'    => $this->aimodel,
                        'messages' => array(
                            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                            array('role' => 'user', 'content' => $prompt)
                        ),
                        'max_tokens' => 1000
                    )),
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apikey
                    ),
                    'timeout' => 20,
                ));
            
                // Check for errors in API response
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    wp_send_json_error('Error connecting to OpenAI API: ' . $error_message);
                } else {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
            
                    if (isset($data['choices'][0]['message']['content'])) {
                        // Split and clean the generated titles
                        $titles = explode("\n", $data['choices'][0]['message']['content']);
                        $titles = array_map('trim', $titles);
            
                        $clean_titles = array_filter($titles, function($title) {
                            return !empty($title) && strlen($title) > 3; // Ensure title has at least 3 characters
                        });
            
                        if (empty($clean_titles)) {
                            wp_send_json_error('Generated titles are invalid or empty', 400);
                        }
            
                        // Remove possessive forms and number prefixes
                        $clean_titles = array_map(function($title) {
                            $title = preg_replace('/^\d+\.\s*/', '', $title); // Remove numbering if present
                            $title = preg_replace('/\b(\w+)\'s\b/', '$1', $title); // Remove possessive forms like "’s"
                            return trim($title, '"');
                        }, $clean_titles);
            
                        // Escape titles for HTML
                        $escaped_titles = array_map('esc_html', $clean_titles);
            
                        global $wpdb;
                        $table_name      = $wpdb->prefix . 'slf_generated_title';
                        $generated_title = $wpdb->prefix . 'slf_generated_title';
                        $cache_key       = 'max_protid';
                        $last_protid     = wp_cache_get($cache_key);
            
                        if (false === $last_protid) {
                            $query       = "SELECT MAX(protid) FROM {$generated_title}";
                            $last_protid = (int) $wpdb->get_var($query); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            wp_cache_set($cache_key, $last_protid, '', 3600);
                        }
            
                        $new_protid = $last_protid + 1;
            
                        foreach ($clean_titles as $title) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery  
                                $table_name,
                                array(
                                    'promt_title'    => esc_html($blog_title),
                                    'generate_title' => esc_html($title),
                                    'protid'         => intval($new_protid),
                                ),
                                array('%s', '%s', '%d')
                            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        }
            
                        wp_send_json_success(array_values($escaped_titles));
                    } else {
                        $messageError = "Something went wrong! Please try again";
                        if (isset($data['error']['message']) && !empty($data['error']['message'])) {
                            $messageError = $data['error']['message'];
                        }
            
                        wp_send_json_error($messageError);
                    }
                }
                wp_die();
            }
        
                 
            /* ======================================  
                      Instant article generate  
               ====================================*/    

            public function otslf_instant_generate_post_content() {
                
                check_ajax_referer('ai-seo-content-nonce', 'nonce');
                
                error_log('Artcile generator');

                // Check if the title is passed via AJAX and sanitize it
                if (!isset($_POST['title']) || empty($_POST['title'])) {
                    wp_send_json_error([
                        'message' => esc_html_e('Title is required.', 'super-fast-blog-ai'),
                    ]);
                    wp_die();
                }
            
                $title = sanitize_text_field(wp_unslash($_POST['title']));
                $seokeyword = isset($_POST['titlekeywords']) ? sanitize_text_field(wp_unslash($_POST['titlekeywords'])) : '';
                $metadescrip = isset($_POST['titlemeta']) ? sanitize_text_field(wp_unslash($_POST['titlemeta'])) : '';
                
                $keywordSeparet = explode(", ", $seokeyword);
                
                global $wpdb;            
                try {
                    $config = new OpenAIConfig($this->apikey);
                    $client = new OpenAIClient($config);

                    $user_word_count = intval($this->wordcount); 
                    $estimated_tokens = intval($user_word_count * 1.5); 

                    $max_token_limit = 8090;
                    $estimated_tokens = min($estimated_tokens, $max_token_limit);

                    // Prepare the prompt

                        // Initialize the prompt array
                        $prompt = [];
                    
                        $prompt = [

                            [
                                'role' => 'system',
                                'content' => esc_html__('You are an expert SEO assistant that generates well-structured, keyword-optimized, and SEO-friendly content. Ensure the content is engaging, informative, and easy to read.', 'super-fast-blog-ai')
                            ],
                            [
                                'role' => 'user',   
                                'content' => sprintf(     
                                    esc_html__('Generate a detailed, SEO-optimized article on "%1$s" with at least "%2$d" words. Follow these SEO guidelines:', 'super-fast-blog-ai'),
                                    esc_html($title),
                                    $user_word_count
                                )
                               
                            ],
                            [
                                'role' => 'user',
                                    'content' => sprintf(
                                        /* translators: %1$s, %2$s, %3$s, %4$s, %5$s: Main keyword used multiple times in different contexts,
                                        * %6$s, %7$s, %8$s, %9$s: Secondary keywords used throughout the article */
                                        esc_html__(
                                            '1. Use the first generated main keyword "%1$s" within the first 100 words of the article.
                                    2. Include the first keyword "%2$s" in the <h1>, <h2>, and <h3> tags.
                                    3. Place the first keyword "%3$s" naturally and contextually in a few paragraphs.
                                    4. Use the first keyword "%4$s" 8-12 times in a 1000-word article and 10-12 times in a 1500-word article.
                                    5. Place the first keyword "%5$s" in relevant places throughout the article, including the last paragraph.
                                    6. Use the following secondary keywords naturally in the article, ensuring a density of 0.5%%–1%%:
                                    - %6$s
                                    - %7$s
                                    - %8$s
                                    - %9$s
                                    7. Use one secondary keyword every 100-150 words, ensuring the content remains natural and engaging.
                                    8. End the article with a concluding paragraph that includes the main keyword.
                                    9. Use clear and informative subheadings to improve readability and SEO.',
                                            'super-fast-blog-ai'
                                        ),
                                        esc_html($keywordSeparet[0]),
                                        esc_html($keywordSeparet[0]),
                                        esc_html($keywordSeparet[0]),
                                        esc_html($keywordSeparet[0]),
                                        esc_html($keywordSeparet[0]),
                                        esc_html($keywordSeparet[1]),
                                        esc_html($keywordSeparet[2]),
                                        esc_html($keywordSeparet[3]),
                                        esc_html($keywordSeparet[4])
                                    )
                                ],
                                [
                                    'role' => 'user',
                                    'content' => esc_html__('Ensure the article contains no more than 10% passive sentences.', 'super-fast-blog-ai')
                                ],
                                [
                                    'role' => 'user',
                                    'content' => esc_html__('Ensure that no more than 25% of the sentences in the article contain more than 20 words.', 'super-fast-blog-ai')
                                ],
                                [
                                    'role' => 'user',
                                    'content' => esc_html__('Ensure at least 30% of the sentences in the article contain transition words to improve readability.', 'super-fast-blog-ai')
                                ]    
                        ];   

                    
                     
                  // Include FAQ section if requested
                    if ($this->faq === '1') {
                        $prompt[] = [
                            'role' => 'user',
                            'content' => esc_html__('Add a FAQ section at the end of the article to answer common questions.', 'super-fast-blog-ai')
                        ];
                    }

                    // Conditionally add the table of contents section if table_content equals '1'
                        /* if ($this->table_content === '1') {
                          $prompt[] =  [
                                'role'    => 'user',
                                'content' => esc_html__(
                                    'After writing the introduction of the article, create a "Table of Contents" section. 
                                    Use a bulleted list (or numbered list) for the major headings of the article. 
                                    For each item in the list, use descriptive anchor text that links to the corresponding heading in the article. 
                                    The table of contents should appear immediately after the introduction, clearly labeled, 
                                    and should resemble the following format:
                            
                                    Table of Contents
                                    - [Heading 1]
                                    - [Heading 2]
                                    - [Heading 3]
                                    - [Heading 4]
                                
                            
                                    Each entry in the table of contents should match the headings used in the article and provide a brief overview 
                                    or link to that section.',
                                    'super-fast-blog-ai'
                                )
                            ];
                        } */    

                        if ( $this->table_content === '1' ) {
                                $prompt[] = [
                                    'role'    => 'user',
                                    'content' => esc_html__(
                                        'After writing the introduction, insert a **Table of Contents** wrapped in a container with a light-gray background (#d7d6d5). For example:

                                <div style="background-color:#d7d6d5; padding:16px; border-radius:4px;">
                                <strong>Table of Contents</strong>
                                <ul>
                                <li><a href="#heading-1">Heading 1</a></li>
                                <li><a href="#heading-2">Heading 2</a></li>
                                <li><a href="#heading-3">Heading 3</a></li>
                                <li><a href="#heading-4">Heading 4</a></li>
                                </ul>
                                </div>

                                Use inline CSS exactly as shown (`background-color:#d7d6d5; padding:16px;`) and ensure each link’s href matches the generated heading anchors.',
                                            'super-fast-blog-ai'
                                        ),
                                    ];
                        }


                        // Pros & Cons as styled HTML table
                        if ( $this->proscons === '1' ) {
                                $prompt[] = [
                                    'role'    => 'user',
                                    'content' => esc_html__(
                                        'Include a **Pros and Cons** section formatted as an HTML table with inline CSS styling. The table should look like this:

                            <table>
                            <tr>
                                <th style="background-color:red;color:white;">Pros</th>
                                <th style="background-color:green;color:white;">Cons</th>
                            </tr>
                            <tr>
                                <td>First pro point</td>
                                <td>First con point</td>
                            </tr>
                            <!-- …more rows as needed… -->
                            </table>

                            Use only the `<table>`, `<tr>`, `<th>`, and `<td>` tags for this section, and apply the inline styles exactly as shown:  
                            – Pros cells: `background-color:red; color:white;`  
                            – Cons cells: `background-color:green; color:white;`',
                                        'super-fast-blog-ai'
                                    ),
                            ];
                        }

                    // Include subheadings with specified heading tags and count if requested
                    if ($this->subheading === '1') {
                        $prompt[] = [
                            'role' => 'user',
                            'content' => sprintf(   /* Translators: is used tag number %1$s, %2$s is the used for H group tag.*/
                                esc_html__('Incorporate %1$d subheadings using %2$s tags to structure the content. Ensure each subheading contains relevant keywords.', 'super-fast-blog-ai'),
                                $this->number_h,
                                esc_html($this->htaging)
                            )
                        ];
                    }
                    
                    $prompt[] = [
                        'role' => 'user',
                        'content' => esc_html__('Do not use unnecessary HTML tags like <HTML>, <body>, or <article>. Only use <h1>, <h2>, <h3>, <p>, <ul>, <li>, <b>, and <i> tags where relevant. Ensure optimal readability and proper keyword density.', 'super-fast-blog-ai')
                    ]; 

                    
                    // Make the API call with dynamic word count
                    $response = $client->chat($this->aimodel, $prompt, intval($estimated_tokens));
                    
                    $content = $response['choices'][0]['message']['content'] ?? '';

                    error_log('Artcile generator', '1111');
            
                    $ot_taxonomy = get_option('otslf_ot_taxonomy', []);
                    if (!is_array($ot_taxonomy)) {
                        $ot_taxonomy = [];
                    }
            
                    $stringcount = mb_strlen($content, 'UTF-8');
            
                    if (!empty($content)) {
                        $post_data = [
                            'post_title'   => $title,
                            'post_content' => wp_kses($content, wp_kses_allowed_html('post')),
                            'post_status'  => 'draft',  
                            'post_author'  => get_current_user_id(),
                            'post_date'    => current_time('mysql'),
                            'post_name'    => sanitize_title($title),
                        ];
            
                        $post_id = wp_insert_post($post_data);
            
                        if ($post_id) {

                            //$this->otslf_update_seo_meta_generate($post_id, $seokeyword, $metadescrip);
                            if ($this->seokey == 1 || $this->seometa == 1){
                                $this->otslf_update_seo_meta_generate($post_id, $seokeyword, $metadescrip);
                            }
                                
                            
                                
                            wp_set_post_terms($post_id, $ot_taxonomy, 'category');
                            $this->otslf_set_featured_image($post_id, $seokeyword, $title);
                            
                            // Insert log entry
                            $schedule_post = $wpdb->prefix . 'slf_schedule_post_title_log';
                            $data = [
                                'title'      => $title,
                                'status'     => $this->schedule,
                                'postid'     => $post_id,
                                'charaters'  => $stringcount,
                                'modelused'  => $this->aimodel,
                                'log_time'   => current_time('mysql'),
                            ];
                            $wpdb->insert($schedule_post, $data);                                 // phpcs:ignore WordPress.DB.DirectDatabaseQuery                
            
                            $table = $wpdb->prefix . 'slf_generated_title';
                            $cache_key = 'title_' . md5($title);          
                            $wpdb->delete($table, ['generate_title' => $title]);                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            wp_cache_delete($cache_key);
            
                            // Send email to admin if enabled
                            if (get_option('otslf_email_notification') === '1') {
                                $this->otslf_post_email_notification($post_id);       
                            }

                            error_log('Article-2', '222');
            
                            // Send success response
                            wp_send_json_success([
                                'message' => esc_html__('Post published successfully.', 'super-fast-blog-ai'),
                                'title'   => $title
                            ]);
                            return;
                        } else {
                            wp_send_json_error(['message' => esc_html__('Failed to insert post.', 'super-fast-blog-ai')]);
                            return;
                        }
                    } else {
                        wp_send_json_error(['message' => esc_html__('Generated content was empty.', 'super-fast-blog-ai')]);
                        return;
                    }
                } catch (Exception $e) {
                    wp_send_json_error(['message' => esc_html__('Error: Failed to generate content. Please try again later.', 'super-fast-blog-ai'), 'error' => $e->getMessage()]);
                    return;
                }
            }
            
          

                                                        

                    
                 /*===========================================================
                    Keyword & meta description define in Yoast or Rank Math
                ============================================================= */

            public function otslf_update_seo_meta_generate($post_id, $seokeyword, $metadescrip ) {
                
                $yoast_seo = 'wordpress-seo/wp-seo.php';
                $rank_math_seo = 'seo-by-rank-math/rank-math.php';
          
                if ( is_plugin_active( $yoast_seo ) ) {
                    if(get_option('otslf_seokeyword') ==='1') {
                    update_post_meta( $post_id, '_yoast_wpseo_focuskw', $seokeyword );

                    }if(get_option('otslf_meta_des') ==='1') {
                    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $metadescrip );
                    }        

                } elseif ( is_plugin_active( $rank_math_seo ) ) {

                    if(get_option('otslf_seokeyword') ==='1') {
                    update_post_meta( $post_id, 'rank_math_focus_keyword', $seokeyword );

                    }if(get_option('otslf_meta_des') ==='1') {
                    update_post_meta( $post_id, 'rank_math_description', $metadescrip );
                    }

                } 
            }    


                    /*================================
                          SEO keyword Generate
                    ================================ */

            public function otslf_title_generate_seo_keyword() {

                check_ajax_referer('ai-seo-content-nonce', 'nonce');
            
                if (!isset($_POST['blog_title'])) {
                    wp_send_json_error('Blog title not provided', 400);
                }
                
                error_log('Keyword-1');

                $blog_title = sanitize_text_field(wp_unslash($_POST['blog_title']));
            
                // Modify the prompt to ask for keywords directly
                $prompt = 'Provide only a list of five SEO keywords based on the following input: ' . $blog_title;
            
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'body' => wp_json_encode(array(
                        'model' => $this->aimodel,
                        'messages' => array(
                            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                            array('role' => 'user', 'content' => $prompt)
                        ),
                        'max_tokens' => 1000
                    )),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apikey
                    ),
                    'timeout' => 20,
                ));

                error_log('Keyword-2');

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    wp_send_json_error('Error connecting to OpenAI API: ' . $error_message);
                } else {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
            
                    if (isset($data['choices'][0]['message']['content'])) {
                        $titles = explode("\n", $data['choices'][0]['message']['content']);
                        $titles = array_map('trim', $titles);
            
                        // Filter out unwanted phrases and empty lines
                        $clean_titles = array_filter($titles, function($title) {
                            return !empty($title) && !preg_match('/^Here are|^Certainly|^Sure|
                            ^given topic/', $title);
                        });
                error_log('Keyword-3');    
                        // Remove numbering and any unnecessary quotes
                        $clean_titles = array_map(function($title) {
                            $title = preg_replace('/^\d+\.\s*/', '', $title);
                            return trim($title, '"');
                        }, $clean_titles);
            
                        $escaped_titles = array_map('esc_html', $clean_titles);
            
                        wp_send_json_success(array_values($escaped_titles));
                    } else {
                        $messageError = "Something went wrong! Please try again";
                        if (isset($data['error']['message']) && !empty($data['error']['message'])) {
                            $messageError = $data['error']['message'];
                        }
            
                        wp_send_json_error($messageError);
                    }
                }
                wp_die();
            }
            

                /*================================
                      SEO Meta desription
                ================================ */


            public function otslf_title_generate_meta_description() {

                check_ajax_referer('ai-seo-content-nonce', 'nonce');
            
                if (!isset($_POST['blog_title'])) {
                    wp_send_json_error('Blog title not provided', 400);
                }

                error_log('Meta description error-1');

                $blog_title = sanitize_text_field(wp_unslash($_POST['blog_title']));
                $prompt = 'Write a SEO-friendly 160-character meta description based on the following input: ' . $blog_title;
            
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'body' => wp_json_encode(array(
                        'model' => $this->aimodel,
                        'messages' => array(
                            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                            array('role' => 'user', 'content' => $prompt)
                        ),
                        'max_tokens' => 50,
                    )),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apikey
                    ),
                    'timeout' => 30,
                ));

                error_log('Meta description error-2');

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    wp_send_json_error('Error connecting to OpenAI API: ' . $error_message);
                } else {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
            
                    if (isset($data['choices'][0]['message']['content'])) {
                        $meta_description = trim($data['choices'][0]['message']['content']);
                        wp_send_json_success(esc_html($meta_description));
                    } else {
                        $messageError = "Something went wrong! Please try again";
                        if (isset($data['error']['message']) && !empty($data['error']['message'])) {
                            $messageError = $data['error']['message'];
                        }
            
                        wp_send_json_error($messageError);
                    }
                }
            }



            /*=================================================
                    for get the email notification to Admin 
            ================================================= */  


            public function otslf_post_email_notification($post_id) {

                    $admin_email = get_option('otslf_admin_email');
                    $subject = esc_html_e('New post generated for your blog', 'super-fast-blog-ai');
                    $post_url = get_permalink($post_id);
                                                 
                    $message = printf(       /* Translators: is used post title %1$s, %2$s is the used for post url.*/
                            esc_html__('A new post titled "%1$s" has been generated. You can view it here: %2$s', 'super-fast-blog-ai'),
							esc_html($post_title),
							esc_url($post_url)
						);

                   wp_mail($admin_email, $subject, $message);
            }

            /* ===== schedule keyword generat ===== */
            
            public function otslf_schedule_generate_seo_keywords( $title ) {
                // Prepare the prompt for keyword generation
                $prompt = 'Provide only a list of five SEO keywords based on the following input: ' . $title;
                
                // Call the OpenAI API
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'body'    => wp_json_encode(array(
                        'model'    => $this->aimodel,
                        'messages' => array(
                            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                            array('role' => 'user', 'content' => $prompt)
                        ),
                        'max_tokens' => 1000
                    )),
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apikey
                    ),
                    'timeout' => 20,
                ));
                
                if (is_wp_error($response)) {
                    return new WP_Error('openai_error', 'Error connecting to OpenAI API.');
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['choices'][0]['message']['content'])) {
                    $titles = explode("\n", $data['choices'][0]['message']['content']);
                    $titles = array_map('trim', $titles);
                    
                    // Filter out any unwanted phrases and empty lines
                    $clean_titles = array_filter($titles, function($title) {
                        return !empty($title) && !preg_match('/^Here are|^Certainly|^Sure|^given topic/', $title);
                    });
                    
                    // Remove numbering and extra quotes
                    $clean_titles = array_map(function($title) {
                        $title = preg_replace('/^\d+\.\s*/', '', $title);
                        return trim($title, '"');
                    }, $clean_titles);
                    
                    return array_values($clean_titles);
                } else {
                    return new WP_Error('openai_response_error', 'Keyword generation failed.');
                }
            }


            /* ========= Schedule Meta Description generat ======== */
            
            public function otslf_schedule_generate_meta_description( $title ) {
                $prompt = 'Write a SEO-friendly 160-character meta description based on the following input: ' . $title;
            
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'body'    => wp_json_encode(array(
                        'model'      => $this->aimodel,
                        'messages'   => array(
                            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                            array('role' => 'user', 'content' => $prompt)
                        ),
                        'max_tokens' => 50,
                    )),
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apikey
                    ),
                    'timeout' => 30,
                ));
            
                if ( is_wp_error( $response ) ) {
                    return new WP_Error('openai_error', 'Error connecting to OpenAI API.');
                }
            
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );
            
                if ( isset( $data['choices'][0]['message']['content'] ) ) {
                    return trim( $data['choices'][0]['message']['content'] );
                } else {
                    return new WP_Error('openai_response_error', 'Meta description generation failed.');
                }
            }
            


            public function otslf_schedule_article_publish($title) {

				global $wpdb;
				$generated_title = $wpdb->prefix . 'slf_generated_title';
				$cache_key = 'latest_generated_title_value';
				$title = wp_cache_get($cache_key);
				if (false === $title) {
				   $query = "SELECT generate_title FROM $generated_title ORDER BY id DESC LIMIT 1";
				   $title = $wpdb->get_var($query);                                         // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				   wp_cache_set($cache_key, $title, '', 3600); 
				}
                
                try {
                    $config = new OpenAIConfig($this->apikey);
                    $client = new OpenAIClient($config);

                    $prompt = [
                        [
                            'role' => 'system',
                            'content' => esc_html_e('You are a helpful assistant that generates SEO-friendly content based on user input.', 'super-fast-blog-ai')
                        ],
                        [
                            'role' => 'user',
                            'content' => sprintf(
                                "%s %s. %s %s. %s %s. %s %s.",
                                esc_html_e('Generate content based on the following input:', 'super-fast-blog-ai'), esc_html($title),
                                esc_html_e('Use the following tone:', 'super-fast-blog-ai'), esc_html($this->slftone),
                                esc_html_e('Use the following style:', 'super-fast-blog-ai'), esc_html($this->slfstyle),
                                esc_html_e('Use the following language:', 'super-fast-blog-ai'), esc_html($this->slflanguage)
                            )
                        ]
                    ];
            
                    if ($this->faq === '1') {
                        $prompt[] = [
                            'role' => 'user',
                            'content' => esc_html_e('Also include FAQ at the bottom of the content,question would be bold.', 'super-fast-blog-ai')
                        ];
                    }
            
                    if ($this->subheading === '1') {
                        $prompt[] = [
                            'role' => 'user',
                            'content' => sprintf(  /* Translators: is used tag number %1$s, is the used H group tag %2$s.*/
									esc_html__('Also include subheading %1$s%2$s:', 'super-fast-blog-ai'),
									esc_html($this->number_h),
									esc_html($this->htaging)
								),
                        ];
                    }
            
                    $prompt[] = [
                        'role' => 'user',
                        'content' => esc_html_e('Do not use HTML tags like <HTML> or <body>. No need for <article> or extra tags. Use <h1> for the title at the beginning. The content should be SEO-friendly with keywords at the beginning of paragraphs.', 'super-fast-blog-ai')
                    ];
            

                    $response = $client->chat($this->aimodel, $prompt, intval($maxWord));
            
                    $content = $response['choices'][0]['message']['content'] ?? '';
            
                    $ot_taxonomy = get_option('otslf_ot_taxonomy', []);
                    if (!is_array($ot_taxonomy)) {
                        $ot_taxonomy = [];
                    }
            
                    $stringcount = mb_strlen($content, 'UTF-8');
            
                    if (!empty($content)) {
                        $post_data = [
                            'post_title'   => $title,
                            'post_content' => wp_kses($content, wp_kses_allowed_html('post')),
                            'post_status'  => 'draft',  
                            'post_author'  => get_current_user_id(),
                            'post_date'    => current_time('mysql'),
                        ];
                            
                        $post_id = wp_insert_post($post_data);
            
                        if ($post_id) {
                            wp_set_post_terms($post_id, $ot_taxonomy, 'category');
            
                            // Set featured image
                            $this->otslf_set_featured_image($post_id, $title);
                            
                            // Insert log entry
                            $table_name = $wpdb->prefix . 'slf_schedule_post_title_log';
                            $data = [
                                'title' => $title,
                                'status' => 'Publish',
                                'postid' => $post_id,
                                'charaters' => $stringcount,
                                'modelused' => $this->aimodel,
                                'log_time' => current_time('mysql'),
                            ];
                            $wpdb->insert($table_name, $data);        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							
							$table = $wpdb->prefix . 'slf_generated_title';
							$cache_key = 'generated_title_' . $title;
							$wpdb->delete($table, ['generate_title' => $title]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							wp_cache_delete($cache_key);


                            $seokeyword = $this->otslf_schedule_generate_seo_keywords( $title );
                            $metadescrip = $this->otslf_schedule_generate_meta_description( $title );

                            $yoast_seo = 'wordpress-seo/wp-seo.php';
                            $rank_math_seo = 'seo-by-rank-math/rank-math.php';
                            
                      
                            if ( is_plugin_active( $yoast_seo ) ) {
                                if(get_option('otslf_seokeyword') ==='1') {
                                update_post_meta( $post_id, '_yoast_wpseo_focuskw', $seokeyword );
            
                                }if(get_option('otslf_meta_des') ==='1') {
                                update_post_meta( $post_id, '_yoast_wpseo_metadesc', $metadescrip );
                                }        
            
                            } elseif ( is_plugin_active( $rank_math_seo ) ) {
            
                                if(get_option('otslf_seokeyword') ==='1') {
                                update_post_meta( $post_id, 'rank_math_focus_keyword', $seokeyword );
            
                                }if(get_option('otslf_meta_des') ==='1') {
                                update_post_meta( $post_id, 'rank_math_description', $metadescrip );
                                }
                            } 


                           // Send success response
                            wp_send_json_success([
                                'message' => esc_html_e('Post published successfully.', 'super-fast-blog-ai'),
                                'title' => $title
                            ]);
                            return;
                        } else {
                            // Send error response if post insertion fails
                            wp_send_json_error(['message' => esc_html_e('Failed to insert post.', 'super-fast-blog-ai')]);
                            return;
                        }
                    } else {
                        // Send error response if generated content is empty
                        wp_send_json_error(['message' => esc_html_e('Generated content was empty.', 'super-fast-blog-ai')]);
                        return;
                    }
                } catch (Exception $e) {
                    // Send error response in case of exception
                    wp_send_json_error(['message' => esc_html_e('Error: Failed to generate content. Please try again later.', 'super-fast-blog-ai'), 'error' => $e->getMessage()]);
                    return;
                }
            }



            
              
              /*================================
                        Featured image
                ================================ */  

            public function otslf_set_featured_image($post_id, $seokeyword, $title) { 
                            
                if ($this->featuredimage == 'pixabay') {
                    $image_url = $this->otslf_get_pixabay_image($post_id, $seokeyword, $title);
                    $is_temp_file = false;

                } elseif ($this->featuredimage == 'dalle3') {
                    $image_url = $this->otslf_generate_dalle_image_for_post($post_id, $seokeyword, $title);
                    $is_temp_file = true;
                    
                } elseif ($this->featuredimage == 'unsplash') {
                    $image_url = $this->otslf_set_featured_image_from_unsplash($post_id, $seokeyword, $title);
                    $is_temp_file = false; 
                }
            
                if ($image_url) {
                    $upload_dir = wp_upload_dir();

                    if ($is_temp_file) {
                        $temp_file = $image_url;
                        
                    } else {
                        $temp_file = download_url($image_url);
                    }
            
                    $file_array = [
                        'name' => sanitize_title($title) . '-' . uniqid() . '.jpg',
                        'tmp_name' => $temp_file
                    ];
            
                    if (isset($upload_dir['error']) && $upload_dir['error'] !== false) {
                        wp_delete_file($temp_file);
                        return false;
                    }
            
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
            
                    $attach_id = media_handle_sideload($file_array, $post_id);
            
                    wp_delete_file($temp_file);
            
                    $result = set_post_thumbnail($post_id, $attach_id);
                }
                
            }

            /*================================
                        Pixaby API
            ================================ */  

            
            /* Try to findout good image using more keyword */

            public function otslf_get_pixabay_image($post_id, $seokeyword, $title) {
    
                if ($this->featuredimage !== 'pixabay') {
                    return false;
                }
                
                $api_key = $this->pixabyapikey;
            
                // Process SEO keyword
                $keyword_separate = array_filter(array_map('trim', explode(",", $seokeyword)));
            
                // If no valid keywords, fallback to title
                if (empty($keyword_separate) && !empty($title)) {
                    $keyword_separate = [trim($title)];
                }
            
                error_log('Searching Image for Keyword(s): ' . implode(", ", $keyword_separate));
            
                foreach ($keyword_separate as $term) {
                    if (empty($term)) continue;
            
                    // Construct API URL
                    $url = add_query_arg([
                        'key'        => $api_key,
                        'q'          => urlencode($term),
                        'image_type' => 'photo',
                        'safesearch' => 'true',
                        'order'      => 'popular', // Sort by popularity
                        'per_page'   => 5 // Fetch multiple images for better selection
                    ], 'https://pixabay.com/api/');
            
                    // Fetch data from Pixabay
                    $response = wp_remote_get($url, [
                        'timeout'   => 15,
                        'sslverify' => true
                    ]);
            
                    if (is_wp_error($response)) {
                        error_log('Pixabay API Error: ' . $response->get_error_message());
                        continue;
                    }
            
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
            
                    if (!empty($data['hits'])) {
                        // Sort images by relevance (if needed)
                        usort($data['hits'], function ($a, $b) {
                            return $b['views'] - $a['views']; // Most viewed image first
                        });
            
                        // Return the best-matching image URL
                        return $data['hits'][0]['largeImageURL'];
                    }
                }
            
                return false;
            }
                


            /*================================
                    DALL-E IMAGE API
            ================================ */   

                /* image generate by the title */
                     
            public function otslf_generate_dalle_image_for_post($title) {
                $apiKey = $this->apikey;
                $apiEndpoint = 'https://api.openai.com/v1/images/generations';
                
                $data = [
                    'prompt' => 'You analyze the prompt need a help from google images to generate the image. 
                                 The generated image needs to be perfect :' .$title,
                    'n' => 1,
                    'size' => '512x512',
                    'quality' => 'standard',
                    'response_format' => 'url'
                ];
            
                $args = [
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking' => true,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $apiKey
                    ],
                    'body' => wp_json_encode($data),
                    'cookies' => []
                ];
            
                try {

                    $response = wp_remote_post($apiEndpoint, $args);
                    $body = wp_remote_retrieve_body($response);
                    $responseData = json_decode($body, true);
            
                    if (isset($responseData['data'][0]['url'])) {
                        $imageUrl = $responseData['data'][0]['url'];
                        // Download image
                        $temp_file = download_url($imageUrl);
                       
                    } else {
                        //error_log('DALL-E Debug: No image URL in response. Response body: ' . $body);
                        return false;
                    }
                } catch (Exception $e) {
                   // error_log('DALL-E Debug: Exception: ' . $e->getMessage());
                    return false;
                }
            }
            
                /*================================
                        Unsplash image API
                ================================ */   

            public function otslf_set_featured_image_from_unsplash_sss($post_id, $seokeyword, $title) {
                // Set up the Unsplash API key and search URL.
                $api_key = $this->$imgaccess;
                
                error_log('Title :' . $title);

                $api_url = "https://api.unsplash.com/search/photos?query=" . urlencode($title) . "&client_id=" . $api_key;
                
                error_log('Fetching image from Unsplash');
                
                // Fetch image data from Unsplash.
                $response = wp_remote_get($api_url);
                if (is_wp_error($response)) {
                    error_log('Unsplash API request failed: ' . $response->get_error_message());
                    return false;
                }
                
                error_log('Unsplash image fetched');
                
                // Check the HTTP response code.
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    error_log("Unsplash API returned HTTP code: $response_code");
                    return false;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                error_log('Processing Unsplash API response');
                
                // Check if the results array exists and contains a valid image URL.
                if (!is_array($data) || empty($data['results'][0]['urls']['regular'])) {
                    error_log("Unsplash API response is missing a valid image URL.");
                    return false;
                }
                
                // Get the regular-sized image URL from the first result.
                $image_url = $data['results'][0]['urls']['regular'];
                error_log('Unsplash Image url: ' . $image_url);
                return $image_url;
            }

            public function otslf_set_featured_image_from_unsplash_zzz($post_id, $seokeyword, $title) {
                // Separate the keywords and remove empty values.
                $keywords = array_filter(array_map('trim', explode(",", $seokeyword)));
                
                $api_key = $this->$imgaccess;
                
                // Normalize title words for comparison.
                $title_words = array_map('strtolower', explode(" ", $title));
                
                // Loop through each keyword to find a valid image.
                foreach ($keywords as $term) {
                    // Normalize the keyword words.
                    $term_words = array_map('strtolower', explode(" ", $term));
                    // Find common words between the title and the keyword.
                    $common_words = array_intersect($title_words, $term_words);
                    
                    error_log('common words' . $common_words);
                    
                    // If common words exist, use them; otherwise, use the original keyword.
                    $search_query = !empty($common_words) ? implode(" ", $common_words) : $term;
                    
                    // Build the API URL using the refined query.
                    $api_url = "https://api.unsplash.com/search/photos?query=" . urlencode($search_query) . "&client_id=" . $api_key;
                    error_log('Fetching image from Unsplash for term: ' . $term . ' with query: ' . $search_query);

                    error_log('URL' . $api_url);

                    // Fetch image data from Unsplash.
                    $response = wp_remote_get($api_url);
                    if (is_wp_error($response)) {
                        error_log('Unsplash API request failed for term ' . $term . ': ' . $response->get_error_message());
                        continue; // Try next keyword.
                    }
                    
                    // Check HTTP response code.
                    $response_code = wp_remote_retrieve_response_code($response);
                    if ($response_code !== 200) {
                        error_log("Unsplash API returned HTTP code $response_code for term: " . $term);
                        continue; // Try next keyword.
                    }
                    
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    error_log('Processing Unsplash API response for term: ' . $term);
                    
                    // Check if the results array exists and contains a valid image URL.
                    if (!is_array($data) || empty($data['results'][0]['urls']['regular'])) {
                        error_log("Unsplash API response for term '$term' is missing a valid image URL.");
                        continue; // Try next keyword.
                    }
                    
                    // Valid image found – log and return it.
                    $image_url = $data['results'][0]['urls']['regular'];
                    error_log('Unsplash Image URL for term ' . $term . ': ' . $image_url);
                    return $image_url;
                }
                
                // If no valid image is found after checking all keywords.
                error_log('No valid Unsplash image found for any keyword.');
                return false;
            }
                                      
            public function otslf_set_featured_image_from_unsplash($post_id, $seokeyword, $title) {    //keyword separate
                // Separate the keywords and remove empty values.
                $keyword_separate = array_filter(array_map('trim', explode(",", $seokeyword)));
            
                $api_key = $this->imgaccess;
                
                $common_words_string = implode(', ', $keyword_separate);

                error_log('Common Words' . $common_words_string);

                // Loop through each keyword to find a valid image.
                foreach ($keyword_separate as $term) {

                    error_log('Common Terms' . $term);

                    $api_url = "https://api.unsplash.com/search/photos?query=" . urlencode($term) . "&client_id=" . $api_key;
                    
                    error_log('Fetching image from Unsplash for term: ' . $term);
                    
                    // Fetch image data from Unsplash.
                    $response = wp_remote_get($api_url);
                    if (is_wp_error($response)) {
                        error_log('Unsplash API request failed for term ' . $term . ': ' . $response->get_error_message());
                        continue; // Try next keyword instead of returning false immediately.
                    }
                    
                    // Check the HTTP response code.
                    $response_code = wp_remote_retrieve_response_code($response);
                    if ($response_code !== 200) {
                        error_log("Unsplash API returned HTTP code $response_code for term: " . $term);
                        continue; // Try next keyword.
                    }
                    
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    error_log('Processing Unsplash API response for term: ' . $term);
                    
                    // Check if the results array exists and contains a valid image URL.
                    if (!is_array($data) || empty($data['results'][0]['urls']['regular'])) {
                        error_log("Unsplash API response for term $term is missing a valid image URL.");
                        continue; // Try next keyword.
                    }
                    // If a valid image is found, log it and return.
                    $image_url = $data['results'][0]['urls']['regular'];
                    error_log('Unsplash Image URL for term ' . $term . ': ' . $image_url);
                    return $image_url;
                }
                
                // If no keyword produced a valid image URL, log and return false.
                error_log('No valid Unsplash image found for any keyword.');
                return false;
            }
            
            
            
              /*=======================================
                    Latter day publish scheudle post 
                ========================================*/

                public function otslf_publish_scheduled_posts() {
                    global $wpdb;
                    
                    $args = array(
                        'post_type'   => 'post',
                        'post_status' => 'draft',
                        'numberposts' => 1,
                        'orderby'     => 'date',
                        'order'       => 'ASC',
                    );
                
                    $scheduled_posts = get_posts($args);
    
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'slf_generated_title';
                    $cache_key = 'latest_generated_title';
                    $results = wp_cache_get($cache_key);
                    if (false === $results) {
                       $query = "SELECT * FROM $table_name where promt_title = promt_title ORDER BY id DESC limit 1";
                       $results = $wpdb->get_results($query, OBJECT);            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                       wp_cache_set($cache_key, $results, '', 3600);
                    } 	 
                    
                    $title = $results->generate_title;
                
                    if (empty($scheduled_posts) && !empty($titles)) {
                        $title = array_shift($titles); // Get the first title
                
                        // Generate content using the title
                        $allowed_html_content_post = wp_kses_allowed_html('post');
                        $content = $this->otslf_schedule_article_publish($title);
                        
                        // Get the user's timezone
                        $user_timezone = get_user_meta(get_current_user_id(), 'timezone_string', true);
                        if (empty($user_timezone)) {
                            $user_timezone = 'UTC';
                        }
                
                        // Convert the current time to UTC and add 6 hours
                        $current_time = new DateTime('now', new DateTimeZone($user_timezone));
                        $scheduled_time = $current_time->setTimezone(new DateTimeZone('UTC'))->modify('+6 hours');
                        $formatted_time = $scheduled_time->format('Y-m-d H:i:s');
    
                        // Prepare post data
                        $post_data = array(
                            'post_title'   => $title,
                            'post_content' => wp_kses($content, $allowed_html_content_post),
                            'post_status'  => 'draft', // Set post status to 'future' to schedule the post
                            'post_author'  => get_current_user_id(),
                            'post_date'    => $formatted_time, // Schedule for 6 hours later in UTC
                        );
                
                        // Insert the post into the database
                        $post_id = wp_insert_post($post_data);
                
                        if ($post_id) {
                            // Set featured image
                            $this->otslf_set_featured_image($post_id, $seokeyword, $title);
                
                            // Insert log entry
                            $table_name = $wpdb->prefix . 'slf_schedule_post_title_log'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $data = array(
                                'title'    => $title,
                                'status'   => 'Publish',
                                'log_time' => current_time('mysql'),
                            );
                            $format = array('%s', '%s', '%s');
                            $result = $wpdb->insert($table_name, $data, $format);  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            if (false === $result) {
                                //error_log('Error inserting log data for title: ' . $title);
                            }
                        } else {
                               //error_log('Failed to insert post for title: ' . $title);
                        }
                
                        // Send email notification if enabled
                        if ($this->email_notification === '1') { 
                            $this->otslf_post_email_notification();
                        }
                    }
                }
    
                        
                        /*===============================  
                            Daily Publish scheudle post 
                        =================================*/
    
    
                public function otslf_publish_daily_scheduled_posts() {
                
                    $args = array(
                        'post_type' => 'post',
                        'post_status' => 'draft',
                        'numberposts' => 1,
                        'orderby' => 'date',
                        'order' => 'ASC',
                    );
                
                    $scheduled_posts = get_posts($args);
                    
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'slf_generated_title';
                    $cache_key = 'latest_generated_title';
                    $results = wp_cache_get($cache_key);
    
                    if (false === $results) {                                     
                        $query = "SELECT * FROM $table_name where promt_title = promt_title ORDER BY id DESC limit 1";
                        $results = $wpdb->get_results($query, OBJECT);     // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        wp_cache_set($cache_key, $results, '', 3600);
                    } 	
                    
                    $title = $results->generate_title;
                
    
                    if (empty($scheduled_posts) && !empty($titles)) {
                        $title = array_shift($titles); 
                        
                        $allowed_html_content_post = wp_kses_allowed_html('post');
                        $content =  $this->otslf_schedule_article_publish($title);
                        $post_data = array(
                            'post_title'   => $title,
                            'post_content' => wp_kses($content, $allowed_html_content_post),
                            'post_status'  => 'draft',
                            'post_author'  => get_current_user_id(),    //Change to your author ID
                            'post_date'    => gmdate('Y-m-d g:i a', strtotime('+1 day')),
                        );
                        $post_id = wp_insert_post($post_data);
    
                        if ($post_id) {
                            // Set featured image
                            $this->otslf_set_featured_image($post_id, $seokeyword, $title);
            
                            // Insert log entry
                            $table_name = $wpdb->prefix . 'slf_schedule_post_title_log';    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $data = array(
                                'title' => $title,
                                'status' => 'Publish',
                                'log_time' => current_time('mysql'),
                            );                                              
                            $format = array('%s', '%s', '%s');
                            $result = $wpdb->insert($table_name, $data, $format); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            if (false === $result) {
                                //error_log('Error inserting log data for title: ' . $title);
                            }
                        } else {
                            ///error_log('Failed to insert post for title: ' . $title);
                        }
                        if ($this->email_notification === '1') { 
                            $this->otslf_post_email_notification();
                        }
                    }
                }     

            
              /* ================================ 
                       Delete the title 
               ============================== */         
        
            public function otslf_delete_blog_title() {

                check_ajax_referer('ai-seo-content-nonce', 'nonce', true); 

                global $wpdb;
                    $tid = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
                    if ($tid) {

                        $generate_title = $wpdb->prefix . 'slf_generated_title';
                        $cache_key = 'generated_title_' . $tid;
                        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM $generate_title WHERE id = %d", $tid) );     // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        wp_cache_delete($cache_key);
                        
                        if ($deleted !== false) {
                            // Success response
                            wp_send_json_success();
                        } else {
                            // Error response
                            wp_send_json_error('Failed to delete title from database.');
                        }
                    } else {
                        // Error response if title is not provided
                        wp_send_json_error('No title provided.');
                    }
            }

                 
           /* =====================  
                 Update Script 
            ======================*/

            public function otslf_update_blog_titles() {
                check_ajax_referer('ai-seo-content-nonce', 'nonce', true); 
            
                global $wpdb;
                if (!isset($_POST['tid']) || !isset($_POST['new_title'])) {
                    wp_send_json_error('Invalid parameters.');
                    return;
                }
            
                $tid = sanitize_text_field(wp_unslash($_POST['tid']));
                $new_title = sanitize_text_field(wp_unslash($_POST['new_title']));
                
                $cache_key = 'generated_title_' . $tid;
 
                $query = $wpdb->prepare("UPDATE {$wpdb->prefix}slf_generated_title SET generate_title = %s WHERE id = %d", $new_title, $tid );
                wp_cache_delete($cache_key);
        
                $result = $wpdb->query($query);                 // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            
                if ($result === false) {
                    wp_send_json_error('Database update failed: ' . $wpdb->last_error);
                } else if ($result === 0) {
                    wp_send_json_error('No rows were updated. Ensure the original title exists.');
                } else {
                    wp_send_json_success('Rows affected: ' . $result);
                }
            }
        

              
}     