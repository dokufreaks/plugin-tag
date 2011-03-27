<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

require_once(DOKU_INC.'inc/indexer.php');

class helper_plugin_tag extends DokuWiki_Plugin {

    var $namespace  = '';      // namespace tag links point to

    var $doc        = '';      // the final output XHTML string
    var $references = array(); // $meta['relation']['references'] data for metadata renderer

    var $sort       = '';      // sort key
    var $idx_dir    = '';      // directory for index files
    var $topic_idx  = array();

    /**
     * Constructor gets default preferences and language strings
     */
    function helper_plugin_tag() {
        global $ID, $conf;

        $this->namespace = $this->getConf('namespace');
        if (!$this->namespace) $this->namespace = getNS($ID);
        $this->sort = $this->getConf('sortkey');

        // determine where index files are saved
        if (@file_exists($conf['indexdir'].'/page.idx')) { // new word length based index
            $this->idx_dir = $conf['indexdir'];
            if (!@file_exists($this->idx_dir.'/topic.idx')) $this->_importTagIndex();
        } else {                                          // old index
            $this->idx_dir = $conf['cachedir'];
            if (!@file_exists($this->idx_dir.'/topic.idx')) $this->_generateTagIndex();
        }

        // load page and tag index
        $this->topic_idx = unserialize(io_readFile($this->idx_dir.'/topic.idx', false));
    }

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN.'tag/VERSION'),
                'name'   => 'Tag Plugin (helper class)',
                'desc'   => 'Functions to return tag links and topic lists',
                'url'    => 'http://www.dokuwiki.org/plugin:tag',
                );
    }

    function getMethods() {
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
    function th() {
        return $this->getLang('tags');
    }

    /**
     * Returns the cell data for the Pagelist Plugin
     */
    function td($id) {
        $subject = p_get_metadata($id, 'subject');
        return $this->tagLinks($subject);
    }

    /**
     * Returns the links for given tags
     */
    function tagLinks($tags) {
        global $conf;

        if (!is_array($tags)) $tags = explode(' ', $tags);
        if (empty($tags) || ($tags[0] == '')) return '';

        foreach ($tags as $tag) {
            $svtag = $tag;
            $title = str_replace('_', ' ', noNS($tag));
            resolve_pageid($this->namespace, $tag, $exists); // resolve shortcuts
            if ($exists) {
                $class = 'wikilink1';
                $url   = wl($tag);
                if ($conf['useheading']) {
                    // important: set sendond param to false to prevent recursion!
                    $heading = p_get_first_heading($tag, false);
                    if ($heading) $title = $heading;
                }
            } else {
                $class = 'wikilink1';
                $url   = wl($tag, array('do'=>'showtag', 'tag'=>$svtag));
            }
            $links[] = '<a href="'.$url.'" class="'.$class.'" title="'.hsc($tag).
                '" rel="tag">'.hsc($title).'</a>';
            $this->references[$tag] = $exists;
        }

        return implode(','.DOKU_LF.DOKU_TAB, $links);
    }

    /**
     * Returns a list of pages with a certain tag; very similar to ft_backlinks()
     *
     * @author  Esther Brunner <wikidesign@gmail.com>
     */
    function getTopic($ns = '', $num = NULL, $tag = '') {
        if (!$tag) $tag = $_REQUEST['tag'];
        $tag = $this->_parseTagList($tag, true);
        $result = array();

        $docs = $this->_tagIndexLookup($tag);
        $docs = array_filter($docs, 'isVisiblePage'); // discard hidden pages
        if (!count($docs)) return $result;

        // check metadata for matching subject
        foreach ($docs as $match) {

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
            $date  = ($this->sort == 'mdate' ? $meta['date']['modified'] : $meta['date']['created'] );
            if (!is_array($tags)) $tags = explode(' ', $tags);
            $taglinks = $this->tagLinks($tags);

            // determine the sort key
            if ($this->sort == 'id') $key = $match;
            elseif ($this->sort == 'pagename') $key = noNS($match);
            elseif ($this->sort == 'title') $key = $title;
            else $key = $date;

            // make sure that the key is unique
            $key = $this->_uniqueKey($key, $result);

            // is the page really tagged with one of our tags?
            foreach ($tags as $word) {
                if (in_array(utf8_strtolower($word), $tag)) {
                    $result[$key] = array(
                            'id'     => $match,
                            'title'  => $title,
                            'date'   => $date,
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

            // if not, the tag index was out of date: refresh it!
            if (!is_array($result[$key]))
                $this->_refreshTagIndex($match, $tag);
        }        

        // finally sort by sort key
        if ($this->getConf('sortorder') == 'ascending') ksort($result);
        else krsort($result);

        return $result;
    }

    /**
     * Refine found pages with tags (+tag: AND, -tag: (AND) NOT)
     */
    function tagRefine($pages, $refine) {
        if (!is_array($pages)) return $pages; // wrong data type
        $tags = $this->_parseTagList($refine, true);
        foreach ($tags as $tag) {
            if (!(($tag{0} == '+') || ($tag{0} == '-'))) continue;
            $cleaned_tag = substr($tag, 1);
            $tagpages = $this->topic_idx[$cleaned_tag];
            $and = ($tag{0} == '+');
            foreach ($pages as $key => $page) {
                $cond = in_array($page['id'], $tagpages);
                if ($and) $cond = (!$cond);
                if ($cond) unset($pages[$key]);
            }
        }
        return $pages;
   }
   
   /**
    * Get count of occurences for a list of tags
    * @params tags array of tags 
    */
   function tagOccurences($tags) {
        $otags = array();
        
        if($tags[0] == '+') $tags = array_keys($this->topic_idx);    // all tags should be displayed
        
        foreach($tags as $key => $tag) {
            $count = count($this->topic_idx[$tag]);
            $otags[$tag] = $count; 
        }
        
        return $otags;
    }

    /**
     * Refresh tag index
     * Deletes all tags of page id which are not defined in the page's metadata
     * as well.
     * 
     * @param id the page id
     * @param tags tags as defined in the index to double-check
     */
    function _refreshTagIndex($id, $tags) {
        if (!is_array($tags) || empty($tags)) return false;
        $changed = false;

        // clean array first
        $c = count($tags);
        for ($i = 0; $i <= $c; $i++) {
            $tags[$i] = utf8_strtolower($tags[$i]);
        }

        // get actual tags as saved in metadata
        $meta = p_get_metadata($id);
        $metatags = $meta['subject'];
        if (!is_array($metatags)) $metatags = array();

        foreach ($tags as $tag) {
            if (!$tag) continue;                     // skip empty tags
            if (in_array($tag, $metatags)) continue; // tag is still there
            if (is_array($this->topic_idx[$tag])) {
                $this->topic_idx[$tag] = array_diff($this->topic_idx[$tag], array($id));
            } else {
                $this->topic_idx[$tag] = array($id);
            }
            $changed = true;
        }

        if ($changed) return $this->_saveIndex();
        else return true;
    }

    /**
     * Update tag index
     */
    function _updateTagIndex($id, $tags) {
        global $ID, $INFO;

        if (!is_array($tags) || empty($tags)) return false;
        $changed = false;

        // clean array first
        $c = count($tags);
        for ($i = 0; $i <= $c; $i++) {
            $tags[$i] = utf8_strtolower($tags[$i]);
        }

        // clear no longer used tags
        if ($ID == $id) {
            $oldtags = $INFO['meta']['subject'];
            if (!is_array($oldtags)) $oldtags = explode(' ', $oldtags);
            foreach ($oldtags as $oldtag) {
                if (!$oldtag) continue;                 // skip empty tags
                $oldtag = utf8_strtolower($oldtag);
                if (in_array($oldtag, $tags)) continue; // tag is still there
                if (!is_array($this->topic_idx[$oldtag]))
                    $this->topic_idx[$oldtag] = array();
                $this->topic_idx[$oldtag] = array_diff($this->topic_idx[$oldtag], array($id));
                $changed = true;
            }
        }

        // fill tag in
        foreach ($tags as $tag) {
            if (!$tag) continue; // skip empty tags
            if (!is_array($this->topic_idx[$tag])) $this->topic_idx[$tag] = array();
            if (!in_array($id, $this->topic_idx[$tag])) {
                $this->topic_idx[$tag][] = $id;
                $changed = true;
            }
        }

        // save tag index
        if ($changed) return $this->_saveIndex();
        else return true;
    }

    /**
     * Save tag or page index
     */
    function _saveIndex() {
        return io_saveFile($this->idx_dir.'/topic.idx', serialize($this->topic_idx));
    }

    /**
     * Import old creation date index
     */
    function _importTagIndex() {
        global $conf;

        $old = $conf['indexdir'].'/tag.idx';
        $new = $conf['indexdir'].'/topic.idx';
        
        if (!@file_exists($old)) return $this->_generateTagIndex();
        
        $tag_index = @file($this->idx_dir.'/tag.idx');
        $topic_index = array();
        
        if (is_array($tag_index)) {
            foreach ($tag_index as $idx_line) {
                list($key, $value) = explode(' ', $idx_line, 2);
                $topic_index[$key] = $this->_numToID(explode(':', trim($value)));
            }
            return io_saveFile($new, serialize($topic_index));
        }
        
        return false;
    }

    /**
     * Generates the tag index
     */
    function _generateTagIndex() {
        global $conf;

        require_once (DOKU_INC.'inc/search.php');

        $pages = array();
        search($pages, $conf['datadir'], 'search_allpages', array());
        foreach ($pages as $page) {
            $this->_generateTagData($page);
        }
        return true;
    }

    /**
     * Generates the tag data for a single page.
     */
    function _generateTagData($page) {
        $tags = p_get_metadata($page['id'], 'subject');
        if (!is_array($tags)) $tags = explode(' ', $tags);
        $this->_updateTagIndex($page['id'], $tags);
    }

    /**
     * Tag index lookup
     */
    function _tagIndexLookup($tags) {
        $result = array(); // array of line numbers in the page index

        // get the line numbers in page index
        foreach ($tags as $tag) {
            if (($tag{0} == '+') || ($tag{0} == '-')) 
                $t = substr($tag, 1);
            else
                $t = $tag;
            if (!is_array($this->topic_idx[$t])) $this->topic_idx[$t] = array();

            if ($tag{0} == '+') {       // AND: add only if in both arrays
                $result = array_intersect($result, $this->topic_idx[$t]);
            } elseif ($tag{0} == '-') { // NOT: remove array from docs
                $result = array_diff($result, $this->topic_idx[$t]);
            } else {                   // OR: add array to docs
                $result = array_unique(array_merge($result, $this->topic_idx[$t]));
            }
        }

        // now convert to page IDs and return
        return $result;
    }

    /**
     * Converts an array of page numbers to IDs
     */
    function _numToID($nums) {
        $page_index = idx_getIndex('page', '');
        if (is_array($nums)) {
            $docs = array();
            foreach ($nums as $num) {
                $docs[] = trim($page_index[$num]);
            }
            return $docs;
        } else {
            return trim($page_index[$nums]);
        }
    }

    /**
     * Splits a string into an array of tags
     */
    function _parseTagList($tags, $clean = false) {

        // support for "quoted phrase tags"
        if (preg_match_all('#".*?"#', $tags, $matches)) {
            foreach ($matches[0] as $match) {
                $replace = str_replace(' ', '_', substr($match, 1, -1));
                $tags = str_replace($match, $replace, $tags);
            }
        }

        if ($clean) $tags = utf8_strtolower($this->_applyMacro($tags));
        return explode(' ', $tags);
    }

    /**
     * Makes user or date dependent topic lists possible
     */
    function _applyMacro($id) {
        global $INFO, $auth;

        $user     = $_SERVER['REMOTE_USER'];
        // .htaccess auth doesn't provide the auth object
        if($auth) {
            $userdata = $auth->getUserData($user);
            $group    = $userdata['grps'][0];
        }

        $replace = array( 
                '@USER@'  => cleanID($user), 
                '@NAME@'  => cleanID($INFO['userinfo']['name']),
                '@GROUP@' => cleanID($group),
                '@YEAR@'  => date('Y'), 
                '@MONTH@' => date('m'), 
                '@DAY@'   => date('d'), 
                ); 
        return str_replace(array_keys($replace), array_values($replace), $id); 
    }

    /**
     * Non-recursive function to check whether an array key is unique
     *
     * @author    Esther Brunner <wikidesign@gmail.com>
     * @author    Ilya S. Lebedev <ilya@lebedev.net>
     */
    function _uniqueKey($key, &$result) {

        // increase numeric keys by one
        if (is_numeric($key)) {
            while (array_key_exists($key, $result)) $key++;
            return $key;

            // append a number to literal keys
        } else {
            $num     = 0;
            $testkey = $key;
            while (array_key_exists($testkey, $result)) {
                $testkey = $key.$num;
                $num++;
            }
            return $testkey;
        }
    }

    function _notEmpty($val) {
        return !empty($val);
    }

}
// vim:ts=4:sw=4:et:  
