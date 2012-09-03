<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

/**
 * Helper part of the tag plugin, allows to query and print tags
 */
class helper_plugin_tag extends DokuWiki_Plugin {

    var $namespace  = '';      // namespace tag links point to

    var $sort       = '';      // sort key
    var $topic_idx  = array();

    /**
     * Constructor gets default preferences and language strings
     */
    function helper_plugin_tag() {
        global $ID;

        $this->namespace = $this->getConf('namespace');
        if (!$this->namespace) $this->namespace = getNS($ID);
        $this->sort = $this->getConf('sortkey');
    }

    /**
     * Returns some documentation of the methods provided by this helper part
     *
     * @return array Method description
     */
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
        $result[] = array(
                'name'   => 'tagOccurrences',
                'desc'   => 'returns a list of tags with their number of occurrences',
                'params' => array(
                    'list of tags to get the occurrences for' => 'array',
                    'namespaces to which the search shall be restricted' => 'array',
                    'if all tags shall be returned (then the first parameter is ignored)' => 'boolean',
                    'if the namespaces shall be searched recursively' => 'boolean'),
                'return' => array('tags' => 'array'),
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
        $subject = $this->_getSubjectMetadata($id);
        return $this->tagLinks($subject);
    }

    /**
     * Returns the links for given tags
     *
     * @param array $tags an array of tags
     * @return string HTML link tags
     */
    function tagLinks($tags) {
        if (empty($tags) || ($tags[0] == '')) return '';

        $links = array();
        foreach ($tags as $tag) {
            $links[] = $this->tagLink($tag);
        }

        return implode(','.DOKU_LF.DOKU_TAB, $links);
    }

