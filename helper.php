<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

use dokuwiki\Extension\Event;
use dokuwiki\Utf8\PhpString;

/**
 * Helper part of the tag plugin, allows to query and print tags
 */
class helper_plugin_tag extends DokuWiki_Plugin {

    /**
     * @deprecated 2022-10-02 Use the helper_plugin_tag::getNamespace() function instead!
     * @var string namespace tag links point to
     */
    public $namespace;
    /**
     * @var string sort key: 'cdate', 'mdate', 'pagename', 'id', 'ns', 'title'
     */
    protected $sort;
    /**
     * @var string sort order 'ascending' or 'descending'
     */
    protected $sortorder;
    /**
     * @var array
     * @deprecated 2022-08-31 Not used/filled any more by tag plugin
     */
    var $topic_idx  = [];

    /**
     * Constructor gets default preferences and language strings
     */
    public function __construct() {
        global $ID;

        $this->namespace = $this->getConf('namespace');
        if (!$this->namespace) {
            $this->namespace = getNS($ID);
        }
        $this->sort = $this->getConf('sortkey');
        $this->sortorder = $this->getConf('sortorder');
    }

    /**
     * Returns some documentation of the methods provided by this helper part
     *
     * @return array Method description
     */
    public function getMethods() {
        $result = [];

        $result[] = [
            'name'   => 'overrideSortFlags',
            'desc'   => 'takes an array of sortflags and overrides predefined value',
            'params' => [
                'name' => 'string'
            ]
        ];
        $result[] = [
                'name'   => 'th',
                'desc'   => 'returns the header for the tags column for pagelist',
                'return' => ['header' => 'string'],
        ];
        $result[] = [
                'name'   => 'td',
                'desc'   => 'returns the tag links of a given page',
                'params' => ['id' => 'string'],
                'return' => ['links' => 'string'],
        ];
        $result[] = [
                'name'   => 'tagLinks',
                'desc'   => 'generates tag links for given words',
                'params' => ['tags' => 'array'],
                'return' => ['links' => 'string'],
        ];
        $result[] = [
                'name'   => 'getTopic',
                'desc'   => 'returns a list of pages tagged with the given keyword',
                'params' => [
                    'namespace (optional)' => 'string',
                    'number (not used)' => 'integer',
                    'tag (required)' => 'string'
                ],
                'return' => ['pages' => 'array'],
        ];
        $result[] = [
                'name'   => 'tagRefine',
                'desc'   => 'refines an array of pages with tags',
                'params' => [
                    'pages to refine' => 'array',
                    'refinement tags' => 'string'
                ],
                'return' => ['pages' => 'array'],
        ];
        $result[] = [
                'name'   => 'tagOccurrences',
                'desc'   => 'returns a list of tags with their number of occurrences',
                'params' => [
                    'list of tags to get the occurrences for' => 'array',
                    'namespaces to which the search shall be restricted' => 'array',
                    'if all tags shall be returned (then the first parameter is ignored)' => 'boolean',
                    'if the namespaces shall be searched recursively' => 'boolean'
                ],
                'return' => ['tags' => 'array'],
        ];
        return $result;
    }

    /**
     * Takes an array of sortflags and overrides predefined value
     *
     * @param array $newflags recognizes:
     *      'sortkey' => string,
     *      'sortorder' => string
     * @return void
     */
    public function overrideSortFlags($newflags = []) {
        if(isset($newflags['sortkey'])) {
            $this->sort = trim($newflags['sortkey']);
        }
        if(isset($newflags['sortorder'])) {
            $this->sortorder = trim($newflags['sortorder']);
        }
    }

    /**
     * Returns the column header for the Pagelist Plugin
     */
    public function th() {
        return $this->getLang('tags');
    }

    /**
     * Returns the cell data for the Pagelist Plugin
     *
     * @param string $id page id
     * @return string html content for cell of table
     */
    public function td($id) {
        $subject = $this->getTagsFromPageMetadata($id);
        return $this->tagLinks($subject);
    }

    /**
     *
     * @return string|false
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     * Returns the links for given tags
     *
     * @param array $tags an array of tags
     * @return string HTML link tags
     */
    public function tagLinks($tags) {
        if (empty($tags) || ($tags[0] == '')) {
            return '';
        }

        $links = array();
        foreach ($tags as $tag) {
            $links[] = $this->tagLink($tag);
        }
        return implode(','.DOKU_LF.DOKU_TAB, $links);
    }

