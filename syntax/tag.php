<?php
/**
 * Tag Plugin: displays list of keywords with links to categories this page
 * belongs to. The links are marked as tags for Technorati and other services
 * using tagging.
 *
 * Usage: {{tag>category tags space separated}}
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
class syntax_plugin_tag_tag extends DokuWiki_Syntax_Plugin {

  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-12-09',
      'name'   => 'Tag Plugin (tag component)',
      'desc'   => 'Displays links to categories the page belongs to',
      'url'    => 'http://www.wikidesign.ch/en/plugin/tag/start',
    );
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 305; }
  function getPType(){ return 'block';}
  
  function connectTo($mode) {
    $this->Lexer->addSpecialPattern('\{\{tag>.+?\}\}', $mode, 'plugin_tag_tag');
  }
  
  function handle($match, $state, $pos, &$handler){
    return explode(' ', substr($match, 6, -2)); // strip markup and split tags
  }      
 
  function render($mode, &$renderer, $data){
    if (!$helper = plugin_load('helper', 'tag')) return false;
    $tags = $helper->tagLinks($data);
    
    // XHTML output
    if ($mode == 'xhtml'){
      $renderer->doc .= '<div class="tags">'.DOKU_LF.$tags.DOKU_LF.'</div>'.DOKU_LF;
      return true;
      
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      if ($renderer->capture) $renderer->doc .= DOKU_LF.strip_tags($tags).DOKU_LF;
      foreach ($helper->references as $ref => $exists){
        $renderer->meta['relation']['references'][$ref] = $exists;
      }
      $renderer->meta['subject'] = $data;
      return true;
    }
    return false;
  }
   
}
 
//Setup VIM: ex: et ts=4 enc=utf-8 :
