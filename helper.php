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
  
  var $idx_dir    = '';      // directory for index files
  var $page_idx   = array(); // array of existing pages
  var $tag_idx    = array(); // array of tags and index in which pages they are found
        
  /**
   * Constructor gets default preferences and language strings
   */
  function helper_plugin_tag(){
    global $ID, $conf;
    
    $this->namespace = $this->getConf('namespace');
    if (!$this->namespace) $this->namespace = getNS($ID);
        
    // determine where index files are saved
    if (@file_exists($conf['indexdir'].'/page.idx')){ // new word length based index
      $this->idx_dir = $conf['indexdir'];
      if (!@file_exists($this->idx_dir.'/tag.idx')) $this->_importTagIndex();
    } else {                                          // old index
      $this->idx_dir = $conf['cachedir'];
      if (!@file_exists($this->idx_dir.'/tag.idx')) $this->_generateTagIndex();
    }
    
    // load page and tag index
    $this->page_idx = @file($this->idx_dir.'/page.idx');
    $tag_index      = @file($this->idx_dir.'/tag.idx');
    if (is_array($tag_index)){
      foreach ($tag_index as $idx_line){
        list($key, $value) = explode(' ', $idx_line, 2);
        $this->tag_idx[$key] = explode(':', trim($value));
      }
    }
  }

  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2007-08-03',
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
    $result[] = array(
      'name'   => 'tagRefine',
      'desc'   => 'refines an array of pages with tags',
      'params' => array(
        'pages to refine' => 'array',
        'refinement tags' => 'string'),
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
    if (empty($tags) || ($tags[0] == '')) return '';
    
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
        
    return implode(', ', $links);
  }
  
  /**
   * Returns a list of pages with a certain tag; very similar to ft_backlinks()
   *
   * @author  Esther Brunner <wikidesign@gmail.com>
   */
  function getTopic($ns = '', $num = NULL, $tag = ''){
    global $conf;
    
    if (!$tag) $tag = $_REQUEST['tag'];
    $tag = explode(' ', utf8_strtolower($this->_applyMacro($tag)));
    $result = array();
    
    $docs = $this->_tagIndexLookup($tag);
    $docs = array_filter($docs, 'isVisiblePage'); // discard hidden pages
    if (!count($docs)) return $result;
  
    // check metadata for matching subject
    foreach ($docs as $match){
    
      // filter by namespace
      if ($ns && (strpos(':'.getNS($match), ':'.$ns) !== 0)) continue;
      
      // check ACL permission; if okay, then add the page
      $perm = auth_quickaclcheck($match);
      if ($perm < AUTH_READ) continue;
          
      // get metadata
      $meta = array();
      $meta = p_get_metadata($match);
      
      // skip drafts unless for users with create priviledge
      $draft = ($meta['type'] == 'draft');
      if ($draft && ($perm < AUTH_CREATE)) continue;
      
      $title = $meta['title'];
      $tags  = $meta['subject'];
      if (!is_array($tags)) $tags = explode(' ', $tags);
      $taglinks = $this->tagLinks($tags);
      
      // determine the sort key
      if ($conf['useheading']) $key = $this->_uniqueKey($title, $result);
      else $key = $match; // id is always unique
      
      // does it match?
      foreach ($tags as $word){
        if (in_array(utf8_strtolower($word), $tag)){
          $result[$key] = array(
            'id'     => $match,
            'title'  => $title,
            'date'   => $meta['date']['created'],
            'user'   => $meta['creator'],
            'desc'   => $meta['description']['abstract'],
            'cat'    => $tags[0],
            'tags'   => $taglinks,
            'perm'   => $perm,
            'exists' => true,
            'draft'  => $draft,
          );
          break;
        }
      }
    }        
    ksort($result);
    return $result;
  }
  
  /**
   * Refine found pages with tags (+tag: AND, -tag: (AND) NOT)
   */
  function tagRefine($pages, $refine){
    if (!is_array($pages)) return $pages; // wrong data type
    $tags = explode(' ', $this->_applyMacro($refine));
    foreach ($tags as $tag){
      if (!(($tag{0} == '+') || ($tag{0} == '-'))) continue;
      $cleaned_tag = substr($tag, 1);
      $tagpages = $this->_numToID($this->tag_idx[$cleaned_tag]);
      $and = ($tag{0} == '+');
      foreach ($pages as $key => $page){
        $cond = in_array($page['id'], $tagpages);
        if ($and) $cond = (!$cond);
        if ($cond) unset($pages[$key]);
      }
    }
    return $pages;
  }
  
  /**
   * Update tag index
   */
  function _updateTagIndex($id, $tags){
    global $ID, $INFO;
    
    if (!is_array($tags) || empty($tags)) return false;
    $changed = false;
  
    // get page id (this is the linenumber in page.idx)
    $pid = array_search("$id\n", $this->page_idx);
    if (!is_int($pid)){
      $this->page_idx[] = "$id\n";
      $pid = count($this->page_idx) - 1;
      // page was new - write back
      $this->_saveIndex('page');
    }
    
    // clean array first
    $c = count($tags);
    for ($i = 0; $i <= $c; $i++){
      $tags[$i] = utf8_strtolower($tags[$i]);
    }
    
    // clear no longer used tags
    if ($ID == $id){
      $oldtags = $INFO['meta']['subject'];
      if (!is_array($oldtags)) $oldtags = explode(' ', $oldtags);
      foreach ($oldtags as $oldtag){
        if (!$oldtag) continue;                 // skip empty tags
        $oldtag = utf8_strtolower($oldtag);
        if (in_array($oldtag, $tags)) continue; // tag is still there
        $this->tag_idx[$oldtag] = array_diff($this->tag_idx[$oldtag], array($pid));
        $changed = true;
      }
    }
        
    // fill tag in
    foreach ($tags as $tag){
      if (!$tag) continue; // skip empty tags
      if (!is_array($this->tag_idx[$tag])) $this->tag_idx[$tag] = array();
      if (!in_array($pid, $this->tag_idx[$tag])){
        $this->tag_idx[$tag][] = $pid;
        $changed = true;
      }
    }
        
    // save tag index
    if ($changed) return $this->_saveIndex('tag');
    else return true;
  }
  
  /**
   * Save tag or page index
   */
  function _saveIndex($idx = 'tag'){
    $fh = fopen($this->idx_dir.'/'.$idx.'.idx', 'w');
    if (!$fh) return false;
    if ($idx == 'page'){
      fwrite($fh, join('', $this->page_idx));
    } else {
      $tag_index = array();
      foreach ($this->tag_idx as $key => $value){
        $tag_index[] = $key.' '.join(':', $value)."\n";
      }
      fwrite($fh, join('', $tag_index));
    }
    fclose($fh);
    return true;
  }
  
  /**
   * Import old creation date index
   */
  function _importTagIndex(){
    global $conf;
    
    $old = $conf['cachedir'].'/tag.idx';
    $new = $conf['indexdir'].'/tag.idx';
    
    if (!@file_exists($old)) return $this->_generateTagIndex();
        
    if (@copy($old, $new)){
      @unlink($old);
      return true;
    }
    return false;
  }
  
  /**
   * Generates the tag index
   */
  function _generateTagIndex(){
    global $conf;
    
    require_once (DOKU_INC.'inc/search.php');
    
    $pages = array();
    search($pages, $conf['datadir'], 'search_allpages', array());
    foreach ($pages as $page){
      $tags = p_get_metadata($page['id'], 'subject');
      if (!is_array($tags)) $tags = explode(' ', $tags);
      $this->_updateTagIndex($page['id'], $tags);
    }
    return true;
  }
  
  /**
   * Tag index lookup
   */
  function _tagIndexLookup($tags){
    $result = array(); // array of line numbers in the page index
    
    // get the line numbers in page index
    foreach ($tags as $tag){
      if ($tag{0} == '+'){       // AND: add only if in both arrays
        $tag = substr($tag, 1);
        $result = array_intersect($result, $this->tag_idx[$tag]);
      } elseif ($tag{0} == '-'){ // NOT: remove array from docs
        $tag = substr($tag, 1);
        $result = array_diff($result, $this->tag_idx[$tag]);
      } else {                   // OR: add array to docs
        $result = array_unique(array_merge($result, $this->tag_idx[$tag]));
      }
    }
    
    // now convert to page IDs and return
    return $this->_numToID($result);
  }
  
  /**
   * Converts an array of page numbers to IDs
   */
  function _numToID($nums){
    if (is_array($nums)){
      $docs = array();
      foreach ($nums as $num){
        $docs[] = trim($this->page_idx[$num]);
      }
      return $docs;
    } else {
      return trim($this->page_idx[$nums]);
    }
  }
  
  /**
   * Makes user or date dependent topic lists possible
   */
  function _applyMacro($id){
    global $INFO;
    
    list($group, $rest) = explode(',', $INFO['username']['grps']);
 
    $replace = array( 
      '@USER@'  => cleanID($_SERVER['REMOTE_USER']), 
      '@NAME@'  => cleanID($INFO['userinfo']['name']),
      '@GROUP@' => cleanID($group),
      '@YEAR@'  => date('Y'), 
      '@MONTH@' => date('m'), 
      '@DAY@'   => date('d'), 
    ); 
    return str_replace(array_keys($replace), array_values($replace), $id); 
  }
  
  /**
   * Recursive function to check whether an array key is unique
   *
   * Simplyfied from the more complex version in the Blog Plugin
   */
  function _uniqueKey($key, &$result, $num = 0){
    $testkey = $key.($num > 0 ? $num : '');
    if (!array_key_exists($testkey, $result)) return $testkey;
    return $this->_uniqueKey($key, $result, $num++);
  }
  
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
