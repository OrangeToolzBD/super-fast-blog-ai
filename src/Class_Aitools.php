<?php 
/**
 *
 *  orange setting managment
 *
 **/

namespace Orange\AiBlog\Src;

class Class_Aitools { 
  
    public function otslf_language (){
?>
      <p><?php echo esc_html_e('Language','super-fast-blog-ai');?> <span class="redmark">*</span></p>
      <select name="tlanguage" id="tlanguage"> 
      <option value="en" selected><?php esc_html_e('English', 'super-fast-blog-ai'); ?></option>
        <option value="af"><?php esc_html_e('Afrikaans', 'super-fast-blog-ai'); ?></option>
        <option value="ar"><?php esc_html_e('Arabic', 'super-fast-blog-ai'); ?></option>
        <option value="an"><?php esc_html_e('Armenian', 'super-fast-blog-ai'); ?></option>
        <option value="bs"><?php esc_html_e('Bosnian', 'super-fast-blog-ai'); ?></option>
        <option value="bg"><?php esc_html_e('Bulgarian', 'super-fast-blog-ai'); ?></option>
        <option value="zh"><?php esc_html_e('Chinese (Simplified)', 'super-fast-blog-ai'); ?></option>
        <option value="zt"><?php esc_html_e('Chinese (Traditional)', 'super-fast-blog-ai'); ?></option>
        <option value="hr"><?php esc_html_e('Croatian', 'super-fast-blog-ai'); ?></option>
        <option value="cs"><?php esc_html_e('Czech', 'super-fast-blog-ai'); ?></option>
        <option value="da"><?php esc_html_e('Danish', 'super-fast-blog-ai'); ?></option>
        <option value="nl"><?php esc_html_e('Dutch', 'super-fast-blog-ai'); ?></option>
        <option value="et"><?php esc_html_e('Estonian', 'super-fast-blog-ai'); ?></option>
        <option value="fil"><?php esc_html_e('Filipino', 'super-fast-blog-ai'); ?></option>
        <option value="fi"><?php esc_html_e('Finnish', 'super-fast-blog-ai'); ?></option>
        <option value="fr"><?php esc_html_e('French', 'super-fast-blog-ai'); ?></option>
        <option value="de"><?php esc_html_e('German', 'super-fast-blog-ai'); ?></option>
        <option value="el"><?php esc_html_e('Greek', 'super-fast-blog-ai'); ?></option>
        <option value="he"><?php esc_html_e('Hebrew', 'super-fast-blog-ai'); ?></option>
        <option value="hi"><?php esc_html_e('Hindi', 'super-fast-blog-ai'); ?></option>
        <option value="hu"><?php esc_html_e('Hungarian', 'super-fast-blog-ai'); ?></option>
        <option value="id"><?php esc_html_e('Indonesian', 'super-fast-blog-ai'); ?></option>
        <option value="it"><?php esc_html_e('Italian', 'super-fast-blog-ai'); ?></option>
        <option value="ja"><?php esc_html_e('Japanese', 'super-fast-blog-ai'); ?></option>
        <option value="ko"><?php esc_html_e('Korean', 'super-fast-blog-ai'); ?></option>
        <option value="lv"><?php esc_html_e('Latvian', 'super-fast-blog-ai'); ?></option>
        <option value="lt"><?php esc_html_e('Lithuanian', 'super-fast-blog-ai'); ?></option>
        <option value="ms"><?php esc_html_e('Malay', 'super-fast-blog-ai'); ?></option>
        <option value="no"><?php esc_html_e('Norwegian', 'super-fast-blog-ai'); ?></option>
        <option value="fa"><?php esc_html_e('Persian', 'super-fast-blog-ai'); ?></option>
        <option value="pl"><?php esc_html_e('Polish', 'super-fast-blog-ai'); ?></option>
        <option value="pt"><?php esc_html_e('Portuguese', 'super-fast-blog-ai'); ?></option>
        <option value="ro"><?php esc_html_e('Romanian', 'super-fast-blog-ai'); ?></option>
        <option value="ru"><?php esc_html_e('Russian', 'super-fast-blog-ai'); ?></option>
        <option value="sr"><?php esc_html_e('Serbian', 'super-fast-blog-ai'); ?></option>
        <option value="sk"><?php esc_html_e('Slovak', 'super-fast-blog-ai'); ?></option>
        <option value="sl"><?php esc_html_e('Slovenian', 'super-fast-blog-ai'); ?></option>
        <option value="es"><?php esc_html_e('Spanish', 'super-fast-blog-ai'); ?></option>
        <option value="sv"><?php esc_html_e('Swedish', 'super-fast-blog-ai'); ?></option>
        <option value="th"><?php esc_html_e('Thai', 'super-fast-blog-ai'); ?></option>
        <option value="tr"><?php esc_html_e('Turkish', 'super-fast-blog-ai'); ?></option>
        <option value="uk"><?php esc_html_e('Ukrainian', 'super-fast-blog-ai'); ?></option>
        <option value="vi"><?php esc_html_e('Vietnamese', 'super-fast-blog-ai'); ?></option>
      </select>

<?php 
    }
    public function otslf_writtenstyle (){ 
    ?>    
      <p><?php echo esc_html_e('Writing Style','super-fast-blog-ai');?><span class="redmark">*</span></p> 
      <select name="twstyle" id="twstyle"> 
      <option value="infor" selected><?php esc_html_e('Informative', 'super-fast-blog-ai'); ?></option>
      <option value="acade"><?php esc_html_e('Academic', 'super-fast-blog-ai'); ?></option>
      <option value="analy"><?php esc_html_e('Analytical', 'super-fast-blog-ai'); ?></option>
      <option value="anect"><?php esc_html_e('Anecdotal', 'super-fast-blog-ai'); ?></option>
      <option value="argum"><?php esc_html_e('Argumentative', 'super-fast-blog-ai'); ?></option>
      <option value="artic"><?php esc_html_e('Articulate', 'super-fast-blog-ai'); ?></option>
      <option value="biogr"><?php esc_html_e('Biographical', 'super-fast-blog-ai'); ?></option>
      <option value="blog"><?php esc_html_e('Blog', 'super-fast-blog-ai'); ?></option>
      <option value="casua"><?php esc_html_e('Casual', 'super-fast-blog-ai'); ?></option>
      <option value="collo"><?php esc_html_e('Colloquial', 'super-fast-blog-ai'); ?></option>
      <option value="compa"><?php esc_html_e('Comparative', 'super-fast-blog-ai'); ?></option>
      <option value="conci"><?php esc_html_e('Concise', 'super-fast-blog-ai'); ?></option>
      <option value="creat"><?php esc_html_e('Creative', 'super-fast-blog-ai'); ?></option>
      <option value="criti"><?php esc_html_e('Critical', 'super-fast-blog-ai'); ?></option>
      <option value="descr"><?php esc_html_e('Descriptive', 'super-fast-blog-ai'); ?></option>
      <option value="detai"><?php esc_html_e('Detailed', 'super-fast-blog-ai'); ?></option>
      <option value="dialo"><?php esc_html_e('Dialogue', 'super-fast-blog-ai'); ?></option>
      <option value="direct"><?php esc_html_e('Direct', 'super-fast-blog-ai'); ?></option>
      <option value="drama"><?php esc_html_e('Dramatic', 'super-fast-blog-ai'); ?></option>
      <option value="evalu"><?php esc_html_e('Evaluative', 'super-fast-blog-ai'); ?></option>
      <option value="emoti"><?php esc_html_e('Emotional', 'super-fast-blog-ai'); ?></option>
      <option value="expos"><?php esc_html_e('Expository', 'super-fast-blog-ai'); ?></option>
      <option value="ficti"><?php esc_html_e('Fiction', 'super-fast-blog-ai'); ?></option>
      <option value="histo"><?php esc_html_e('Historical', 'super-fast-blog-ai'); ?></option>
      <option value="journ"><?php esc_html_e('Journalistic', 'super-fast-blog-ai'); ?></option>
      <option value="lette"><?php esc_html_e('Letter', 'super-fast-blog-ai'); ?></option>
      <option value="lyric"><?php esc_html_e('Lyrical', 'super-fast-blog-ai'); ?></option>
      <option value="metaph"><?php esc_html_e('Metaphorical', 'super-fast-blog-ai'); ?></option>
      <option value="monol"><?php esc_html_e('Monologue', 'super-fast-blog-ai'); ?></option>
      <option value="narra"><?php esc_html_e('Narrative', 'super-fast-blog-ai'); ?></option>
      <option value="news"><?php esc_html_e('News', 'super-fast-blog-ai'); ?></option>
      <option value="objec"><?php esc_html_e('Objective', 'super-fast-blog-ai'); ?></option>
      <option value="pasto"><?php esc_html_e('Pastoral', 'super-fast-blog-ai'); ?></option>
      <option value="perso"><?php esc_html_e('Personal', 'super-fast-blog-ai'); ?></option>
      <option value="persu"><?php esc_html_e('Persuasive', 'super-fast-blog-ai'); ?></option>
      <option value="poeti"><?php esc_html_e('Poetic', 'super-fast-blog-ai'); ?></option>
      <option value="refle"><?php esc_html_e('Reflective', 'super-fast-blog-ai'); ?></option>
      <option value="rheto"><?php esc_html_e('Rhetorical', 'super-fast-blog-ai'); ?></option>
      <option value="satir"><?php esc_html_e('Satirical', 'super-fast-blog-ai'); ?></option>
      <option value="senso"><?php esc_html_e('Sensory', 'super-fast-blog-ai'); ?></option>
      <option value="simpl"><?php esc_html_e('Simple', 'super-fast-blog-ai'); ?></option>
      <option value="techn"><?php esc_html_e('Technical', 'super-fast-blog-ai'); ?></option>
      <option value="theore"><?php esc_html_e('Theoretical', 'super-fast-blog-ai'); ?></option>
      <option value="vivid"><?php esc_html_e('Vivid', 'super-fast-blog-ai'); ?></option>
      <option value="busin"><?php esc_html_e('Business', 'super-fast-blog-ai'); ?></option>
      <option value="repor"><?php esc_html_e('Report', 'super-fast-blog-ai'); ?></option>
      <option value="resea"><?php esc_html_e('Research', 'super-fast-blog-ai'); ?></option>
      </select>
    <?php    
    
    }   
    public function otslf_writone(){ 
        ?>    
      <p><?php echo esc_html_e('Writing Tone','super-fast-blog-ai')?> <span class="redmark"><?php echo esc_html_e('*','super-fast-blog-ai')?></span></p> 
      <select name="writone" id="writone"> 
      <option value="formal" selected><?php esc_html_e('Formal', 'super-fast-blog-ai'); ?></option>
      <option value="asser"><?php esc_html_e('Assertive', 'super-fast-blog-ai'); ?></option>
      <option value="authoritative"><?php esc_html_e('Authoritative', 'super-fast-blog-ai'); ?></option>
      <option value="cheer"><?php esc_html_e('Cheerful', 'super-fast-blog-ai'); ?></option>
      <option value="confident"><?php esc_html_e('Confident', 'super-fast-blog-ai'); ?></option>
      <option value="conve"><?php esc_html_e('Conversational', 'super-fast-blog-ai'); ?></option>
      <option value="factual"><?php esc_html_e('Factual', 'super-fast-blog-ai'); ?></option>
      <option value="friendly"><?php esc_html_e('Friendly', 'super-fast-blog-ai'); ?></option>
      <option value="humor"><?php esc_html_e('Humorous', 'super-fast-blog-ai'); ?></option>
      <option value="informal"><?php esc_html_e('Informal', 'super-fast-blog-ai'); ?></option>
      <option value="inspi"><?php esc_html_e('Inspirational', 'super-fast-blog-ai'); ?></option>
      <option value="neutr"><?php esc_html_e('Neutral', 'super-fast-blog-ai'); ?></option>
      <option value="nostalgic"><?php esc_html_e('Nostalgic', 'super-fast-blog-ai'); ?></option>
      <option value="polite"><?php esc_html_e('Polite', 'super-fast-blog-ai'); ?></option>
      <option value="profe"><?php esc_html_e('Professional', 'super-fast-blog-ai'); ?></option>
      <option value="romantic"><?php esc_html_e('Romantic', 'super-fast-blog-ai'); ?></option>
      <option value="sarca"><?php esc_html_e('Sarcastic', 'super-fast-blog-ai'); ?></option>
      <option value="scien"><?php esc_html_e('Scientific', 'super-fast-blog-ai'); ?></option>
      <option value="sensit"><?php esc_html_e('Sensitive', 'super-fast-blog-ai'); ?></option>
      <option value="serious"><?php esc_html_e('Serious', 'super-fast-blog-ai'); ?></option>
      <option value="sincere"><?php esc_html_e('Sincere', 'super-fast-blog-ai'); ?></option>
      <option value="skept"><?php esc_html_e('Skeptical', 'super-fast-blog-ai'); ?></option>
      <option value="suspenseful"><?php esc_html_e('Suspenseful', 'super-fast-blog-ai'); ?></option>
      <option value="sympathetic"><?php esc_html_e('Sympathetic', 'super-fast-blog-ai'); ?></option>
      <option value="curio"><?php esc_html_e('Curious', 'super-fast-blog-ai'); ?></option>
      <option value="disap"><?php esc_html_e('Disappointed', 'super-fast-blog-ai'); ?></option>
      <option value="encou"><?php esc_html_e('Encouraging', 'super-fast-blog-ai'); ?></option>
      <option value="optim"><?php esc_html_e('Optimistic', 'super-fast-blog-ai'); ?></option>
      <option value="surpr"><?php esc_html_e('Surprised', 'super-fast-blog-ai'); ?></option>
      <option value="worry"><?php esc_html_e('Worried', 'super-fast-blog-ai'); ?></option>       
      </select>    
    <?php 
    }
    public function otslf_titlevariant(){ 
     ?>    
      <p><?php echo esc_html_e('Title Variations','super-fast-blog-ai')?></p>
      <select name="wvariation" id="wvariation"> 
        <option value="2" selected><?php esc_html_e('2', 'super-fast-blog-ai'); ?></option>
        <option value="5"><?php esc_html_e('5', 'super-fast-blog-ai'); ?></option>
        <option value="8"><?php esc_html_e('8', 'super-fast-blog-ai'); ?></option>
        <option value="10"><?php esc_html_e('10', 'super-fast-blog-ai'); ?></option>
        <option value="12"><?php esc_html_e('12', 'super-fast-blog-ai'); ?></option>
        <option value="15"><?php esc_html_e('15', 'super-fast-blog-ai'); ?></option>
        <option value="20"><?php esc_html_e('20', 'super-fast-blog-ai'); ?></option>
      </select>
    <?php
    }
}
