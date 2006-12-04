<?php
/**
 * Tag Plugin, topic component: displays links to all wiki pages with a certain category tag
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_tag_topic extends DokuWiki_Syntax_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-12-04',
      'name'   => 'Tag Plugin (topic component)',
      'desc'   => 'Displays a list of wiki pages with a given category tag',
      'url'    => 'http://wiki.splitbrain.org/plugin:tag',
    );
  }

  function getType(){ return 'substition'; }
  function getPType(){ return 'block'; }
  function getSort(){ return 306; }
  function connectTo($mode) { $this->Lexer->addSpecialPattern('\{\{topic>.+?\}\}',$mode,'plugin_tag_topic'); }

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match, 8, -2); // strip {{topic> from start and }} from end
    list($ns, $rest) = explode("?", $match);
    if (!$rest){
      $rest = $ns;
      $ns   = '';
    }
    return array($ns, $rest);
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    global $ID;
    global $conf;
    
    $pages = $this->_topicList($data[0], $data[1]);
    
    if (!count($pages)) return true; // nothing to display
    
    if ($mode == 'xhtml'){
      
      // prevent caching to ensure content is always fresh
      $renderer->info['cache'] = false;
  
      $renderer->doc .= '<table class="topic">';
      foreach ($pages as $page){
        $renderer->doc .= '<tr><td class="page">';
        
        // page title
        $id  = $page['id'];
        $title = $page['title'];
        if (!$title) $title = str_replace('_', ' ', noNS($id));
        $renderer->doc .= $renderer->internallink(':'.$id, $title).'</td>';
        
        // creation date
        if ($this->getConf('topic_showdate')){
          if (!$page['date']) $page['date'] = filectime(wikiFN($id));
          $renderer->doc .= '<td class="date">'.
            date($conf['dformat'], $page['date']).'</td>';
        }
        
        // author
        if ($this->getConf('topic_showuser')){
          if ($page['user']) $renderer->doc .= '<td class="user">'.$page['user'].'</td>';
          else $renderer->doc .= '<td class="user">&nbsp;</td>';
        }
        
        $renderer->doc .= '</tr>';
      }
      $renderer->doc .= '</table>';
      
      return true;
      
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      foreach ($pages as $page){
        $id  = $page['id'];
        $renderer->meta['relation']['references'][$id] = true;
      }
      
      return true;
    }
    return false;
  }
    
  /**
   * returns the category archive list; very similar to ft_backlinks()
   *
   * @author  Andreas Gohr <andi@splitbrain.org>
   * @author  Esther Brunner <esther@kaffeehaus.ch>
   */
  function _topicList($ns, $tag){
    require_once (DOKU_INC.'inc/indexer.php');
    require_once (DOKU_INC.'inc/fulltext.php');
    
    if ($ns == '*') $ns = '';
    $tag = utf8_strtolower($tag);
    $result = array();
    
    // quick lookup of the pagename
    $sw    = array(); // we don't use stopwords here
    $matches = idx_lookup(idx_tokenizer($tag, $sw));  // pagename may contain specials (_ or .)
    $docs  = array_keys(ft_resultCombine(array_values($matches)));
    $docs  = array_filter($docs,'isVisiblePage'); // discard hidden pages
    if (!count($docs)) return $result;

    // check metadata for matching subject
    foreach ($docs as $match){
      
      // filter by namespace
      if (strpos(':'.getNS($match), ':'.$ns) !== 0) continue;
      
      // get metadata
      $meta = array();
      $meta = p_get_metadata($match);
      $tags = $meta['subject'];
      if (!is_array($tags)) $tags = explode(' ', $tags);
      
      // does it match?
      foreach ($tags as $word){
        if ($tag == utf8_strtolower($word)){

          // check ACL permission; if okay, then add the page
          if (auth_quickaclcheck($match) >= AUTH_READ){
            $title = $meta['title'];
            $result[$title] = array(
              'id'    => $match,
              'title' => $title,
              'date'  => $meta['date']['created'],
              'user'  => $meta['creator'],
            );
          }
          break;
        }
      }
    }
    if (!count($result)) return $result;
        
    ksort($result);
    return $result;
  }
        
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