    /**
     * Returns the link for one given tag
     *
     * @param string $tag the tag the link shall point to
     * @param string $title the title of the link (optional)
     * @param bool   $dynamic if the link class shall be changed if no pages with the specified tag exist
     * @return string The HTML code of the link
     */
    public function tagLink($tag, $title = '', $dynamic = false) {
        global $conf;
        $svtag = $tag;
        $tagTitle = str_replace('_', ' ', noNS($tag));
        // Igor and later
        if (class_exists('dokuwiki\File\PageResolver')) {
            $resolver = new dokuwiki\File\PageResolver($this->namespace . ':something');
            $tag = $resolver->resolveId($tag);
            $exists = page_exists($tag);
        } else {
            // Compatibility with older releases
            resolve_pageid($this->namespace, $tag, $exists);
        }
        if ($exists) {
            $class = 'wikilink1';
            $url   = wl($tag);
            if ($conf['useheading']) {
                // important: set render param to false to prevent recursion!
                $heading = p_get_first_heading($tag, false);
                if ($heading) {
                    $tagTitle = $heading;
                }
            }
        } else {
            if ($dynamic) {
                $pages = $this->getTopic('', 1, $svtag);
                if (empty($pages)) {
                    $class = 'wikilink2';
                } else {
                    $class = 'wikilink1';
                }
            } else {
                $class = 'wikilink1';
            }
            $url   = wl($tag, ['do'=>'showtag', 'tag'=>$svtag]);
        }
        if (!$title) {
            $title = $tagTitle;
        }
        $link = [
            'href' => $url,
            'class' => $class,
            'tooltip' => hsc($tag),
            'title' => hsc($title)
        ];
        Event::createAndTrigger('PLUGIN_TAG_LINK', $link);
        return '<a href="'.$link['href'].'" class="'.$link['class'].'" title="'.$link['tooltip'].'" rel="tag">'
                .$link['title']
                .'</a>';
    }

    /**
     * Returns a list of pages with a certain tag; very similar to ft_backlinks()
     *
     * @param string $ns A namespace to which all pages need to belong, "." for only the root namespace
     * @param int    $num The maximum number of pages that shall be returned
     * @param string $tagquery The tag string that shall be searched e.g. 'tag +tag -tag'
     * @return array The list of pages
     *
     * @author  Esther Brunner <wikidesign@gmail.com>
     */
    public function getTopic($ns = '', $num = null, $tagquery = '') {
        global $INPUT;
        if (!$tagquery) {
            $tagquery = $INPUT->str('tag');
        }
        $queryTags = $this->parseTagList($tagquery, true);
        $result = [];

        // find the pages using subject_w.idx
        $pages = $this->getIndexedPagesMatchingTagQuery($queryTags);
        if (!count($pages)) {
            return $result;
        }

        foreach ($pages as $page) {
            // exclude pages depending on ACL and namespace
            if($this->isNotVisible($page, $ns)) continue;

            $pageTags  = $this->getTagsFromPageMetadata($page);
            // don't trust index
            if (!$this->matchWithPageTags($pageTags, $queryTags)) continue;

            // get metadata
            $meta = p_get_metadata($page);

            $perm = auth_quickaclcheck($page);

            // skip drafts unless for users with create privilege
            $isDraft = isset($meta['type']) && $meta['type'] == 'draft';
            if ($isDraft && $perm < AUTH_CREATE) continue;

            $title = $meta['title'] ?? '';
            $date  = ($this->sort == 'mdate' ? $meta['date']['modified'] : $meta['date']['created'] );
            $taglinks = $this->tagLinks($pageTags);

            // determine the sort key
            switch($this->sort) {
                case 'id':
                    $sortkey = $page;
                    break;
                case 'ns':
                    $pos = strrpos($page, ':');
                    if ($pos === false) {
                        $sortkey = "\0".$page;
                    } else {
                        $sortkey = substr_replace($page, "\0\0", $pos, 1);
                    }
                    $sortkey = str_replace(':', "\0", $sortkey);
                    break;
                case 'pagename':
                    $sortkey = noNS($page);
                    break;
                case 'title':
                    $sortkey = PhpString::strtolower($title);
                    if (empty($sortkey)) {
                        $sortkey = str_replace('_', ' ', noNS($page));
                    }
                    break;
                default:
                    $sortkey = $date;
            }
            // make sure that the key is unique
            $sortkey = $this->uniqueKey($sortkey, $result);

            $result[$sortkey] = [
                    'id'     => $page,
                    'title'  => $title,
                    'date'   => $date,
                    'user'   => $meta['creator'],
                    'desc'   => $meta['description']['abstract'],
                    'cat'    => $pageTags[0],
                    'tags'   => $taglinks,
                    'perm'   => $perm,
                    'exists' => true,
                    'draft'  => $isDraft
            ];

            if ($num && count($result) >= $num) {
                break;
            }
        }

        // finally sort by sort key
        if ($this->sortorder == 'ascending') {
            ksort($result);
        } else {
            krsort($result);
        }

        return $result;
    }

