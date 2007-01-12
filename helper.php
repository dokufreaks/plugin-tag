<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_tag extends DokuWiki_Plugin {

  var $namespace  = '';      // namespace tag links point to
  
  var $doc        = '';      // the final output XHTML string
  var $references = array(); // $meta['relation']['references'] data for metadata renderer
        
  /**
   * Constructor gets default preferences and language strings
   */
  function helper_plugin_tag(){
    global $ID;
    
    $this->namespace = $this->getConf('namespace');
    if (!$this->namespace) $this->namespace = getNS($ID);
  }

  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2007-01-12',
      'name'   => 'Tag Plugin (helper class)',
      'desc'   => 'Functions to return tag links and topic lists',
      'url'    => 'http://www.wikidesign/en/plugin/tag/start',
    );
  }
  
  function getMethods(){
    $result = array();
    $result[] = array(
      'name'   => 'th',
      'desc'   => 'returns the header for the tags column for pagelist',
      'return' => array('header' => 'string'),
    );
    $result[] = array(
      'name'   => 'td',
      'desc'   => 'returns the tag links of a given page',
      'params' => array('id' => 'string'),
      'return' => array('links' => 'string'),
    );
    $result[] = array(
      'name'   => 'tagLinks',
      'desc'   => 'generates tag links for given words',
      'params' => array('tags' => 'array'),
      'return' => array('links' => 'string'),
    );
    $result[] = array(
      'name'   => 'getTopic',
      'desc'   => 'returns a list of pages tagged with the given keyword',
      'params' => array(
        'namespace (optional)' => 'string',
        'number (not used)' => 'integer',
        'tag (required)' => 'string'),
      'return' => array('pages' => 'array'),
    );
    return $result;
  }
  
  /**
   * Returns the column header for th Pagelist Plugin
   */
  function th(){
    return $this->getLang('tags');
  }
  
  /**
   * Returns the cell data for the Pagelist Plugin
   */
  function td($id){
    $subject = p_get_metadata($id, 'subject');
    return $this->tagLinks($subject);
  }
    
  /**
   * Returns the links for given tags
   */
  function tagLinks($tags){
    global $conf;
    
    if (!is_array($tags)) $tags = explode(' ', $tags);
    
    foreach ($tags as $tag){
      $title = str_replace('_', ' ', noNS($tag));
      resolve_pageid($this->namespace, $tag, $exists); // resolve shortcuts
      if ($exists){
        $class = 'wikilink1';
        if ($conf['useheading']){
          $heading = p_get_first_heading($tag);
          if ($heading) $title = $heading;
        }
      } else {
        $class = 'wikilink2';
      }
      $links[] = '<a href="'.wl($tag).'" class="'.$class.'" title="'.hsc($tag).
        '" rel="tag">'.hsc($title).'</a>';
      $this->references[$tag] = $exists;
    }
    $this->doc = implode(', ', $links);
    
    return $this->doc;
  }
  
  /**
   * Returns a list of pages with a certain tag; very similar to ft_backlinks()
   *
   * @author  Andreas Gohr <andi@splitbrain.org>
   * @author  Esther Brunner <wikidesign@gmail.com>
   */
  function getTopic($ns = '', $num = NULL, $tag = ''){
    require_once (DOKU_INC.'inc/indexer.php');
    
    if (!$tag) $tag = $_REQUEST['tag'];
    $tag = utf8_strtolower($tag);
    $result = array();
    
    // quick lookup of the pagename
    $sw    = array(); // we don't use stopwords here
    $matches = idx_lookup(idx_tokenizer($tag, $sw));  // tag may contain specials (_ or .)
    $matches = array_values($matches);
    $docs    = array_keys($matches[0]);
    
    $docs  = array_filter($docs, 'isVisiblePage'); // discard hidden pages
    if (!count($docs)) return $result;
  
    // check metadata for matching subject
    foreach ($docs as $match){
    
      // filter by namespace
      if ($ns && (strpos(':'.getNS($match), ':'.$ns) !== 0)) continue;
          
      // get metadata
      $meta = array();
      $meta = p_get_metadata($match);
      $tags = $meta['subject'];
      if (!is_array($tags)) $tags = explode(' ', $tags);
      
      // does it match?
      foreach ($tags as $word){
        if ($tag == utf8_strtolower($word)){
  
          // check ACL permission; if okay, then add the page
          $perm = auth_quickaclcheck($match);
          if ($perm >= AUTH_READ){
            $title = $meta['title'];
            $result[$match] = array(
              'id'     => $match,
              'title'  => $title,
              'date'   => $meta['date']['created'],
              'user'   => $meta['creator'],
              'desc'   => $meta['description']['abstract'],
              'cat'    => $tags[0],
              'tags'   => $this->tagLinks($tags),
              'perm'   => $perm,
              'exists' => true,
            );
          }
          break;
        }
      }
    }        
    ksort($result);
    return $result;
  }
      
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
