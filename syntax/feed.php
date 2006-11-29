<?php
/**
 * Feed Plugin: generates a link to the feed of a given tag
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_tag_feed extends DokuWiki_Syntax_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-11-09',
      'name'   => 'Tag Plugin (feed component)',
      'desc'   => 'Generates a link to the feed of a given tag',
      'url'    => 'http://wiki.splitbrain.org/plugin:tag',
    );
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 308; }
  function connectTo($mode) { $this->Lexer->addSpecialPattern('\{\{tagfeed>.+?\}\}',$mode,'plugin_tag_feed'); }

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    global $ID;
    
    $match = substr($match, 10, -2); // strip {{tagfeed> from start and }} from end
    list($tag, $title) = explode('|', $match, 2);
    
    if (!$tag) $tag = noNS($ID);

    return array(trim($tag), trim($title));
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data){
    $tag   = $data[0];
    $title = ($data[1] ? $data[1] : ucwords($tag));
  
    if($mode == 'xhtml'){
      $url   = DOKU_BASE.'lib/plugins/tag/feed.php?tag='.cleanID($tag);
      $title = $renderer->_xmlEntities($title);
      
      $renderer->doc .= '<a href="'.$url.'" class="feed" rel="nofollow"'.
        ' type="application/rss+xml" title="'.$title.'">'.$title.'</a>';
                
      return true;
    
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      if ($renderer->capture) $renderer->doc .= $title;
      
      return true;
    }
    return false;
  }
        
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
