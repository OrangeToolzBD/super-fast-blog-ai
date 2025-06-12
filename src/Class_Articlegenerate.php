<?php
/**
 *
 *  orange post article generate
 *
 **/
namespace Orange\AiBlog\Src;

use Orange\AiBlog\Src\Class_Aitools;

use OpenAI\OpenAIClient;
use OpenAI\OpenAIConfig;

class Class_Articlegenerate {

  private $apikey;
  private $aimodel;
  private $tlanguage;
  private $imageApi;
  private $wordcount;
  
  public function __construct() {
      $this->apikey = trim(get_option('otslf_api_key'));
      $this->aimodel = get_option('otslf_model');
      $this->wordcount = get_option('otslf_word_count');
      add_action('wp_ajax_otslf_ai_article_generate', [$this, 'otslf_ai_article_generate']);  
      add_action('wp_ajax_otslf_generate_seo_keyword', [$this, 'otslf_generate_seo_keyword']);  
      add_action('wp_ajax_otslf_generate_seo_metadescription', [$this, 'otslf_generate_seo_metadescription']);  
      add_action('admin_notices', [$this,'otslf_show_seo_plugin_notice']);
      add_action('wp_ajax_otslf_instant_title_generate', [$this,'otslf_instant_title_generate']);
  }


              /*====================================
                         Blog generator 
              =================================== */  