    /**
     * Returns the link for one given tag
     */
    function tagLink($tag) {
        global $conf;
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
        $link = '<a href="'.$url.'" class="'.$class.'" title="'.hsc($tag).
            '" rel="tag">'.hsc($title).'</a>';
        return $link;
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

        // find the pages using topic.idx
        $pages = $this->_tagIndexLookup($tag);
        if (!count($pages)) return $result;
        
        foreach ($pages as $page) {
            // exclude pages depending on ACL and namespace
            if($this->_notVisible($page, $ns)) continue;
            $tags  = $this->_getSubjectMetadata($page);
            // don't trust index
            if (empty($tags)) continue;
            if (!$this->_checkPageTags($tags, $tag)) continue;

            // get metadata
            $meta = p_get_metadata($page);

            $perm = auth_quickaclcheck($page);

            // skip drafts unless for users with create privilege
            $draft = ($meta['type'] == 'draft');
            if ($draft && ($perm < AUTH_CREATE)) continue;

            $title = $meta['title'];
            $date  = ($this->sort == 'mdate' ? $meta['date']['modified'] : $meta['date']['created'] );
            $taglinks = $this->tagLinks($tags);

            // determine the sort key
            if ($this->sort == 'id') $key = $page;
            elseif ($this->sort == 'pagename') $key = noNS($page);
            elseif ($this->sort == 'title') $key = utf8_strtolower($title);
            else $key = $date;
            // make sure that the key is unique
            $key = $this->_uniqueKey($key, $result);

            $result[$key] = array(
                    'id'     => $page,
                    'title'  => $title,
                    'date'   => $date,
                    'user'   => $meta['creator'],
                    'desc'   => $meta['description']['abstract'],
                    'cat'    => $tags[0],
                    'tags'   => $taglinks,
                    'perm'   => $perm,
                    'exists' => true,
                    'draft'  => $draft, );
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
        $clean_tags = array();
        foreach ($tags as $i => $tag) {
            if (($tag{0} == '+') || ($tag{0} == '-'))
                $clean_tags[$i] = substr($tag, 1);
            else
                $clean_tags[$i] = $tag;
        }

        $indexer = idx_get_indexer();
        $index_pages = $indexer->lookupKey('subject', $clean_tags, array($this, '_tagCompare'));

        foreach ($tags as $tag) {
            if (!(($tag{0} == '+') || ($tag{0} == '-'))) continue;
            $cleaned_tag = substr($tag, 1);
            $tagpages = $index_pages[$cleaned_tag];
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
    * Get count of occurrences for a list of tags
    *
    * @param array $tags array of tags
    * @param array $namespaces array of namespaces where to count the tags
    * @param boolean $allTags boolean if all available tags should be counted
    * @param boolean $recursive boolean if pages in subnamespaces are allowed
    * @return array
    */
   function tagOccurrences($tags, $namespaces = NULL, $allTags = false, $recursive = NULL) {
        // map with trim here in order to remove newlines from tags
        if($allTags) $tags = array_map('trim', idx_getIndex('subject', '_w'));
        $tags = $this->_cleanTagList($tags);
        $otags = array(); //occurrences
        if(!$namespaces || $namespaces[0] == '' || !is_array($namespaces)) $namespaces = NULL; // $namespaces not specified

        $indexer = idx_get_indexer();
        $indexer_pages = $indexer->lookupKey('subject', $tags, array($this, '_tagCompare'));

        $root_allowed = ($namespaces == NULL ? false : in_array('.', $namespaces));
        if ($recursive === NULL)
            $recursive = $this->getConf('list_tags_of_subns');

        foreach ($tags as $tag) {
            if (!isset($indexer_pages[$tag])) continue;

            // just to be sure remove duplicate pages from the list of pages
            $pages = array_unique($indexer_pages[$tag]);

            // don't count hidden pages or pages the user can't access
            // for performance reasons this doesn't take drafts into account
            $pages = array_filter($pages, array($this, '_isVisible'));

            if (empty($pages)) continue;

            if ($namespaces == NULL || ($root_allowed && $recursive)) {
                // count all pages
                $otags[$tag] = count($pages);
            } else if (!$recursive) {
                // filter by exact namespace
                $otags[$tag] = 0;
                foreach ($pages as $page) {
                    $ns = getNS($page);
                    if (($ns == false && $root_allowed) || in_array($ns, $namespaces)) $otags[$tag]++;
                }
            } else { // recursive, no root
                $otags[$tag] = 0;
                foreach ($pages as $page) {
                    foreach ($namespaces as $ns) {
                        if(strpos($page, $ns.':') === 0 ) {
                            $otags[$tag]++ ;
                            break;
                        }
                    }
                }
            }
            // don't return tags without pages
            if ($otags[$tag] == 0) unset($otags[$tag]);
        }
        return $otags;
    }

    /**
     * Get the subject metadata cleaning the result
     *
     * @param string $id the page id
     * @return array
     */
    function _getSubjectMetadata($id){
        $tags = p_get_metadata($id, 'subject');
        if (!is_array($tags)) $tags = explode(' ', $tags);
        return array_unique($tags);
    }

    /**
     * Tag index lookup
     *
     * @param array $tags the tags to filter
     * @return array the matching page ids
     */
    function _tagIndexLookup($tags) {
        $result = array(); // array of page ids

        $clean_tags = array();
        foreach ($tags as $i => $tag) {
            if (($tag{0} == '+') || ($tag{0} == '-'))
                $clean_tags[$i] = substr($tag, 1);
            else
                $clean_tags[$i] = $tag;
        }

        $indexer = idx_get_indexer();
        $pages = $indexer->lookupKey('subject', $clean_tags, array($this, '_tagCompare'));

        foreach ($tags as $i => $tag) {
            $t = $clean_tags[$i];
            if (!is_array($pages[$t])) $pages[$t] = array();

            if ($tag{0} == '+') {       // AND: add only if in both arrays
                $result = array_intersect($result, $pages[$t]);
            } elseif ($tag{0} == '-') { // NOT: remove array from docs
                $result = array_diff($result, $pages[$t]);
            } else {                   // OR: add array to docs
                $result = array_unique(array_merge($result, $pages[$t]));
            }
        }

        return $result;
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

        $tags = explode(' ', $tags);

        if ($clean) {
            return $this->_cleanTagList($tags);
        } else {
            return $tags;
        }
    }

    /**
     * Clean a list (array) of tags using _cleanTag
     */
    function _cleanTagList($tags) {
        return array_unique(array_map(array($this, '_cleanTag'), $tags));
    }

    /**
     * Cleans a tag using cleanID while preserving a possible prefix of + or -
     */
    function _cleanTag($tag) {
        $prefix = substr($tag, 0, 1);
        $tag = $this->_applyMacro($tag);
        if ($prefix === '-' || $prefix === '+') {
            return $prefix.cleanID($tag);
        } else {
            return cleanID($tag);
        }
    }

    /**
     * Makes user or date dependent topic lists possible
     */
    function _applyMacro($id) {
        /** @var auth_basic $auth */
        global $INFO, $auth;

        $user     = $_SERVER['REMOTE_USER'];
        $group    = '';
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

    /**
     * Opposite of _notVisible
     */
    function _isVisible($id, $ns='') {
        return !$this->_notVisible($id, $ns);
    }
    /**
     * Check visibility of the page
     * 
     * @param string $id the page id
     * @param string $ns the namespace authorized
     * @return bool if the page is hidden
     */
    function _notVisible($id, $ns="") {
        if (isHiddenPage($id)) return true; // discard hidden pages
        // discard if user can't read
        if (auth_quickaclcheck($id) < AUTH_READ) return true;
        // filter by namespace, root namespace is identified with a dot
        if($ns == '.') {
            // root namespace is specified, discard all pages who lay outside the root namespace
            if(getNS($id) != false) return true;
        } else {
            // ("!==0" namespace found at position 0)
            if ($ns && (strpos(':'.getNS($id).':', ':'.$ns.':') !== 0)) return true;
        }
        return !page_exists($id, '', false);
    }

    /**
     * Helper function for the indexer in order to avoid interpreting wildcards
     */
    function _tagCompare($tag1, $tag2) {
        return $tag1 == $tag2;
    }

    /**
     * Check if the page is a real candidate for the result of the getTopic
     *
     * @param array $pagetags tags on the metadata of the page
     * @param array $tags tags we are looking
     * @return bool
     */
    function _checkPageTags($pagetags, $tags) {
        $result = false;
        foreach($tags as $tag) {
            if ($tag{0} == "+" and !in_array(substr($tag, 1), $pagetags)) return false;
            if ($tag{0} == "-" and in_array(substr($tag, 1), $pagetags)) return false;
            if (in_array($tag, $pagetags)) $result = true;
        }
        return $result;
    }

}
// vim:ts=4:sw=4:et:  