    /**
     * Refine found pages with tags (+tag: AND, -tag: (AND) NOT)
     *
     * @param array $pages The pages that shall be filtered, each page needs to be an array with a key "id"
     * @param string $tagquery The list of tags in the form "tag +tag2 -tag3". The tags will be cleaned.
     * @return array The filtered list of pages
     */
    public function tagRefine($pages, $tagquery) {
        if (!is_array($pages)) {
            // wrong data type
            return $pages;
        }
        $queryTags = $this->parseTagList($tagquery, true);
        $allMatchedPages = $this->getIndexedPagesMatchingTagQuery($queryTags);

        foreach ($pages as $key => $page) {
            if (!in_array($page['id'], $allMatchedPages)) {
                unset($pages[$key]);
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
    * @param boolean $isRecursive boolean if counting of pages in subnamespaces is allowed
    * @return array with:
    *   $tag => int count
    */
    public function tagOccurrences($tags, $namespaces = null, $allTags = false, $isRecursive = null) {
        // map with trim here in order to remove newlines from tags
        if($allTags) {
            $tags = array_map('trim', idx_getIndex('subject', '_w'));
        }
        $tags = $this->cleanTagList($tags);
        $tagOccurrences = []; //occurrences
        // $namespaces not specified
        if(!$namespaces || $namespaces[0] == '' || !is_array($namespaces)) {
            $namespaces = null;
        }

        $indexer = idx_get_indexer();
        $indexedPagesWithTags = $indexer->lookupKey('subject', $tags, array($this, 'tagCompare'));

        $isRootAllowed = !($namespaces === null) && in_array('.', $namespaces);
        if ($isRecursive === null) {
            $isRecursive = $this->getConf('list_tags_of_subns');
        }

        foreach ($tags as $tag) {
            if (!isset($indexedPagesWithTags[$tag])) continue;

            // just to be sure remove duplicate pages from the list of pages
            $pages = array_unique($indexedPagesWithTags[$tag]);

            // don't count hidden pages or pages the user can't access
            // for performance reasons this doesn't take drafts into account
            $pages = array_filter($pages, [$this, 'isVisible']);

            if (empty($pages)) continue;

            if ($namespaces == null || ($isRootAllowed && $isRecursive)) {
                // count all pages
                $tagOccurrences[$tag] = count($pages);
            } else if (!$isRecursive) {
                // filter by exact namespace
                $tagOccurrences[$tag] = 0;
                foreach ($pages as $page) {
                    $ns = getNS($page);
                    if (($ns === false && $isRootAllowed) || in_array($ns, $namespaces)) {
                        $tagOccurrences[$tag]++;
                    }
                }
            } else { // recursive, no root
                $tagOccurrences[$tag] = 0;
                foreach ($pages as $page) {
                    foreach ($namespaces as $ns) {
                        if(strpos($page, $ns.':') === 0 ) {
                            $tagOccurrences[$tag]++ ;
                            break;
                        }
                    }
                }
            }
            // don't return tags without pages
            if ($tagOccurrences[$tag] == 0) {
                unset($tagOccurrences[$tag]);
            }
        }
        return $tagOccurrences;
    }

    /**
     * Get tags from the 'subject' metadata field
     *
     * @param string $id the page id
     * @return array
     */
    protected function getTagsFromPageMetadata($id){
        $tags = p_get_metadata($id, 'subject');
        if (!is_array($tags)) {
            $tags = explode(' ', $tags);
        }
        return array_unique($tags);
    }

    /**
     * Returns pages from index matching the tag query
     *
     * @param array $queryTags the tags to filter e.g. ['tag'(OR), '+tag'(AND), '-tag'(NOT)]
     * @return array the matching page ids
     */
    public function getIndexedPagesMatchingTagQuery($queryTags) {
        $result = []; // array of page ids

        $cleanTags = [];
        foreach ($queryTags as $i => $tag) {
            if ($tag[0] == '+' || $tag[0] == '-') {
                $cleanTags[$i] = substr($tag, 1);
            } else {
                $cleanTags[$i] = $tag;
            }
        }

        $indexer = idx_get_indexer();
        $pages = $indexer->lookupKey('subject', $cleanTags, [$this, 'tagCompare']);
        // use all pages as basis if the first tag isn't an "or"-tag or if there are no tags given
        if (empty($queryTags) || $cleanTags[0] != $queryTags[0]) {
            $result = $indexer->getPages();
        }

        foreach ($queryTags as $i => $queryTag) {
            $tag = $cleanTags[$i];
            if (!is_array($pages[$tag])) {
                $pages[$tag] = [];
            }

            if ($queryTag[0] == '+') {       // AND: add only if in both arrays
                $result = array_intersect($result, $pages[$tag]);
            } elseif ($queryTag[0] == '-') { // NOT: remove array from docs
                $result = array_diff($result, $pages[$tag]);
            } else {                         // OR: add array to docs
                $result = array_unique(array_merge($result, $pages[$tag]));
            }
        }

        return $result;
    }



    /**
     * Splits a string into an array of tags
     *
     * @param string $tags tag string, if containing spaces use quotes e.g. "tag with spaces", will be replaced by underscores
     * @param bool $clean replace placeholders and clean id
     * @return string[]
     */
    public function parseTagList($tags, $clean = false) {

        // support for "quoted phrase tags", replaces spaces by underscores
        if (preg_match_all('#".*?"#', $tags, $matches)) {
            foreach ($matches[0] as $match) {
                $replace = str_replace(' ', '_', substr($match, 1, -1));
                $tags = str_replace($match, $replace, $tags);
            }
        }

        $tags = preg_split('/ /', $tags, -1, PREG_SPLIT_NO_EMPTY);

        if ($clean) {
            return $this->cleanTagList($tags);
        } else {
            return $tags;
        }
    }

    /**
     * Clean a list (array) of tags using _cleanTag
     *
     * @param string[] $tags
     * @return string[]
     */
    public function cleanTagList($tags) {
        return array_unique(array_map([$this, 'cleanTag'], $tags));
    }

    /**
     * callback: Cleans a tag using cleanID while preserving a possible prefix of + or -, and replace placeholders
     *
     * @param string $tag
     * @return string
     */
    protected function cleanTag($tag) {
        $prefix = substr($tag, 0, 1);
        $tag = $this->replacePlaceholders($tag);
        if ($prefix === '-' || $prefix === '+') {
            return $prefix.cleanID($tag);
        } else {
            return cleanID($tag);
        }
    }

    /**
     * Makes user or date dependent topic lists possible by replacing placeholders in tags
     *
     * @param string $tag
     * @return string
     */
    protected function replacePlaceholders($tag) {
        global $USERINFO, $INPUT;

        $user = $INPUT->server->str('REMOTE_USER');

        //only available for logged-in users
        if(isset($USERINFO)) {
            if(is_array($USERINFO) && isset($USERINFO['name'])) {
                $name  = cleanID($USERINFO['name']);
            }
            else {
                $name = '';
            }
            // FIXME or delete, is unreliable because just first entry of group array is used, regardless the order of groups..
            if(is_array($USERINFO) && is_array($USERINFO['grps']) && isset($USERINFO['grps'][0])) {
                $group = cleanID($USERINFO['grps'][0]);
            }
            else {
                $group = '';
            }
        } else {
            $name  = '';
            $group = '';
        }

        $replace = [
                '@USER@'  => cleanID($user),
                '@NAME@'  => $name,
                '@GROUP@' => $group,
                '@YEAR@'  => date('Y'),
                '@MONTH@' => date('m'),
                '@DAY@'   => date('d'),
        ];
        return str_replace(array_keys($replace), array_values($replace), $tag);
    }

    /**
     * Non-recursive function to check whether an array key is unique
     *
     * @param int|string $key
     * @param array $result
     * @return float|int|string
     *
     * @author    Ilya S. Lebedev <ilya@lebedev.net>
     * @author    Esther Brunner <wikidesign@gmail.com>
     */
    protected function uniqueKey($key, $result) {

        // increase numeric keys by one
        if (is_numeric($key)) {
            while (array_key_exists($key, $result)) {
                $key++;
            }
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
     * Opposite of isNotVisible()
     *
     * @param string $id the page id
     * @param string $ns
     * @return bool if the page is shown
     */
    public function isVisible($id, $ns='') {
        return !$this->isNotVisible($id, $ns);
    }

    /**
     * Check visibility of the page
     *
     * @param string $id the page id
     * @param string $ns the namespace authorized
     * @return bool if the page is hidden
     */
    public function isNotVisible($id, $ns="") {
        // discard hidden pages
        if (isHiddenPage($id)) {
            return true;
        }
        // discard if user can't read
        if (auth_quickaclcheck($id) < AUTH_READ) {
            return true;
        }

        // filter by namespace, root namespace is identified with a dot
        if($ns == '.') {
            // root namespace is specified, discard all pages who lay outside the root namespace
            if(getNS($id) !== false) {
                return true;
            }
        } else {
            // hide if ns is not matching the page id (match gives strpos===0)
            if ($ns && strpos(':'.getNS($id).':', ':'.$ns.':') !== 0) {
                return true;
            }
        }
        return !page_exists($id, '', false);
    }

    /**
     * callback Helper function for the indexer in order to avoid interpreting wildcards
     *
     * @param string $tag1 tag being searched
     * @param string $tag2 tag from index
     * @return bool is equal?
     */
    public function tagCompare($tag1, $tag2) {
        return $tag1 === $tag2;
    }

    /**
     * Check if the page is a real candidate for the result of the getTopic by comparing its tags with the wanted tags
     *
     * @param string[] $pageTags cleaned tags from the metadata of the page
     * @param string[] $queryTags tags we are looking ['tag', '+tag', '-tag']
     * @return bool
     */
    protected function matchWithPageTags($pageTags, $queryTags) {
        $result = false;
        foreach($queryTags as $tag) {
            if ($tag[0] == "+" and !in_array(substr($tag, 1), $pageTags)) {
                $result = false;
            }
            if ($tag[0] == "-" and in_array(substr($tag, 1), $pageTags)) {
                $result = false;
            }
            if (in_array($tag, $pageTags)) {
                $result = true;
            }
        }
        return $result;
    }


    /**
     * @deprecated 2022-08-31 use parseTagList() instead !
     *
     * @param string $tags
     * @param bool $clean
     * @return string[]
     */
    public function _parseTagList($tags, $clean = false) {
        return $this->parseTagList($tags, $clean);
    }

    /**
     * Opposite of isNotVisible()
     *
     * @deprecated 2022-08-31 use isVisible() instead !
     *
     * @param string $id
     * @param string $ns
     * @return bool
     */
    public function _isVisible($id, $ns='') {
        return $this->isVisible($id, $ns);
    }

    /**
     * Clean a list (array) of tags using _cleanTag
     *
     * @deprecated 2022-08-31 use cleanTagList() instead !
     *
     * @param string[] $tags
     * @return string[]
     */
    public function _cleanTagList($tags) {
        return $this->cleanTagList($tags);
    }

    /**
     * Returns pages from index matching the tag query
     *
     * @param array $queryTags the tags to filter e.g. ['tag'(OR), '+tag'(AND), '-tag'(NOT)]
     * @return array the matching page ids
     *
     * @deprecated 2022-08-31 use getIndexedPagesMatchingTagQuery() instead !
     */
    function _tagIndexLookup($queryTags) {
        return $this->getIndexedPagesMatchingTagQuery($queryTags);
    }

    /**
     * Get the subject metadata cleaning the result
     *
     * @deprecated 2022-08-31 use getTagsFromPageMetadata() instead !
     *
     * @param string $id the page id
     * @return array
     */
    public function _getSubjectMetadata($id){
        return $this->getTagsFromPageMetadata($id);
    }
}
