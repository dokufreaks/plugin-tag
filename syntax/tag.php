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
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_tag_tag extends DokuWiki_Syntax_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-08-17',
      'name'   => 'Tag Plugin (tag component)',
      'desc'   => 'Displays a list of keywords with links to categories this page belongs to. '.
                  'The links are marked as tags for Technorati and other services using tagging.',
      'url'    => 'http://wiki.splitbrain.org/plugin:tag',
    );
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 305; }
  function getPType(){ return 'block';}
  function connectTo($mode) { $this->Lexer->addSpecialPattern('\{\{tag>.+?\}\}', $mode, 'plugin_tag_tag'); }
  
  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match, 6, -2);  // strip markup
    $tags = explode(' ', $match);    // split tags
    return $tags;
  }      
 
  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    global $ID;
    global $conf;
    
    $defaultNS = $this->getConf('namespace');
    if (!$defaultNS) $defaultNS = getNS($ID);
    $tags = array();
  
    if ($mode == 'xhtml'){
      $renderer->doc .= '<div class="tags">';
      
      foreach ($data as $tag){
        $title = str_replace('_', ' ', noNS($tag));
        resolve_pageid($defaultNS, $tag, $exists); // resolve shortcuts
        if ($exists){
          $class = 'wikilink1';
          if ($conf['useheading']){
            $heading = p_get_first_heading($tag);
            if ($heading) $title = $heading;
          }
        } else {
          $class = 'wikilink2';
        }
        $tags[] = '<a href="'.wl($tag).'" class="'.$class.
          '" title="'.$renderer->_xmlEntities($tag).'" rel="tag">'.
          $renderer->_xmlEntities($title).'</a>';
      }
      $renderer->doc .= implode(', ', $tags).'</div>';
      return true;
      
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      foreach ($data as $tag){
        $title = str_replace('_', ' ', noNS($tag));
        resolve_pageid($defaultNS, $tag, $exists); // resolve shortcuts
        if ($exists && $conf['useheading']){
          $heading = p_get_first_heading($tag);
          if ($heading) $title = $heading;
        }
        $renderer->meta['relation']['references'][$tag] = $exists;
        $tags[] = $title;
      }
      if ($renderer->capture) // add tags to abstract
        $renderer->doc .= DOKU_LF.implode(', ', $tags).DOKU_LF;
      $renderer->meta['subject'] = $data;
      
      return true;
    }
    return false;
  }
   
}
 
//Setup VIM: ex: et ts=4 enc=utf-8 :
