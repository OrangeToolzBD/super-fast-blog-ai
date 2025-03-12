<?php 
/**
 *
 *  orange setting managment
 *
 **/

namespace Orange\AiBlog\Src;

class Class_Setting_Management {
    
    public function otslf_settings_init() {

        register_setting('otslf_setting_group', 'otslf_Provider', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_api_key', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_model', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_featured_image', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_image_generate_api_key', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));        
        register_setting('otslf_setting_group', 'otslf_unsplash_generate_api_key', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_email_notification', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_language_select', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_written_select', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_language_tone', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_word_count', array('type' => 'integer', 'sanitize_callback' => 'absint')); // Ensure this is a number
        register_setting('otslf_setting_group', 'otslf_list_faq', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_sub_heading', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_htaging', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_number_h', array('type' => 'integer', 'sanitize_callback' => 'absint')); // Ensure this is a number
        register_setting('otslf_setting_group', 'otslf_seokeyword', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_meta_des', array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field')); // Consider wp_kses_post() if HTML is needed
        register_setting('otslf_setting_group', 'otslf_schedule', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_same_schedule', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
        register_setting('otslf_setting_group', 'otslf_sched_day', array('type' => 'integer', 'sanitize_callback' => 'absint')); // Ensure this is a number
        register_setting('otslf_setting_group', 'otslf_oclock', array('type' => 'integer', 'sanitize_callback' => 'absint'));
        register_setting('otslf_setting_group', 'otslf_hoursInput', array('type' => 'integer', 'sanitize_callback' => 'absint'));
        register_setting('otslf_setting_group', 'otslf_minutesInput', array('type' => 'integer', 'sanitize_callback' => 'absint'));
        register_setting('otslf_setting_group', 'otslf_laterdate', array('type' => 'integer', 'sanitize_callback' => 'absint'));
        register_setting('otslf_setting_group', 'otslf_recurdate', array('type' => 'integer', 'sanitize_callback' => 'absint'));
        register_setting('otslf_setting_group', 'otslf_rcuroclock', array('type' => 'integer', 'sanitize_callback' => 'absint'));
        register_setting('otslf_setting_group', 'otslf_ot_taxonomy', array('sanitize_callback' => array($this, 'otslf_sanitize_taxonomy') ) );
    }
    
      /*== taxonomy sanitize === */      
    public function otslf_sanitize_taxonomy( $input ) {
        $output = array();
        if ( is_array( $input ) ) {
            $output = array_map( 'sanitize_text_field', $input );
        }
        return $output;
    }

    public function otslf_setting_fields(){
 ?>
       <div class="row-1">
            <div class="col-3"><h1> <?php esc_html_e('Setting Section', 'super-fast-blog-ai'); ?> </h1></div>
       </div>
        
        <?php 
            if (isset($_GET['settings-updated'])) {
                echo '<div id="setting-success-message" style="display:none;">' . esc_html__('Congratulations! Settings saved successfully.', 'super-fast-blog-ai') . '</div>';
            }
        ?>

    <form method="post" action="options.php">   

            <?php settings_fields('otslf_setting_group'); ?>
            <?php do_settings_sections('otslf_setting_group'); ?>

    <div id="pluginwrapper">      
      <div class="plugin-row">       
            <h2> <?php esc_html_e('Ai Engine', 'super-fast-blog-ai'); ?> </h2>
            <div class="ai_engine"> 
                
            <table class="form-table ">
                    <tr> 
                        <td><label> <?php esc_html_e('Provider','super-fast-blog-ai');?></label></td>   
                    </tr>
                    <tr valign="top">
                        <td> 
                        <select id="otslf_Provider" name="otslf_Provider">
                            <option value="OpenAI" <?php selected('OpenAI', get_option('slf_Provider')); ?>><?php esc_html_e('OpenAI','super-fast-blog-ai');?></option>
                            <!-- <option value="Gemini" <?php //selected('Gemini', get_option('slf_Provider')); ?>><?php //esc_html_e('Gemini','super-fast-blog-ai');?></option> -->
                        </select>
                        </td>
                    </tr>   
                    <tr> 
                        <td><label><?php esc_html_e('Api key','super-fast-blog-ai');?></label></td>   
                    </tr>
                    <tr valign="top">
                     <td class="api_key_model_box">
                        <div class="api_field-box">
                        <input id="api-field" type="password" name="otslf_api_key" value="<?php echo esc_attr(get_option('otslf_api_key')); ?>"/>
                        <span toggle="#api-field" class="api_input_field"></span>
                        </div>
                      <span class="img_api"> <a href="https://platform.openai.com/api-keys" target="_blank">Get Your Api Key</a> </span></td>
                    </tr>
                    <tr> 
                        <td><label><?php echo esc_html_e('Model','super-fast-blog-ai')?></label></td>   
                    </tr>
                    <tr valign="top">
                        <td> 
                        <select id="otslf_model" name="otslf_model"> 
                        <option value="gpt-4o" <?php selected('gpt-4o', get_option('otslf_model')); ?>><?php esc_html_e('GPT-4o','super-fast-blog-ai');?></option>
                        </select> 
                    </td>    
                    </tr>    
            </table>       
        </div>    
      </div>

      <div class="plugin-row">  
           <h2> <?php esc_html_e('Image Generate','super-fast-blog-ai');?> </h2>
            <div class="ai_engine"> 
            <table class="form-table">
                    <tr> 
                         <td><label><?php esc_html_e('Featured Image','super-fast-blog-ai');?></label></td>   
                    </tr>
                    <tr valign="top">
                        <td>                             
                            <select id="otslf_featured_image" name="otslf_featured_image">
                                <option value="dalle3" <?php selected('dalle3', get_option('otslf_featured_image')); ?>><?php esc_html_e('DALL-E 3','super-fast-blog-ai');?> </option>
                                <option value="pixabay" <?php selected('pixabay', get_option('otslf_featured_image')); ?>><?php esc_html_e('Pixabay','super-fast-blog-ai');?></option>
                                <option value="unsplash" <?php selected('unsplash', get_option('otslf_featured_image')); ?>><?php esc_html_e('Unsplash','super-fast-blog-ai');?></option>
                            </select> 
                        </td>
                    </tr>   

                      <tr> 
                        <td><label><?php esc_html_e('Image Api Key','super-fast-blog-ai');?></label></td>   
                      </tr>   

                    <tr valign="top">
                        <td> 
                            
                           <div id="otslf_image_generate_api_key">
                                <div class="img_api_field-box">
                                    <input id="otslf_image_generate_api_key" type="password" name="otslf_image_generate_api_key" 
                                        value="<?php echo esc_attr(get_option('otslf_image_generate_api_key')); ?>"/>
                                    <span class="img_api_input_field"></span>
                                </div> 
                                <span class="img_api"><a href="https://pixabay.com/api/docs" target="_blank">Get Your API Key</a></span>     
                           </div>
                            
                            <div id="otslf_dall3_generate_api_key">
                                Openai API will be used, don't use other Api.
                            </div>   
                            
                            <div id="otslf_unsplash_generate_api_key">
                                <div class="img_api_field-box">
                                    <input id="otslf_unsplash_generate_api_key" type="password" name="otslf_unsplash_generate_api_key" 
                                        value="<?php echo esc_attr(get_option('otslf_unsplash_generate_api_key')); ?>"/>
                                    <span class="img_api_input_field"></span>
                                </div> 
                                <span class="img_api"><a href="https://pixabay.com/api/docs" target="_blank">Get Your API Key</a></span>     
                           </div>

                        </td>
                    </tr>
                   
                </table>
            </div>
        </div>

    <div class="plugin-row">
        <h2><?php esc_html_e('General','super-fast-blog-ai');?> </h2>

        <div class="ai_engine"> 
        <table class="form-table ai_engine">            
                <tr valign="top">
                    <td> 
                    <label id="otslf_email_notification"><?php esc_html_e('Enable Email Notification','super-fast-blog-ai')?></label>   
                    <input type="checkbox" name="otslf_email_notification" value="1" <?php checked(1, get_option('otslf_email_notification'), true); ?> />
                    </td> 
                </tr>
       </table>         
       <table class="form-table ai_engine">         
                    <tr> 
                     <td><label><?php esc_html_e('Language','super-fast-blog-ai')?></label></td>   
                    </tr>      
                <tr valign="top">
                    <td> 

                <select id="otslf_language_select" name="otslf_language_select">
                    
                <?php selected('hi', get_option('slf_language_select')); ?>                
                <option value="en" <?php selected('en', get_option('otslf_language_select')); ?>><?php esc_html_e('English', 'super-fast-blog-ai'); ?></option>
                <option value="af" <?php selected('af', get_option('otslf_language_select')); ?>><?php esc_html_e('Afrikaans', 'super-fast-blog-ai'); ?></option>
                <option value="ar" <?php selected('ar', get_option('otslf_language_select')); ?>><?php esc_html_e('Arabic', 'super-fast-blog-ai'); ?></option>
                <option value="an" <?php selected('an', get_option('otslf_language_select')); ?>><?php esc_html_e('Armenian', 'super-fast-blog-ai'); ?></option>
                <option value="bs" <?php selected('bs', get_option('otslf_language_select')); ?>><?php esc_html_e('Bosnian', 'super-fast-blog-ai'); ?></option>
                <option value="bg" <?php selected('bg', get_option('otslf_language_select')); ?>><?php esc_html_e('Bulgarian', 'super-fast-blog-ai'); ?></option>
                <option value="zh" <?php selected('zh', get_option('otslf_language_select')); ?>><?php esc_html_e('Chinese (Simplified)', 'super-fast-blog-ai'); ?></option>
                <option value="zt" <?php selected('zt', get_option('otslf_language_select')); ?>><?php esc_html_e('Chinese (Traditional)', 'super-fast-blog-ai'); ?></option>
                <option value="hr" <?php selected('hr', get_option('otslf_language_select')); ?>><?php esc_html_e('Croatian', 'super-fast-blog-ai'); ?></option>
                <option value="cs" <?php selected('cs', get_option('otslf_language_select')); ?>><?php esc_html_e('Czech', 'super-fast-blog-ai'); ?></option>
                <option value="da" <?php selected('da', get_option('otslf_language_select')); ?>><?php esc_html_e('Danish', 'super-fast-blog-ai'); ?></option>
                <option value="nl" <?php selected('nl', get_option('otslf_language_select')); ?>><?php esc_html_e('Dutch', 'super-fast-blog-ai'); ?></option>
                <option value="et" <?php selected('et', get_option('otslf_language_select')); ?>><?php esc_html_e('Estonian', 'super-fast-blog-ai'); ?></option>
                <option value="fil" <?php selected('fil', get_option('otslf_language_select')); ?>><?php esc_html_e('Filipino', 'super-fast-blog-ai'); ?></option>
                <option value="fi" <?php selected('fi', get_option('otslf_language_select')); ?>><?php esc_html_e('Finnish', 'super-fast-blog-ai'); ?></option>
                <option value="fr" <?php selected('fr', get_option('otslf_language_select')); ?>><?php esc_html_e('French', 'super-fast-blog-ai'); ?></option>
                <option value="de" <?php selected('de', get_option('otslf_language_select')); ?>><?php esc_html_e('German', 'super-fast-blog-ai'); ?></option>
                <option value="el" <?php selected('el', get_option('otslf_language_select')); ?>><?php esc_html_e('Greek', 'super-fast-blog-ai'); ?></option>
                <option value="he" <?php selected('he', get_option('otslf_language_select')); ?>><?php esc_html_e('Hebrew', 'super-fast-blog-ai'); ?></option>
                <option value="hi" <?php selected('hi', get_option('otslf_language_select')); ?>><?php esc_html_e('Hindi', 'super-fast-blog-ai'); ?></option>
                <option value="hu" <?php selected('hu', get_option('otslf_language_select')); ?>><?php esc_html_e('Hungarian', 'super-fast-blog-ai'); ?></option>
                <option value="id" <?php selected('id', get_option('otslf_language_select')); ?>><?php esc_html_e('Indonesian', 'super-fast-blog-ai'); ?></option>
                <option value="it" <?php selected('it', get_option('otslf_language_select')); ?>><?php esc_html_e('Italian', 'super-fast-blog-ai'); ?></option>
                <option value="ja" <?php selected('ja', get_option('otslf_language_select')); ?>><?php esc_html_e('Japanese', 'super-fast-blog-ai'); ?></option>
                <option value="ko" <?php selected('ko', get_option('otslf_language_select')); ?>><?php esc_html_e('Korean', 'super-fast-blog-ai'); ?></option>
                <option value="lv" <?php selected('lv', get_option('otslf_language_select')); ?>><?php esc_html_e('Latvian', 'super-fast-blog-ai'); ?></option>
                <option value="lt" <?php selected('lt', get_option('otslf_language_select')); ?>><?php esc_html_e('Lithuanian', 'super-fast-blog-ai'); ?></option>
                <option value="ms" <?php selected('ms', get_option('otslf_language_select')); ?>><?php esc_html_e('Malay', 'super-fast-blog-ai'); ?></option>
                <option value="no" <?php selected('no', get_option('otslf_language_select')); ?>><?php esc_html_e('Norwegian', 'super-fast-blog-ai'); ?></option>
                <option value="fa" <?php selected('fa', get_option('otslf_language_select')); ?>><?php esc_html_e('Persian', 'super-fast-blog-ai'); ?></option>
                <option value="pl" <?php selected('pl', get_option('otslf_language_select')); ?>><?php esc_html_e('Polish', 'super-fast-blog-ai'); ?></option>
                <option value="pt" <?php selected('pt', get_option('otslf_language_select')); ?>><?php esc_html_e('Portuguese', 'super-fast-blog-ai'); ?></option>
                <option value="ro" <?php selected('ro', get_option('otslf_language_select')); ?>><?php esc_html_e('Romanian', 'super-fast-blog-ai'); ?></option>
                <option value="ru" <?php selected('ru', get_option('otslf_language_select')); ?>><?php esc_html_e('Russian', 'super-fast-blog-ai'); ?></option>
                <option value="sr" <?php selected('sr', get_option('otslf_language_select')); ?>><?php esc_html_e('Serbian', 'super-fast-blog-ai'); ?></option>
                <option value="sk" <?php selected('sk', get_option('otslf_language_select')); ?>><?php esc_html_e('Slovak', 'super-fast-blog-ai'); ?></option>
                <option value="sl" <?php selected('sl', get_option('otslf_language_select')); ?>><?php esc_html_e('Slovenian', 'super-fast-blog-ai'); ?></option>
                <option value="es" <?php selected('es', get_option('otslf_language_select')); ?>><?php esc_html_e('Spanish', 'super-fast-blog-ai'); ?></option>
                <option value="sv" <?php selected('sv', get_option('otslf_language_select')); ?>><?php esc_html_e('Swedish', 'super-fast-blog-ai'); ?></option>
                <option value="th" <?php selected('th', get_option('otslf_language_select')); ?>><?php esc_html_e('Thai', 'super-fast-blog-ai'); ?></option>
                <option value="tr" <?php selected('tr', get_option('otslf_language_select')); ?>><?php esc_html_e('Turkish', 'super-fast-blog-ai'); ?></option>
                <option value="uk" <?php selected('uk', get_option('otslf_language_select')); ?>><?php esc_html_e('Ukrainian', 'super-fast-blog-ai'); ?></option>
                <option value="vi" <?php selected('vi', get_option('otslf_language_select')); ?>><?php esc_html_e('Vietnamese', 'super-fast-blog-ai'); ?></option>

                </select>    

                    </td>
                </tr>
                <tr> <td> <label><?php esc_html_e('Writing Style','super-fast-blog-ai')?></label> </td> </tr>
                <tr valign="top">
                    <td> 
                    <select id="otslf_written_select" name="otslf_written_select">
                        <option value="infor" <?php selected('infor', get_option('otslf_written_select')); ?>><?php esc_html_e('Informative', 'super-fast-blog-ai'); ?></option>
                        <option value="acade" <?php selected('acade', get_option('otslf_written_select')); ?>><?php esc_html_e('Academic', 'super-fast-blog-ai'); ?></option>
                        <option value="analy" <?php selected('analy', get_option('otslf_written_select')); ?>><?php esc_html_e('Analytical', 'super-fast-blog-ai'); ?></option>
                        <option value="anect" <?php selected('anect', get_option('otslf_written_select')); ?>><?php esc_html_e('Anecdotal', 'super-fast-blog-ai'); ?></option>
                        <option value="argum" <?php selected('argum', get_option('otslf_written_select')); ?>><?php esc_html_e('Argumentative', 'super-fast-blog-ai'); ?></option>
                        <option value="artic" <?php selected('artic', get_option('otslf_written_select')); ?>><?php esc_html_e('Articulate', 'super-fast-blog-ai'); ?></option>
                        <option value="biogr" <?php selected('biogr', get_option('otslf_written_select')); ?>><?php esc_html_e('Biographical', 'super-fast-blog-ai'); ?></option>
                        <option value="blog" <?php selected('blog', get_option('otslf_written_select')); ?>><?php esc_html_e('Blog', 'super-fast-blog-ai'); ?></option>
                        <option value="casua" <?php selected('casua', get_option('otslf_written_select')); ?>><?php esc_html_e('Casual', 'super-fast-blog-ai'); ?></option>
                        <option value="collo" <?php selected('collo', get_option('otslf_written_select')); ?>><?php esc_html_e('Colloquial', 'super-fast-blog-ai'); ?></option>
                        <option value="compa" <?php selected('compa', get_option('otslf_written_select')); ?>><?php esc_html_e('Comparative', 'super-fast-blog-ai'); ?></option>
                        <option value="conci" <?php selected('conci', get_option('otslf_written_select')); ?>><?php esc_html_e('Concise', 'super-fast-blog-ai'); ?></option>
                        <option value="creat" <?php selected('creat', get_option('otslf_written_select')); ?>><?php esc_html_e('Creative', 'super-fast-blog-ai'); ?></option>
                        <option value="criti" <?php selected('criti', get_option('otslf_written_select')); ?>><?php esc_html_e('Critical', 'super-fast-blog-ai'); ?></option>
                        <option value="descr" <?php selected('descr', get_option('otslf_written_select')); ?>><?php esc_html_e('Descriptive', 'super-fast-blog-ai'); ?></option>
                        <option value="detai" <?php selected('detai', get_option('otslf_written_select')); ?>><?php esc_html_e('Detailed', 'super-fast-blog-ai'); ?></option>
                        <option value="dialo" <?php selected('dialo', get_option('otslf_written_select')); ?>><?php esc_html_e('Dialogue', 'super-fast-blog-ai'); ?></option>
                        <option value="direct" <?php selected('direct', get_option('otslf_written_select')); ?>><?php esc_html_e('Direct', 'super-fast-blog-ai'); ?></option>
                        <option value="drama" <?php selected('drama', get_option('otslf_written_select')); ?>><?php esc_html_e('Dramatic', 'super-fast-blog-ai'); ?></option>
                        <option value="evalu" <?php selected('evalu', get_option('otslf_written_select')); ?>><?php esc_html_e('Evaluative', 'super-fast-blog-ai'); ?></option>
                        <option value="emoti" <?php selected('emoti', get_option('otslf_written_select')); ?>><?php esc_html_e('Emotional', 'super-fast-blog-ai'); ?></option>
                        <option value="expos" <?php selected('expos', get_option('otslf_written_select')); ?>><?php esc_html_e('Expository', 'super-fast-blog-ai'); ?></option>
                        <option value="ficti" <?php selected('ficti', get_option('otslf_written_select')); ?>><?php esc_html_e('Fiction', 'super-fast-blog-ai'); ?></option>
                        <option value="histo" <?php selected('histo', get_option('otslf_written_select')); ?>><?php esc_html_e('Historical', 'super-fast-blog-ai'); ?></option>
                        <option value="journ" <?php selected('journ', get_option('otslf_written_select')); ?>><?php esc_html_e('Journalistic', 'super-fast-blog-ai'); ?></option>
                        <option value="lette" <?php selected('lette', get_option('otslf_written_select')); ?>><?php esc_html_e('Letter', 'super-fast-blog-ai'); ?></option>
                        <option value="lyric" <?php selected('lyric', get_option('otslf_written_select')); ?>><?php esc_html_e('Lyrical', 'super-fast-blog-ai'); ?></option>
                        <option value="metaph" <?php selected('metaph', get_option('otslf_written_select')); ?>><?php esc_html_e('Metaphorical', 'super-fast-blog-ai'); ?></option>
                        <option value="monol" <?php selected('monol', get_option('otslf_written_select')); ?>><?php esc_html_e('Monologue', 'super-fast-blog-ai'); ?></option>
                        <option value="narra" <?php selected('narra', get_option('otslf_written_select')); ?>><?php esc_html_e('Narrative', 'super-fast-blog-ai'); ?></option>
                        <option value="news" <?php selected('news', get_option('otslf_written_select')); ?>><?php esc_html_e('News', 'super-fast-blog-ai'); ?></option>
                        <option value="objec" <?php selected('objec', get_option('otslf_written_select')); ?>><?php esc_html_e('Objective', 'super-fast-blog-ai'); ?></option>
                        <option value="pasto" <?php selected('pasto', get_option('otslf_written_select')); ?>><?php esc_html_e('Pastoral', 'super-fast-blog-ai'); ?></option>
                        <option value="perso" <?php selected('perso', get_option('otslf_written_select')); ?>><?php esc_html_e('Personal', 'super-fast-blog-ai'); ?></option>
                        <option value="persu" <?php selected('persu', get_option('otslf_written_select')); ?>><?php esc_html_e('Persuasive', 'super-fast-blog-ai'); ?></option>
                        <option value="poeti" <?php selected('poeti', get_option('otslf_written_select')); ?>><?php esc_html_e('Poetic', 'super-fast-blog-ai'); ?></option>
                        <option value="refle" <?php selected('refle', get_option('otslf_written_select')); ?>><?php esc_html_e('Reflective', 'super-fast-blog-ai'); ?></option>
                        <option value="rheto" <?php selected('rheto', get_option('otslf_written_select')); ?>><?php esc_html_e('Rhetorical', 'super-fast-blog-ai'); ?></option>
                        <option value="satir" <?php selected('satir', get_option('otslf_written_select')); ?>><?php esc_html_e('Satirical', 'super-fast-blog-ai'); ?></option>
                        <option value="senso" <?php selected('senso', get_option('otslf_written_select')); ?>><?php esc_html_e('Sensory', 'super-fast-blog-ai'); ?></option>
                        <option value="simpl" <?php selected('simpl', get_option('otslf_written_select')); ?>><?php esc_html_e('Simple', 'super-fast-blog-ai'); ?></option>
                        <option value="techn" <?php selected('techn', get_option('otslf_written_select')); ?>><?php esc_html_e('Technical', 'super-fast-blog-ai'); ?></option>
                        <option value="theore" <?php selected('theore', get_option('otslf_written_select')); ?>><?php esc_html_e('Theoretical', 'super-fast-blog-ai'); ?></option>
                        <option value="vivid" <?php selected('vivid', get_option('otslf_written_select')); ?>><?php esc_html_e('Vivid', 'super-fast-blog-ai'); ?></option>
                        <option value="busin" <?php selected('busin', get_option('otslf_written_select')); ?>><?php esc_html_e('Business', 'super-fast-blog-ai'); ?></option>
                        <option value="repor" <?php selected('repor', get_option('otslf_written_select')); ?>><?php esc_html_e('Report', 'super-fast-blog-ai'); ?></option>
                        <option value="resea" <?php selected('resea', get_option('otslf_written_select')); ?>><?php esc_html_e('Research', 'super-fast-blog-ai'); ?></option>
                   </td>
                </tr>
                  <tr> <td><label><?php esc_html_e('Language Tone ','super-fast-blog-ai')?></label></td></tr>
                <tr valign="top">
                    <td> 
                    <select id="otslf_language_tone" name="otslf_language_tone">            
                        <option value="formal" <?php selected('formal', get_option('otslf_language_tone')); ?>><?php esc_html_e('Formal', 'super-fast-blog-ai'); ?></option>
                        <option value="asser" <?php selected('asser', get_option('otslf_language_tone')); ?>><?php esc_html_e('Assertive', 'super-fast-blog-ai'); ?></option>
                        <option value="authoritative" <?php selected('authoritative', get_option('otslf_language_tone')); ?>><?php esc_html_e('Authoritative', 'super-fast-blog-ai'); ?></option>
                        <option value="cheer" <?php selected('cheer', get_option('otslf_language_tone')); ?>><?php esc_html_e('Cheerful', 'super-fast-blog-ai'); ?></option>
                        <option value="confident" <?php selected('confident', get_option('otslf_language_tone')); ?>><?php esc_html_e('Confident', 'super-fast-blog-ai'); ?></option>
                        <option value="conve" <?php selected('conve', get_option('otslf_language_tone')); ?>><?php esc_html_e('Conversational', 'super-fast-blog-ai'); ?></option>
                        <option value="factual" <?php selected('factual', get_option('otslf_language_tone')); ?>><?php esc_html_e('Factual', 'super-fast-blog-ai'); ?></option>
                        <option value="friendly" <?php selected('friendly', get_option('otslf_language_tone')); ?>><?php esc_html_e('Friendly', 'super-fast-blog-ai'); ?></option>
                        <option value="humor" <?php selected('humor', get_option('otslf_language_tone')); ?>><?php esc_html_e('Humorous', 'super-fast-blog-ai'); ?></option>
                        <option value="informal" <?php selected('informal', get_option('otslf_language_tone')); ?>><?php esc_html_e('Informal', 'super-fast-blog-ai'); ?></option>
                        <option value="inspi" <?php selected('inspi', get_option('otslf_language_tone')); ?>><?php esc_html_e('Inspirational', 'super-fast-blog-ai'); ?></option>
                        <option value="neutr" <?php selected('neutr', get_option('otslf_language_tone')); ?>><?php esc_html_e('Neutral', 'super-fast-blog-ai'); ?></option>
                        <option value="nostalgic" <?php selected('nostalgic', get_option('otslf_language_tone')); ?>><?php esc_html_e('Nostalgic', 'super-fast-blog-ai'); ?></option>
                        <option value="polite" <?php selected('polite', get_option('otslf_language_tone')); ?>><?php esc_html_e('Polite', 'super-fast-blog-ai'); ?></option>
                        <option value="profe" <?php selected('profe', get_option('otslf_language_tone')); ?>><?php esc_html_e('Professional', 'super-fast-blog-ai'); ?></option>
                        <option value="romantic" <?php selected('romantic', get_option('otslf_language_tone')); ?>><?php esc_html_e('Romantic', 'super-fast-blog-ai'); ?></option>
                        <option value="sarca" <?php selected('sarca', get_option('otslf_language_tone')); ?>><?php esc_html_e('Sarcastic', 'super-fast-blog-ai'); ?></option>
                        <option value="scien" <?php selected('scien', get_option('otslf_language_tone')); ?>><?php esc_html_e('Scientific', 'super-fast-blog-ai'); ?></option>
                        <option value="sensit" <?php selected('sensit', get_option('otslf_language_tone')); ?>><?php esc_html_e('Sensitive', 'super-fast-blog-ai'); ?></option>
                        <option value="serious" <?php selected('serious', get_option('otslf_language_tone')); ?>><?php esc_html_e('Serious', 'super-fast-blog-ai'); ?></option>
                        <option value="sincere" <?php selected('sincere', get_option('otslf_language_tone')); ?>><?php esc_html_e('Sincere', 'super-fast-blog-ai'); ?></option>
                        <option value="skept" <?php selected('skept', get_option('otslf_language_tone')); ?>><?php esc_html_e('Skeptical', 'super-fast-blog-ai'); ?></option>
                        <option value="suspenseful" <?php selected('suspenseful', get_option('otslf_language_tone')); ?>><?php esc_html_e('Suspenseful', 'super-fast-blog-ai'); ?></option>
                        <option value="sympathetic" <?php selected('sympathetic', get_option('otslf_language_tone')); ?>><?php esc_html_e('Sympathetic', 'super-fast-blog-ai'); ?></option>
                        <option value="curio" <?php selected('curio', get_option('otslf_language_tone')); ?>><?php esc_html_e('Curious', 'super-fast-blog-ai'); ?></option>
                        <option value="disap" <?php selected('disap', get_option('otslf_language_tone')); ?>><?php esc_html_e('Disappointed', 'super-fast-blog-ai'); ?></option>
                        <option value="encou" <?php selected('encou', get_option('otslf_language_tone')); ?>><?php esc_html_e('Encouraging', 'super-fast-blog-ai'); ?></option>
                        <option value="optim" <?php selected('optim', get_option('otslf_language_tone')); ?>><?php esc_html_e('Optimistic', 'super-fast-blog-ai'); ?></option>
                        <option value="surpr" <?php selected('surpr', get_option('otslf_language_tone')); ?>><?php esc_html_e('Surprised', 'super-fast-blog-ai'); ?></option>
                        <option value="worry" <?php selected('worry', get_option('otslf_language_tone')); ?>><?php esc_html_e('Worried', 'super-fast-blog-ai'); ?></option>
                    </select>
                    </td>
                </tr>
               <tr><td><label><?php esc_html_e('Word Count','super-fast-blog-ai');?></label></td></tr>
               <tr valign="top">        
                <td> 
                   <input type="number" id="otslf_word_count" name="otslf_word_count" value="<?php 
                        if (empty(get_option('otslf_word_count'))){
                            echo esc_attr(trim(500));     
                        }else{
                            echo esc_attr(get_option('otslf_word_count')); 
                        }
                        ?>"/>
                        <p><i><?php esc_html_e('You can write max token how many like : 1500, 1000, 2000','super-fast-blog-ai')?></i></p>      
                </td>
            </tr>
            <tr>
            <td colspan="2">    
             <div class="form-section">
                <div class="checkbox-container">
                    <label for="otslf_sub_heading"><?php esc_html_e('Sub Heading','super-fast-blog-ai');?></label>    
                    <input type="checkbox" id="otslf_sub_heading" name="otslf_sub_heading" value="1" <?php checked(1, get_option('otslf_sub_heading'), true); ?> /> 
                </div>
                 
                <div class="row heading-row">
                    <div class="dropdown-container">
                      <div class="colleft"><?php esc_html_e('Heading Tag','super-fast-blog-ai'); ?></div>
                        <select id="otslf_htaging" name="otslf_htaging">
                            <option value="h1" <?php selected('h1', get_option('otslf_htaging')); ?>>H1</option>
                            <option value="h2" <?php selected('h2', get_option('otslf_htaging')); ?>>H2</option>
                            <option value="h3" <?php selected('h3', get_option('otslf_htaging')); ?>>H3</option>
                            <option value="h4" <?php selected('h4', get_option('otslf_htaging')); ?>>H4</option>
                            <option value="h5" <?php selected('h5', get_option('otslf_htaging')); ?>>H5</option>
                            <option value="h6" <?php selected('h6', get_option('otslf_htaging')); ?>>H6</option>
                        </select>
                    </div>
                
                    <div class="dropdown-container">
                      <div class="colright"><?php esc_html_e('Number of Heading','super-fast-blog-ai');?></div>
                        <select id="otslf_number_h" name="otslf_number_h">
                            <option value="1" <?php selected('1', get_option('otslf_number_h')); ?>>1</option>
                            <option value="2" <?php selected('2', get_option('otslf_number_h')); ?>>2</option>
                            <option value="3" <?php selected('3', get_option('otslf_number_h')); ?>>3</option>
                            <option value="4" <?php selected('4', get_option('otslf_number_h')); ?>>4</option>
                            <option value="5" <?php selected('5', get_option('otslf_number_h')); ?>>5</option>
                            <option value="6" <?php selected('6', get_option('otslf_number_h')); ?>>6</option>
                            <option value="7" <?php selected('7', get_option('otslf_number_h')); ?>>7</option>
                            <option value="8" <?php selected('8', get_option('otslf_number_h')); ?>>8</option>
                            <option value="9" <?php selected('9', get_option('otslf_number_h')); ?>>9</option>
                            <option value="10" <?php selected('10', get_option('otslf_number_h')); ?>>10</option>
                            <option value="11" <?php selected('11', get_option('otslf_number_h')); ?>>11</option>
                            <option value="12" <?php selected('12', get_option('otslf_number_h')); ?>>12</option>
                    </select>
                    </div>
                </div>
                </td>
            </tr>

            <tr valign="top">        
             <th scope="row">
             <div class="checkbox-container">
                <label for="otslf_list_faq"><?php esc_html_e('Article FAQ','super-fast-blog-ai')?></label>    
                <input type="checkbox" id="otslf_list_faq" name="otslf_list_faq" value="1" <?php checked('1', get_option('otslf_list_faq')); ?> />
             </div>  
             </th>  
            </tr>
          </table>
        </div>    
    </div>

    <div class="plugin-row"> 
            <h2><?php esc_html_e('SEO','super-fast-blog-ai');?> </h2>
         <div class="ai_engine"> 
            <table class="form-table">
                    <tr valign="top">
                        <td> 
                        <label for="otslf_seokeyword"><?php esc_html_e('Keyword Generate', 'super-fast-blog-ai') ?> </label>    
                           <input type="checkbox" id="otslf_seokeyword" name="otslf_seokeyword" value="1" <?php checked(1, get_option('otslf_seokeyword'), true); ?> />                            
                        </td> 
                        <td> 
                    </tr>   
                    <tr> 
                      <td>
                        <label for="otslf_meta_des"><?php esc_html_e('Meta Description Generate','super-fast-blog-ai') ?></label>      
                        <input type="checkbox" id="otslf_meta_des" name="otslf_meta_des" value="1" <?php checked(1, get_option('otslf_meta_des'), true); ?> />
                      </td>  
                    </tr>
            </table>    
         </div>
    </div>    
    
    <div class="plugin-row">
           <h2><?php esc_html_e('Post Publish','super-fast-blog-ai');?> </h2>   
        <div class="ai_engine"> 
           
          <div class="seorow">
            <?php 
             $terms = get_terms(array(
                    'taxonomy' => 'category', 
                    'hide_empty' => false, 
                ));

                $saved_taxonomies = get_option('otslf_ot_taxonomy', []);
                if (!is_array($saved_taxonomies)) {
                    $saved_taxonomies = [];
                }

                if (!empty($terms) && !is_wp_error($terms)) {
                    echo '<ul class="custom-taxonomy-list">';
                    foreach ($terms as $term) {
                        $checked = in_array($term->term_id, $saved_taxonomies) ? 'checked="checked"' : '';
                        echo '<li>';
                        echo '<label>';
                        echo '<input type="checkbox" name="otslf_ot_taxonomy[]" value="' . esc_attr($term->term_id) . '" ' . esc_attr($checked) . '> ';
                        echo esc_html($term->name);
                        echo '</label>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }                
            ?>
          </div>    
        </div>    
    </div>      
            <?php
            add_action('pre_update_option_custom_taxonomy', function($value, $old_value) {
                return is_array($value) ? $value : [];
            }, 10, 2);
            ?>  

            <div class="plugin-row"> 
                <h2><?php esc_html_e('Post Schedule','super-fast-blog-ai');?> </h2>    
            <div class="ai_engine"> 
                <div id="schedule"> <?php esc_html_e('Select Schedule','super-fast-blog-ai');?> </div>

                    <div class="radio-buttons">
                        <div class="radio-button">
                            <input type="radio" id="same-day" name="otslf_schedule" value="sameday" <?php checked('sameday', get_option('otslf_schedule'), true); ?> /> 
                            <label for="same-day"> <?php esc_html_e('Same day','super-fast-blog-ai');?> </label>
                        </div>
                        <div class="radio-button">
                            <input type="radio" id="hour" name="otslf_schedule" value="later" <?php checked('later', get_option('otslf_schedule'), true); ?> /> 
                            <label for="hour"> <?php esc_html_e('Later Day','super-fast-blog-ai');?> </label>
                        </div>
                        <div class="radio-button">
                            <input type="radio" id="daily" name="otslf_schedule" value="recurring" <?php checked('recurring', get_option('otslf_schedule'), true); ?> />
                            <label for="daily"> <?php esc_html_e('Recurring','super-fast-blog-ai');?> </label>
                        </div>
                    </div>
                   
                   <div id="sameday"> 
                        <div class="schedule-item">
                            <label for=""><?php esc_html_e('When to send','super-fast-blog-ai');?></label>
                              <div class="schedule-item-inner">
                                <p>
                                  <input type="radio" id="Immediately" name="otslf_same_schedule" value="immediately" checked <?php checked('immediately', get_option('otslf_same_schedule'), true); ?> /> 
                                  <label for="Immediately"> <?php esc_html_e('Immediately','super-fast-blog-ai');?></label>
                                </p>
                                <p>
                                  <input type="radio" id="later_same_day" name="otslf_same_schedule" value="later_same_day" <?php checked('later_same_day', get_option('otslf_same_schedule')); ?> /> 
                                  <label for="later_same_day"> <?php esc_html_e('Later on the same day','super-fast-blog-ai');?></label>
                                </p>
                              </div>
                        
                            <div class="same_schedule_post">
                              <label><?php esc_html_e('Post','super-fast-blog-ai');?> </label>

                              <!-- hours  --> 
                              <div class="input-container">
                                <input type="number" id="otslf_hoursInput" name="otslf_hoursInput" value="<?php echo esc_attr (get_option('otslf_hoursInput')) ?>"  min="0" max="12" placeholder="8 hr"/>
                                <label> <?php esc_html_e('oClock','super-fast-blog-ai');?></label>
                               
                              </div>

                              <!-- minutes  -->
                              <div class="input-container">
                                <input type="number" id="otslf_minutesInput" name="otslf_minutesInput" value="<?php echo esc_attr (get_option('otslf_minutesInput')) ?>" min="0" max="59" placeholder="8 min"/>
                              </div>
                              <label><?php esc_html_e('later.','super-fast-blog-ai');?> </label>
                            </div>
                                
                        </div>
                    </div>

                    <div class="later-box" id="later">
    
                        <div class="row-3"> 
                          <div class="day-column">  
                             <span>Day </span> 
                             <input type="number" id="otslf_laterdate" name="otslf_laterdate"  value="<?php echo esc_attr(get_option('otslf_laterdate')) ?>" placeholder="example: 2, 4 days">                             
                          </div>  
                          <div class="at-column" id="vcontent">
                                    <span>at</span>
                                    <select id="otslf_oclock" name="otslf_oclock">
                                        <option value="1:00 PM" <?php selected('1:00 PM', get_option('otslf_oclock')); ?>>1:00 PM</option>
                                        <option value="2:00 PM" <?php selected('2:00 PM', get_option('otslf_oclock')); ?>>2:00 PM</option>
                                        <option value="3:00 PM" <?php selected('3:00 PM', get_option('otslf_oclock')); ?>>3:00 PM</option>
                                        <option value="4:00 PM" <?php selected('4:00 PM', get_option('otslf_oclock')); ?>>4:00 PM</option>
                                        <option value="5:00 PM" <?php selected('5:00 PM', get_option('otslf_oclock')); ?>>5:00 PM</option>
                                        <option value="6:00 PM" <?php selected('6:00 PM', get_option('otslf_oclock')); ?>>6:00 PM</option>
                                        <option value="7:00 PM" <?php selected('7:00 PM', get_option('otslf_oclock')); ?>>7:00 PM</option>
                                        <option value="8:00 PM" <?php selected('8:00 PM', get_option('otslf_oclock')); ?>>8:00 PM</option>
                                        <option value="9:00 PM" <?php selected('9:00 PM', get_option('otslf_oclock')); ?>>9:00 PM</option>
                                        <option value="10:00 PM" <?php selected('10:00 PM', get_option('otslf_oclock')); ?>>10:00 PM</option>
                                        <option value="11:00 PM" <?php selected('11:00 PM', get_option('otslf_oclock')); ?>>11:00 PM</option>
                                        <option value="12:00 AM" <?php selected('12:00 AM', get_option('otslf_oclock')); ?>>12:00 AM</option>
                                        <option value="1:00 AM" <?php selected('1:00 AM', get_option('otslf_oclock')); ?>>1:00 AM</option>
                                        <option value="2:00 AM" <?php selected('2:00 AM', get_option('otslf_oclock')); ?>>2:00 AM</option>
                                        <option value="3:00 AM" <?php selected('3:00 AM', get_option('otslf_oclock')); ?>>3:00 AM</option>
                                        <option value="4:00 AM" <?php selected('4:00 AM', get_option('otslf_oclock')); ?>>4:00 AM</option>
                                        <option value="5:00 AM" <?php selected('5:00 AM', get_option('otslf_oclock')); ?>>5:00 AM</option>
                                        <option value="6:00 AM" <?php selected('6:00 AM', get_option('otslf_oclock')); ?>>6:00 AM</option>
                                        <option value="7:00 AM" <?php selected('7:00 AM', get_option('otslf_oclock')); ?>>7:00 AM</option>
                                        <option value="8:00 AM" <?php selected('8:00 AM', get_option('otslf_oclock')); ?>>8:00 AM</option>
                                        <option value="9:00 AM" <?php selected('9:00 AM', get_option('otslf_oclock')); ?>>9:00 AM</option>
                                        <option value="10:00 AM" <?php selected('10:00 AM', get_option('otslf_oclock')); ?>>10:00 AM</option>
                                        <option value="11:00 AM" <?php selected('11:00 AM', get_option('otslf_oclock')); ?>>11:00 AM</option>
                                        <option value="12:00 PM" <?php selected('12:00 PM', get_option('otslf_oclock')); ?>>12:00 PM</option>
                                    </select>
                            </div>    
                        </div>                        
                    </div> 

                    <div class="recurring-box" id="recurring">
                        <div class="row-3"> 
                            <div class="repeat-column">
                                <span>Repeat Eevery</span>
                               <input type="number" id="otslf_recurdate" name="otslf_recurdate" value="<?php echo esc_attr (get_option('otslf_recurdate')) ?>" placeholder="example: 2, 4 days">
                            </div>
                            <div class="at-column" id="vcontent">
                               <span>at</span>
                              <select id="otslf_rcuroclock" name="otslf_rcuroclock">
                                <option value="1:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '1:00 PM' ); ?>>1:00 PM</option>
                                <option value="2:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '2:00 PM' ); ?>>2:00 PM</option>
                                <option value="3:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '3:00 PM' ); ?>>3:00 PM</option>
                                <option value="4:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '4:00 PM' ); ?>>4:00 PM</option>
                                <option value="5:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '5:00 PM' ); ?>>5:00 PM</option>
                                <option value="6:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '6:00 PM' ); ?>>6:00 PM</option>
                                <option value="7:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '7:00 PM' ); ?>>7:00 PM</option>
                                <option value="8:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '8:00 PM' ); ?>>8:00 PM</option>
                                <option value="9:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '9:00 PM' ); ?>>9:00 PM</option>
                                <option value="10:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '10:00 PM' ); ?>>10:00 PM</option>
                                <option value="11:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '11:00 PM' ); ?>>11:00 PM</option>
                                <option value="12:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '12:00 AM' ); ?>>12:00 AM</option>
                                <option value="1:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '1:00 AM' ); ?>>1:00 AM</option>
                                <option value="2:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '2:00 AM' ); ?>>2:00 AM</option>
                                <option value="3:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '3:00 AM' ); ?>>3:00 AM</option>
                                <option value="4:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '4:00 AM' ); ?>>4:00 AM</option>
                                <option value="5:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '5:00 AM' ); ?>>5:00 AM</option>
                                <option value="6:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '6:00 AM' ); ?>>6:00 AM</option>
                                <option value="7:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '7:00 AM' ); ?>>7:00 AM</option>
                                <option value="8:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '8:00 AM' ); ?>>8:00 AM</option>
                                <option value="9:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '9:00 AM' ); ?>>9:00 AM</option>
                                <option value="10:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '10:00 AM' ); ?>>10:00 AM</option>
                                <option value="11:00 AM" <?php selected( get_option( 'otslf_rcuroclock' ), '11:00 AM' ); ?>>11:00 AM</option>
                                <option value="12:00 PM" <?php selected( get_option( 'otslf_rcuroclock' ), '12:00 PM' ); ?>>12:00 PM</option>

                              </select>
                            </div>
                            </div>
                        </div>
                      
            </div>

        </div>  
    </div>        
        <?php
        submit_button(
            'Save Changes',
            'primary',
            'submit',
            true,
            array(
                'class' => 'setting_button'
            )
        );
        ?>
    </form>

    <?php
        }    
    }