              public function otslf_bloggenerate(){
                ?>
                <div class="blog-generate-page">

                <div class="row-1">
                      <div class="col-3">
                          <h1><?php esc_html_e('Blog Generate', 'super-fast-blog-ai');?></h1>
                      </div>
                      <div class="col-4 publish_link">
                        <?php
                            global $wpdb;
                            $schedule_post = $wpdb->prefix . 'slf_schedule_post_title_log';
                            $last_row = wp_cache_get('last_schedule_post_row');
                            
                            if ($last_row === false) {
                                $last_row = $wpdb->get_row(
                                  $wpdb->prepare( "SELECT * FROM `{$schedule_post}` WHERE indicat = %s ORDER BY log_time DESC LIMIT 1", 'yes' )
                              );
                              
                                wp_cache_set('last_schedule_post_row', $last_row, '', 600);
                            }

                            if (!empty($last_row->postid)) {
                                $updated = $wpdb->update(
                                    $schedule_post,
                                    ['indicat' => 'no'],
                                    ['postid' => $last_row->postid]
                                );
                                
                                wp_cache_delete('last_schedule_post_row');
                            }
                            if ($last_row) {
                              $edit_url = admin_url('post.php?post=' . $last_row->postid . '&action=edit');
                              echo '<a href="' . esc_url($edit_url) . '" data-postid="' . esc_attr($last_row->postid) . '" target="_blank">'. esc_html__('Please click the link for edit or preview the article.','super-fast-blog-ai'). '</a>';
                            }    
                         ?>
                      </div>
                  </div>

                <div class="blog-generate-wrapper">
                  <div class="blog-generate-title-card">
                    <!-- tab start  -->
                        <div class="art-tab-container">
                          <ul class="art-tabs">
                              <li class="art-tab-link active" data-tab="art-tab-1" ><?php esc_html_e('Generate With AI', 'super-fast-blog-ai'); ?> </li>
                              <li class="art-tab-link" data-tab="art-tab-2"><?php esc_html_e('Write your own Title', 'super-fast-blog-ai'); ?></li>
                          </ul>
                                        <!-- Generate With AI -->
                          <div id="art-tab-1" class="art-tab-content active">
                            <div class="blog-generate-tabs"> <span class="redmark"><?php esc_html_e('* is required field', 'super-fast-blog-ai'); ?>
                            </span>
                                <p><?php esc_html_e('Write your own title and get more title suggestion', 'super-fast-blog-ai'); ?> <span class="redmark"><?php echo esc_html('*'); ?></span></p>
                                <!-- <input type="text" name="prompt" id="prompt" value=""> -->
                                <textarea id="prompt" name="prompt" rows="4" cols="100" style="width: 100%; resize: none;" placeholder="What's on your mind ? Start typing here...."></textarea>
                                <small class="error-message" id="prompterror"></small>                      
                            </div> 
                          
                            <div class="blog-variation-select" id="vcontent">
                                <div class="language input-selected">
                                  <div class="option-field">
                                    <?php $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_language(); ?> 
                                  </div>
                                </div>
                                <div class="language input-selected">
                                <div class="option-field">
                                    <?php $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_writtenstyle(); ?>
                                </div>
                                </div>
                                <div class="language input-selected">
                                <div class="option-field">
                                    <?php $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_writone(); ?> 
                                </div>
                                </div>
                              </div>
                              <div class="generate-title-wrap">
                                <button class="btn disabled" disabled id="instanttitle" name="instanttitle">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">
                                      <path d="M11.403 6.027a5.32 5.32 0 0 1-4.114-4.114.548.548 0 0 0-1.073 0 5.32 5.32 0 0 1-4.114 4.114.548.548 0 0 0 0 1.072 5.32 5.32 0 0 1 4.114 4.115.548.548 0 0 0 1.072 0 5.32 5.32 0 0 1 4.115-4.115.548.548 0 0 0 0-1.072m-.906 9.049a2.51 2.51 0 0 1-1.938-1.938.548.548 0 0 0-1.072 0 2.51 2.51 0 0 1-1.939 1.938.548.548 0 0 0 0 1.072 2.51 2.51 0 0 1 1.939 1.939.548.548 0 0 0 1.072 0 2.51 2.51 0 0 1 1.938-1.939.548.548 0 0 0 0-1.072m7.401-5.072a3.39 3.39 0 0 1-2.62-2.62.548.548 0 0 0-1.072 0 3.39 3.39 0 0 1-2.62 2.62.548.548 0 0 0 0 1.073 3.39 3.39 0 0 1 2.62 2.62.548.548 0 0 0 1.072 0 3.39 3.39 0 0 1 2.62-2.62.548.548 0 0 0 0-1.073" fill="#fff"/>
                                    </svg>
                                    <?php esc_html_e('Suggest Title', 'super-fast-blog-ai'); ?>
                                </button>
                              </div>
                          </div>

                                <!-- Write your own Title -->
                          <div id="art-tab-2" class="art-tab-content">
                              <div class="blog-generate-tabs"> 
                                <p><?php esc_html_e('Write your own title  ', 'super-fast-blog-ai'); ?></p>
                                <input type="text" name="woprompt" id="woprompt">
                                <small class="error-message" id="woprompterror"></small>
                              </div>
                          </div>
                        </div>

                    </div> <!-- end the first section -->
                          
                  <!-- Start according menu --> 
                  <div class="blog-generate-title-card" id="generatetitle" style="display:none;">                   
                  <div class="topwrapper">
                    <div class="ai_accordion">
                        <div class="ai_accordion-item">
                            <div class="ai_accordion-header">
                              <label id="ptitle"></label>
                            </div>
                            <div class="ai_accordion-content show">
                              <div class="ai_inner-content">
                                <ul class="ai_accordion-list">
                                  <div id="gtitle" style="display:none;"> 

                                  </div>
                                </ul>
                                </div>
                              </div>
                          </div>
                      </div>
                    </div>
                  </div>
                  <!-- END according menu -->            


                  <!-- Start blog core setting  -->
                  <div class="blog-generate-title-card core-setting">
                    <h6 class="core-setting-title"><?php esc_html_e('Core Settings', 'super-fast-blog-ai'); ?></h6>
                    <div class="core-setting-inner">
                      <div class="blog-variation-select" id="vcontent">
                        <div class="language input-selected">
                        <div class="option-field">
                          <?php $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_language(); ?> 
                        </div>
                        </div>
                        <div class="language input-selected">
                        <div class="option-field">
                          <?php $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_writtenstyle(); ?>
                        </div>
                        </div>
                        <div class="language input-selected">
                        <div class="option-field">
                        <?php $this->tlanguage = new Class_Aitools; $this->tlanguage->otslf_writone(); ?> 
                        </div>
                        </div>
                        <div class="language input-selected">
                          
                            <p><?php esc_html_e('Word Count', 'super-fast-blog-ai'); ?>
                              <span class="redmark"> <?php echo esc_html('*'); ?>&nbsp;&nbsp; </span>
                              <!-- <span class="subheading">
                                  <?php 
                                
                                  /* printf(       
                                    esc_html__('Minimum Words for an article: %1$s, %2$s', 'super-fast-blog-ai'), 
                                    '1000',
                                    '2000' 
                                  );*/
                                ?>
                              </span> -->
                            </p>
                              <div class="option-field">
                              <input type="number" id="countWord" name="countWord" placeholder="Enter at least 100 words"/>
                            </div>
                                <small class="error-message" id="maxerror"></small>
                              </div>
                          </div>

                          <div class="input-container">
                            <h6 class=""><?php esc_html_e('Sub Heading', 'super-fast-blog-ai'); ?></h6>
                            <label for="checkbox" class="checkbox-container">
                              <input type="checkbox" id="subheading" value="1" name="subheading"/>
                              <span class="checkmark"></span>
                            </label>
                            
                          </div>
                          <span id="subheadingerror" class="error-message"></span>
                          <div class="sub-heading-inner">
                            <div class="blog-variation-select" id="vcontent">
                              <div class="language input-selected">
                                <label for="select"><p><?php esc_html_e('Heading Tag', 'super-fast-blog-ai'); ?><span class="redmark">*</span> </p></label> 
                                <div class="custom-select">
                                <div class="option-field">
                                      <select id="htaging" name="htaging" required>
                                          <option selected>Select Header</option>
                                          <option value="h2">H2</option>
                                          <option value="h3">H3</option>
                                          <option value="h4">H4</option>
                                          <option value="h5">H5</option>
                                          <option value="h6">H6</option>
                                        </select>
                                </div>
                            </div>
                                <small class="error-message" id="hgerror"></small>
                              </div>
                              <div class="language input-selected" style="margin-top:9px;">
                                <label for="select"> 
                                  <p><?php esc_html_e('Number of Heading', 'super-fast-blog-ai'); ?> <span class="redmark"><?php echo esc_html('*'); ?></span></p>
                                </label>
                                <div class="custom-select">
                                <div class="option-field">
                                <select id="numberh" name="numberh" required>
                                          <option  selected>Select Number</option>
                                          <option value="1">1</option>
                                          <option value="2">2</option>
                                          <option value="3">3</option>
                                          <option value="4">4</option>
                                          <option value="5">5</option>
                                          <option value="6">6</option>
                                          <option value="7">7</option>
                                          <option value="8">8</option>
                                          <option value="9">9</option>
                                          <option value="10">10</option>
                                          <option value="11">11</option>
                                          <option value="12">12</option>
                                  </select>
                                </div>
                                </div>
                                <small class="error-message" id="hnumbererror"></small>
                              </div>
                            </div>
                          </div>

                          <div class="input-container">
                            <h6 class=""><?php esc_html_e('Article FAQ', 'super-fast-blog-ai'); ?></h6>
                            <label for="checkbox" class="checkbox-container">
                              <input type="checkbox" id="faqlist" value="1" name="faqlist"/>
                              <span class="checkmark"></span>
                            </label>
                          </div>
                        </div>
                        <!-- Image Generation  -->
                        <div class="image_accordion_wrapper">
                          <button class="accordion image-generation-btn">
                            <h5> <?php esc_html_e('Image Generate', 'super-fast-blog-ai'); ?> <span class="redmark">*</span> </h5>
                            <span class="slf-arrow">
                              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.06 8.204a.75.75 0 0 1 1.06-.063l4.297 3.82 4.297-3.82a.75.75 0 1 1 .997 1.121l-4.796 4.263a.75.75 0 0 1-.997 0L5.123 9.262a.75.75 0 0 1-.062-1.058" fill="#757F8E"/></svg>
                            </span>
                          </button>
                          <div class="image-generation-wrap panel">
                            <div class="image-generation-inner">
                              <div class="blog-variation-select">
                                <div id="vcontent">
                                <label for="select">
                                  <p>
                                      <?php esc_html_e('Featured Image', 'super-fast-blog-ai'); ?>
                                      <!-- <span class="redmark"><?php //echo esc_html('*'); ?></span>-->
                                  </p>
                                </label>
                                  <div class="custom-select">
                                    <select id="otslf_featured_image" name="slf_featured_image" required>
                                      <option selected>-- Select image type --</option>
                                      <option value="dalle3">DALL-E 3</option>
                                      <option value="pixabay">Pixabay</option>
                                    </select><br> 
                                    <small class="error-message" id="featImg"></small>
                                  </div>
                                </div>

                                <div id="otslf_image_generate_api_key">
                                  <label for="select">
                                    <p>
                                        <?php esc_html_e('Image API key', 'super-fast-blog-ai'); ?>
                                        <span class="redmark"><?php echo esc_html('*'); ?></span>
                                        &nbsp;&nbsp;
                                        <a href="<?php echo esc_url('https://pixabay.com/api/docs/'); ?>" 
                                          target="_blank"
                                          rel="noopener noreferrer">
                                            <?php esc_html_e('Get Api key', 'super-fast-blog-ai'); ?>
                                        </a>
                                    </p>
                                  </label>
                                    
                                  <div id="otslf_image_generate_api_key">
                                    <input type="password" name="featured_image_api" id="featured_image_api" placeholder="">                                     
                                  </div>
                                   <small class="error-message" id="imgapierror"></small>
                                </div>               
                              <div id="otslf_dall3_generate_api_key" class="dalle_image">                      
                                    Openai API will be used, don't use other Api.                
                              </div>  
                            </div>
                          </div>
                        </div>

                          <button class="accordion image-generation-btn">
                            <?php esc_html_e('SEO','super-fast-blog-ai')?> 
                            <span class="slf-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.06 8.204a.75.75 0 0 1 1.06-.063l4.297 3.82 4.297-3.82a.75.75 0 1 1 .997 1.121l-4.796 4.263a.75.75 0 0 1-.997 0L5.123 9.262a.75.75 0 0 1-.062-1.058" fill="#757F8E"/></svg>
                            </span>
                          </button>

                          <div class="image-generation-wrap panel">
                            <div class="image-generation-inner">
                              <div class="blog-post">
                                <div class="textarea-content">
                                <label for="textarea"><?php esc_html_e('Five keywords will be generated by the selected title (you can edit & modify those keywords)','super-fast-blog-ai')?></label> 
                                </div>
                                <textarea name="seo_keyword" id="seo_keyword" placeholder=""></textarea><br>
                                <small class="error-message" id="keyword_limit"></small>
                                <div class="textarea-content meta-description-field">
                                  <label for="textarea"><?php esc_html_e('Meta Description Generated Limit 160 Characters','super-fast-blog-ai')?> </label>  
                                </div>
                                <textarea name="meta_description" id="meta_description" placeholder="Meta Description"></textarea>
                                <small class="error-message" id="limit_message"></small>
                                <div class="btn-wrap">
                                  <button class="seokeyword btn">
                                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><path d="M11.403 6.027a5.32 5.32 0 0 1-4.114-4.114.548.548 0 0 0-1.073 0 5.32 5.32 0 0 1-4.114 4.114.548.548 0 0 0 0 1.072 5.32 5.32 0 0 1 4.114 4.115.548.548 0 0 0 1.072 0 5.32 5.32 0 0 1 4.115-4.115.548.548 0 0 0 0-1.072m-.906 9.049a2.51 2.51 0 0 1-1.938-1.938.548.548 0 0 0-1.072 0 2.51 2.51 0 0 1-1.939 1.938.548.548 0 0 0 0 1.072 2.51 2.51 0 0 1 1.939 1.939.548.548 0 0 0 1.072 0 2.51 2.51 0 0 1 1.938-1.939.548.548 0 0 0 0-1.072m7.401-5.072a3.39 3.39 0 0 1-2.62-2.62.548.548 0 0 0-1.072 0 3.39 3.39 0 0 1-2.62 2.62.548.548 0 0 0 0 1.073 3.39 3.39 0 0 1 2.62 2.62.548.548 0 0 0 1.072 0 3.39 3.39 0 0 1 2.62-2.62.548.548 0 0 0 0-1.073" fill="#7328F2"/></svg>
                                    <?php esc_html_e('keyword Generate','super-fast-blog-ai')?> 
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>

                          <!-- Publish to post   -->
                          <button class="accordion image-generation-btn">
                            <h5> <?php esc_html_e('Publish to Post','super-fast-blog-ai')?> <span class="redmark">*</span> </h5>                                    
                            <span class="slf-arrow">
                              <!-- &#9660; -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.06 8.204a.75.75 0 0 1 1.06-.063l4.297 3.82 4.297-3.82a.75.75 0 1 1 .997 1.121l-4.796 4.263a.75.75 0 0 1-.997 0L5.123 9.262a.75.75 0 0 1-.062-1.058" fill="#757F8E"/></svg>
                            </span>
                          </button>

                          <div class="image-generation-wrap panel schedule-wrap">
                            <div class="image-generation-inner">
                              <div class="category-inner col2">
                                <div class="input-container">
                                  <h6><?php esc_html_e('Category','super-fast-blog-ai')?> </h6> 
                                </div>
                                 <span><?php esc_html_e('Select 1 or 2 categories following list','super-fast-blog-ai')?></span>
                                 <p></p>
                                <div class="category-checkbox-inner">                                  
                                  <?php 
                                        $terms = get_terms(array(
                                          'taxonomy' => 'category', 
                                          'hide_empty' => false, 
                                        ));
                                        
                                        if (!empty($terms) && !is_wp_error($terms)) {
                                            echo '<ul class="custom-taxonomy-list">';
                                            foreach ($terms as $term) {
                                                echo '<li>';
                                                echo '<label>';
                                                echo '<input type="checkbox" id="category" name="ot_taxonomy" value="' . esc_attr($term->term_id) . '"> ';
                                                echo esc_html($term->name);
                                                echo '</label>';
                                                echo '</li>';
                                            }
                                            echo '</ul>';
                                        }
                                  ?>
                                </div>
                                <small class="error-message" id="caterror"></small>
                              </div>

                                  <!-- end -->    
                            </div>
                        </div>
                        <!-- Start ending btn  -->
                        <div class="generate-title-wrap">
                          <div class="generate-title-prmo">
                          </div>
                          <div class="btn-wrapper">
                            <button disabled class="btn generateBtn disabled" name="generateBtn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><path d="M11.403 6.027a5.32 5.32 0 0 1-4.114-4.114.548.548 0 0 0-1.073 0 5.32 5.32 0 0 1-4.114 4.114.548.548 0 0 0 0 1.072 5.32 5.32 0 0 1 4.114 4.115.548.548 0 0 0 1.072 0 5.32 5.32 0 0 1 4.115-4.115.548.548 0 0 0 0-1.072m-.906 9.049a2.51 2.51 0 0 1-1.938-1.938.548.548 0 0 0-1.072 0 2.51 2.51 0 0 1-1.939 1.938.548.548 0 0 0 0 1.072 2.51 2.51 0 0 1 1.939 1.939.548.548 0 0 0 1.072 0 2.51 2.51 0 0 1 1.938-1.939.548.548 0 0 0 0-1.072m7.401-5.072a3.39 3.39 0 0 1-2.62-2.62.548.548 0 0 0-1.072 0 3.39 3.39 0 0 1-2.62 2.62.548.548 0 0 0 0 1.073 3.39 3.39 0 0 1 2.62 2.62.548.548 0 0 0 1.072 0 3.39 3.39 0 0 1 2.62-2.62.548.548 0 0 0 0-1.073" fill="#fff"/></svg>
                            <?php esc_html_e('Generate Blog','super-fast-blog-ai')?> 
                            </button>
                          </div>
                        </div>
                      </div>
                  <!-- Image Generation  -->
                </div>
              <?php 
              }

