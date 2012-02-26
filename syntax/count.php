<?php
/**
 * Tag Plugin: displays list of keywords with links to categories this page
 * belongs to. The links are marked as tags for Technorati and other services
 * using tagging.
 *
 * Usage: {{tag>category tags space separated}}
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Matthias Schulte <mailinglist@lupo49.de>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_tag_count extends DokuWiki_Syntax_Plugin {

    var $tags = array();

    function getInfo() {
        return array(
                'author' => 'Matthias Schulte',
                'email'  => 'mailinglist@lupo49.de',
                'date'   => @file_get_contents(DOKU_PLUGIN.'tag/VERSION'),
                'name'   => 'Tag Plugin (count component)',
                'desc'   => 'Displays the occurence of specific tags',
                'url'    => 'http://www.dokuwiki.org/plugin:tag',
                );
    }

    function getType() { return 'substition'; }
    function getSort() { return 305; }
    function getPType() { return 'block';}

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{count>.*?\}\}', $mode, 'plugin_tag_count');
    }

    function handle($match, $state, $pos, &$handler) {
        
        $dump = trim(substr($match, 8, -2));     // get given tags
        $dump = explode('&', $dump);             // split to tags and allowed namespaces 
        $tags = $dump[0];
        $allowedNamespaces = explode(' ', $dump[1]); // split given namespaces into an array
        
        if($allowedNamespaces && $allowedNamespaces[0] == '') {
            unset($allowedNamespaces[0]);    // When exists, remove leading space after the delimiter
            $allowedNamespaces = array_values($allowedNamespaces);                
        }
        
        if (!$tags) return false;

        if(!($my = plugin_load('helper', 'tag'))) return false;
        $tags = $my->_parseTagList($tags);
        
        // get tags and their related occurences
        // we need all tags to discover the related pages and namespaces
        $occurences = $my->tagOccurences($tags);

        // no tags given, list all tags for allowed namespaces
        if($tags[0] == '+') {
            $pages = array();
            $listedTag = implode(' ', array_keys($occurences)); // getTopic() needs tags seperated by spaces

            foreach($allowedNamespaces as $ns) {
                $tmppages = $my->getTopic($ns, '', $listedTag);
                array_push($pages, $tmppages);                // holds all pages in allowed namespaces
            }
            
            $tags = array();    // remove old tags
            
            // discover tags of the stored pages
            foreach($pages as $entry) { 
                foreach($entry as $page => $details) {
                    $tmptags = p_get_metadata($details['id'], 'subject', METADATA_DONT_RENDER);
                    foreach($tmptags as $singletag) {    // store tags on first level inside the array
                        array_push($tags, $singletag);
                    }
                }
            }
            $tags = array_unique($tags);    // remove tag duplicates

            $occurences = $my->tagOccurences($tags, $allowedNamespaces, true);
        } else {
            $occurences = $my->tagOccurences($tags, $allowedNamespaces);
        }
        
        return $occurences;
    }      

    function render($mode, &$renderer, $data) {
        if(!($my = plugin_load('helper', 'tag'))) return false;
        
        // $data -> tag as key; value as count of tag occurence
        $class = "inline"; // valid: inline, ul, pagelist
        $col = "page";

        if($mode == "xhtml") {
            $renderer->doc .= '<table class="'.$class.'">'.DOKU_LF;
            $renderer->doc .= DOKU_TAB.'<tr>'.DOKU_LF.DOKU_TAB.DOKU_TAB;
            $renderer->doc .= '<th class="'.$col.'">tag</th>';
            $renderer->doc .= '<th class="'.$col.'">#</th>';
            $renderer->doc .= DOKU_LF.DOKU_TAB.'</tr>'.DOKU_LF;
            
            // skip output, if the individual occurences of the tags is zero
            // if the amount of tags with count == 0 is equal to the overall count of tags then skip output
            $countTags = array_keys($data, 0);
            
            if(sizeof($countTags) == sizeof($data)) {
                // Skip output
                $renderer->doc .= DOKU_TAB.'<tr>'.DOKU_LF.DOKU_TAB.DOKU_TAB;
                $renderer->doc .= DOKU_TAB.DOKU_TAB.'<td class="'.$class.'" colspan="2">'.$this->getLang('empty_output').'</td>'.DOKU_LF;
                $renderer->doc .= DOKU_LF.DOKU_TAB.'</tr>'.DOKU_LF;
            } else {
                foreach($data as $tagname => $count) {
                    if($count <= 0) continue; // don't display tags with zero occurences
                    $renderer->doc .= DOKU_TAB.'<tr>'.DOKU_LF.DOKU_TAB.DOKU_TAB;
                    $renderer->doc .= DOKU_TAB.DOKU_TAB.'<td class="'.$class.'">'.$my->tagLink($tagname).'</td>'.DOKU_LF;
                    $renderer->doc .= DOKU_TAB.DOKU_TAB.'<td class="'.$class.'">'.$count.'</td>'.DOKU_LF;
                    $renderer->doc .= DOKU_LF.DOKU_TAB.'</tr>'.DOKU_LF;
                }
            }
            $renderer->doc .= '</table>'.DOKU_LF;
        }
    }
}
// vim:ts=4:sw=4:et: 
