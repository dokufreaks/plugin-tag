<?php
/**
 * Tag Plugin, topic component: displays links to all wiki pages with a certain tag
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_tag_topic extends DokuWiki_Syntax_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-12-14',
      'name'   => 'Tag Plugin (topic component)',
      'desc'   => 'Displays a list of wiki pages with a given category tag',
      'url'    => 'http://www.wikidesign.ch/en/plugin/tag/start',
    );
  }

  function getType(){ return 'substition'; }
  function getPType(){ return 'block'; }
  function getSort(){ return 306; }
  
  function connectTo($mode){
    $this->Lexer->addSpecialPattern('\{\{topic>.+?\}\}',$mode,'plugin_tag_topic');
  }

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match, 8, -2); // strip {{topic> from start and }} from end
    list($ns, $tag) = explode('?', $match);
    if (!$tag){
      $tag = $ns;
      $ns   = '';
    }
    return array(cleanID($ns), trim($tag));
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data){
    global $ID;
    
    list($ns, $tag) = $data;
    
    if (($ns == '*') || ($ns == ':')) $ns = '';
    elseif ($ns == '.') $ns = getNS($ID);
    
    if ($my =& plugin_load('helper', 'tag')) $pages = $my->getTopic($ns, '', $tag);
    if (!$pages) return true; // nothing to display
    
    if ($mode == 'xhtml'){
      
      // prevent caching to ensure content is always fresh
      $renderer->info['cache'] = false;
      
      // let Pagelist Plugin do the work for us
      if (!$pagelist = plugin_load('helper', 'pagelist')){
        msg('The Pagelist Plugin must be installed for topic lists.', -1);
        return false;
      }
      $pagelist->startList();
      foreach ($pages as $page){
        $pagelist->addPage($page);
      }
      $renderer->doc .= $pagelist->finishList();      
      return true;
      
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      foreach ($pages as $page){
        $renderer->meta['relation']['references'][$page['id']] = true;
      }
      
      return true;
    }
    return false;
  }
        
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