              /*====================================
                    Instant title generation
              =================================== */       


              public function otslf_instant_title_generate() {

                  // Nonce verification
                  if ( !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ai-seo-content-nonce')
                  ) {
                      wp_send_json_error([
                          'message' => esc_html_e('Nonce verification failed.', 'super-fast-blog-ai'),
                      ]);
                      wp_die();
                  }

                  // Check if the title is passed via AJAX and sanitize it
                  if (!isset($_POST['title']) || empty($_POST['title'])) {
                      wp_send_json_error([
                          'message' => esc_html_e('Title is required.', 'super-fast-blog-ai'),
                      ]);
                      wp_die();
                  }

                  // Sanitize and assign variables
                  $title = sanitize_text_field(wp_unslash($_POST['title']));
                  
                  // If tone, style, language fields are used, ensure they are checked before use
                  $writone = isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : 'neutral';
                  $twstyle = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : 'informative';
                  $tlanguage = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';
              
                  // Constructing the prompt
                  $prompt = sprintf(
                      'Generate five blog post titles based on the following input: %s. Use the following tone: %s. Use the following style: %s. Use the following language: %s.',
                      $title, $writone, $twstyle, $tlanguage
                  );
              
                  // Setup the request
                  $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                      'body' => wp_json_encode(array(
                          'model' => sanitize_text_field($this->aimodel),
                          'messages' => array(
                              array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                              array('role' => 'user', 'content' => $prompt)
                          ),
                          'max_tokens' => 1000
                      )),
                      'headers' => array(
                          'Content-Type' => 'application/json',
                          'Authorization' => 'Bearer ' . sanitize_text_field($this->apikey),
                      ),
                      'timeout' => 20,
                  ));
              
                  // Check for errors
                  if (is_wp_error($response)) {
                      $error_message = $response->get_error_message();
                      wp_send_json_error('Error connecting to OpenAI API: ' . $error_message);
                      wp_die();
                  } else {
                      $body = wp_remote_retrieve_body($response);
                      $data = json_decode($body, true);
              
                      if (isset($data['choices'][0]['message']['content'])) {
                          // Process titles
                          $titles = explode("\n", $data['choices'][0]['message']['content']);
                          $titles = array_map('trim', $titles);
                          $titles = array_filter($titles, function($title) {
                              return !empty($title);
                          });
              
                          // Clean and escape titles
                          $clean_titles = array_map(function($title) {
                              $title = preg_replace('/^\d+\.\s*/', '', $title);
                              return trim($title, '"');
                          }, $titles);
              
                          $escaped_titles = array_map('esc_html', $clean_titles);
                          wp_send_json_success(array_values($escaped_titles));
                      } else {
                          // Handle error from API response
                          $messageError = isset($data['error']['message']) && !empty($data['error']['message']) 
                                          ? $data['error']['message'] 
                                          : 'Something went wrong! Please try again.';
                          wp_send_json_error($messageError);
                      }
                  }
                wp_die();
              }
        

              /*====================================
                    Article generrated
              =================================== */  

             
            
              public function otslf_ai_article_generate() {

                // Verify nonce and required fields
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai-seo-content-nonce' ) ) {
                    wp_send_json_error( [ 'message' => esc_html__( 'Nonce verification failed.', 'super-fast-blog-ai' ) ] );
                }
                if ( empty( $_POST['title'] ) ) {
                    wp_send_json_error( [ 'message' => esc_html__( 'Title is required.', 'super-fast-blog-ai' ) ] );
                }
            
                // Sanitize and assign variables
                $title       = sanitize_text_field( wp_unslash( $_POST['title'] ) );
                $seokeyword  = isset( $_POST['seo_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_keyword'] ) ) : '';
                $metadescrip = isset( $_POST['metades'] ) ? sanitize_text_field( wp_unslash( $_POST['metades'] ) ) : '';
                $tlanguage   = isset( $_POST['tlanguage'] ) ? sanitize_text_field( wp_unslash( $_POST['tlanguage'] ) ) : '';
                $twstyle     = isset( $_POST['twstyle'] ) ? sanitize_text_field( wp_unslash( $_POST['twstyle'] ) ) : '';
                $writone     = isset( $_POST['writone'] ) ? sanitize_text_field( wp_unslash( $_POST['writone'] ) ) : '';
                $countWord   = isset( $_POST['countWord'] ) ? sanitize_text_field( wp_unslash( $_POST['countWord'] ) ) : '';
                $htaging     = isset( $_POST['htaging'] ) ? sanitize_text_field( wp_unslash( $_POST['htaging'] ) ) : '';
                $numberh     = isset( $_POST['numberh'] ) ? sanitize_text_field( wp_unslash( $_POST['numberh'] ) ) : '';
                $faqlist     = isset( $_POST['faqlist'] ) ? sanitize_text_field( wp_unslash( $_POST['faqlist'] ) ) : '';
                $subheading  = isset( $_POST['subheading'] ) ? sanitize_text_field( wp_unslash( $_POST['subheading'] ) ) : '0';
                $this->imageApi = isset( $_POST['imageAPi'] ) ? sanitize_text_field( wp_unslash( $_POST['imageAPi'] ) ) : '';
            
                // If subheading is enabled, ensure htaging and numberh are provided.
                if ( '1' === $subheading && ( empty( $htaging ) || empty( $numberh ) ) ) {
                    wp_send_json_error( [ 'message' => esc_html__( 'Please provide both the header tag and the number of subheadings if subheading is enabled.', 'super-fast-blog-ai' ) ] );
                }
            
                global $wpdb;
                try {
                    $config = new OpenAIConfig( $this->apikey );
                    $client = new OpenAIClient( $config );
                  
                    $user_word_count = intval($this->wordcount); 
                    $estimated_tokens = intval($user_word_count * 1.5); 

                    $max_token_limit = 8090;
                    $estimated_tokens = min($estimated_tokens, $max_token_limit);
            
                    // Build the prompt in one frame
                    $prompt = [
                        [
                            'role'    => 'system',
                            'content' => esc_html__( 'You are a helpful assistant that generates SEO-friendly content.', 'super-fast-blog-ai' )
                        ],
                        [
                            'role'    => 'user',
                            'content' => sprintf(
                                esc_html__( 'Generate a detailed article on "%1$s" with at least %2$d words.', 'super-fast-blog-ai' ),
                                esc_html( $title ),
                                $user_word_count
                            )
                        ]
                    ];
            
                    // Consolidate tone, style, and language instructions if provided
                    $settings = [];
                    if ( ! empty( $writone ) ) { $settings[] = "tone: " . esc_html( $writone ); }
                    if ( ! empty( $twstyle ) ) { $settings[] = "style: " . esc_html( $twstyle ); }
                    if ( ! empty( $tlanguage ) ) { $settings[] = "language: " . esc_html( $tlanguage ); }
                    if ( ! empty( $settings ) ) {
                        $prompt[] = [
                            'role'    => 'user',
                            'content' => 'Use the following settings: ' . implode( ', ', $settings ) . '.'
                        ];
                    }
            
                    if ( ! empty( $seokeyword ) ) {
                        $prompt[] = [
                            'role'    => 'user',
                            'content' => 'Include the following SEO keywords: ' . esc_html( $seokeyword )
                        ];
                    }
            
                    if ( '1' === $faqlist ) {
                        $prompt[] = [
                            'role'    => 'user',
                            'content' => esc_html__( 'Add a FAQ section at the end of the article.', 'super-fast-blog-ai' )
                        ];
                    }
            
                    if ( '1' === $subheading && ! empty( $htaging ) && ! empty( $numberh ) ) {
                        $prompt[] = [
                            'role'    => 'user',
                            'content' => sprintf(
                                esc_html__( 'Include %1$d subheadings using %2$s tags, ensuring each contains relevant keywords.', 'super-fast-blog-ai' ),
                                intval( $numberh ),
                                esc_html( $htaging )
                            )
                        ];
                    }
            
                    // Final formatting instructions
                    $prompt[] = [
                        'role'    => 'user',
                        'content' => esc_html__( 'Use only <h1>, <h2>, <h3>, <p>, <ul>, <li>, <b>, and <i> HTML tags for formatting.', 'super-fast-blog-ai' )
                    ];
            
                    // Call the OpenAI client with the optimized prompt
                    $response = $client->chat( $this->aimodel, $prompt, $estimated_tokens );
                    $content  = $response['choices'][0]['message']['content'] ?? '';
            
                    if ( ! empty( $content ) ) {
                        $post_data = [
                            'post_title'   => $title,
                            'post_content' => wp_kses( $content, wp_kses_allowed_html( 'post' ) ),
                            'post_status'  => 'draft',
                            'post_author'  => get_current_user_id(),
                            'post_date'    => current_time( 'mysql' ),
                        ];
            
                        $post_id = wp_insert_post( $post_data );
                        $stringcount = mb_strlen( $content, 'UTF-8');
            
                        if ( $post_id ) {
                            $this->otslf_update_seo_meta_generate( $post_id, $seokeyword, $metadescrip );
                            wp_set_post_terms( $post_id, get_option( 'otslf_ot_taxonomy', [] ), 'category' );
                            $this->otslf_set_featured_image( $post_id, $title );
            
                            $table_name = $wpdb->prefix . 'slf_schedule_post_title_log';
                            $data = [
                                'title'     => $title,
                                'status'    => 'sameday',
                                'postid'    => $post_id,
                                'charaters' => $stringcount,
                                'modelused' => $this->aimodel,
                                'indicat'   => 'yes',
                                'log_time'  => current_time( 'mysql' ),
                            ];
                            $wpdb->insert( $table_name, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery  
            
                            wp_send_json_success( [ 'message' => esc_html__( 'Post published successfully.', 'super-fast-blog-ai' ), 'title' => $title ] );
                        } else {
                            wp_send_json_error( [ 'message' => esc_html__( 'Failed to insert post.', 'super-fast-blog-ai' ) ] );
                        }
                    } else {
                        wp_send_json_error( [ 'message' => esc_html__( 'Generated content was empty.', 'super-fast-blog-ai' ) ] );
                    }
                } catch ( Exception $e ) {
                    wp_send_json_error( [
                        'message' => esc_html__( 'Error: Failed to generate content. Please try again later.', 'super-fast-blog-ai' ),
                        'error'   => $e->getMessage()
                    ] );
                }
            }
            

              /*====================================
                        Plugin Activated check 
                =================================== */   
          
              public function otslf_update_seo_meta_generate($post_id, $seokeyword, $metadescrip ) {
                
                $yoast_seo = 'wordpress-seo/wp-seo.php';
                $rank_math_seo = 'seo-by-rank-math/rank-math.php';
          
                if ( is_plugin_active( $yoast_seo ) ) {
                    
                    update_post_meta( $post_id, '_yoast_wpseo_focuskw', $seokeyword );
                    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $metadescrip );

                } elseif ( is_plugin_active( $rank_math_seo ) ) {
                    
                    update_post_meta( $post_id, 'rank_math_focus_keyword', $seokeyword );
                    update_post_meta( $post_id, 'rank_math_description', $metadescrip );
                }
              }
              
              public function otslf_show_seo_plugin_notice() {

                $yoast_seo = 'wordpress-seo/wp-seo.php';
                $rank_math_seo = 'seo-by-rank-math/rank-math.php';

                $yoast_active = is_plugin_active( $yoast_seo );
                $rank_math_active = is_plugin_active( $rank_math_seo );

                if ( ! $yoast_active && ! $rank_math_active ) {
                ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'Your custom plugin requires both Yoast SEO and Rank Math SEO plugins to be installed and activated. Please install and activate these plugins.', 'super-fast-blog-ai'); ?></p>
                </div>
                <?php    
                }
              }


              /*================================
                      Featured image
              ================================ */  

            public function otslf_set_featured_image($post_id, $title) {

              $image_url = $this->otslf_get_pixabay_image($title);

              if ($image_url) {
                  $upload_dir = wp_upload_dir();
                  $response = wp_remote_get($image_url);

                  if (is_wp_error($response)) {
                      return false;
                  }

                  $image_data = wp_remote_retrieve_body($response);
                  
                  if (empty($image_data)) {

                      return false;
                  }

                  global $wp_filesystem;
                  if (empty($wp_filesystem)) {
                      require_once ABSPATH . 'wp-admin/includes/file.php';
                      WP_Filesystem();
                  }

                  $filename = wp_unique_filename($upload_dir['path'], basename($image_url));
                  $filepath = $upload_dir['path'] . '/' . $filename;

                  if (!$wp_filesystem->put_contents($filepath, $image_data, FS_CHMOD_FILE)) {
                  
                      return false;
                  }
                  
                  $wp_filetype = wp_check_filetype($filename, null);
                  $attachment = array(
                      'post_mime_type' => $wp_filetype['type'],
                      'post_title'     => sanitize_file_name($filename),
                      'post_content'   => '',
                      'post_status'    => 'inherit'
                  );

                  $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
                  require_once ABSPATH . 'wp-admin/includes/image.php';
                  $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
                  
                  if (is_wp_error($attach_data)) {
                      return false;
                  }

                  wp_update_attachment_metadata($attach_id, $attach_data);
                  set_post_thumbnail($post_id, $attach_id);
                  return true;
              }
              return false;
            }


              /*================================
                    Pixaby API
              ================================ */   

            public function otslf_get_pixabay_image($title) {

                $api_key = $this->imageApi;  //pixaby api key

                $search_terms = [];
                $search_terms[] = $title;

                $words = explode(' ', $title);
                for ($i = count($words); $i > 0; $i--) {
                    $search_terms[] = implode(' ', array_slice($words, 0, $i));
                }

                foreach ($search_terms as $term) {
                    $response = wp_remote_get("https://pixabay.com/api/?key=$api_key&q=" . urlencode($term) . "&image_type=photo");

                    if (is_wp_error($response)) {
                        continue;  // Skip to the next search term if there's an error
                    }
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);

                    if (isset($data['hits'][0]['largeImageURL'])) {
                        return $data['hits'][0]['largeImageURL'];  // Return the first found image URL
                    }
                  }
                return false;  // Return false if no image is found for any search term
            }

            
  

              /*================================
                    SEO keyword Generate
              ================================ */

              public function otslf_generate_seo_keyword() {

                check_ajax_referer('ai-seo-content-nonce', 'nonce');
                
                if (!isset($_POST['blog_title'])) {
                    wp_send_json_error('Blog title not provided', 400);
                }
            
                $blog_title = sanitize_text_field(wp_unslash($_POST['blog_title']));

                $prompt = 'Write five SEO keywords based on the following input: ' . $blog_title;
            
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
            
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    wp_send_json_error('Error connecting to OpenAI API: ' . $error_message);
                } else {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
            
                    if (isset($data['choices'][0]['message']['content'])) {
                        $titles = explode("\n", $data['choices'][0]['message']['content']);
                        $titles = array_map('trim', $titles);
                        $titles = array_filter($titles, function($title) {
                            return !empty($title);
                        });
            
                        $clean_titles = array_map(function($title) {
                            $title = preg_replace('/^\d+\.\s*/', '', $title);
                            return trim($title, '"');
                        }, $titles);
                        
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

              public function otslf_generate_seo_metadescription() {
                check_ajax_referer('ai-seo-content-nonce', 'nonce');
            
                if (!isset($_POST['blog_title'])) {
                    wp_send_json_error('Blog title not provided', 400);
                }
            
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
                        'Authorization' => 'Bearer ' . esc_html($this->apikey)
                    ),
                    'timeout' => 20,
                ));
            
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
          
} /* END THE Class */
