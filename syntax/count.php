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

        if (empty($allowedNamespaces)) $allowedNamespaces = null;

        if (!$tags) $tags = '+';

        if(!($my = plugin_load('helper', 'tag'))) return false;

        return array($my->_parseTagList($tags), $allowedNamespaces);
    }

    function render($mode, &$renderer, $data) {
        if ($data == false) return false;

        list($tags, $allowedNamespaces) = $data;

        // deactivate (renderer) cache as long as there is no proper cache handling
        // implemented for the count syntax
        $renderer->info['cache'] = false;

        if($mode == "xhtml") {
            if(!($my = plugin_load('helper', 'tag'))) return false;

            // get tags and their occurences
            if($tags[0] == '+') {
                // no tags given, list all tags for allowed namespaces
                $occurences = $my->tagOccurences($tags, $allowedNamespaces, true);
            } else {
                $occurences = $my->tagOccurences($tags, $allowedNamespaces);
            }

            $class = "inline"; // valid: inline, ul, pagelist
            $col = "page";

            $renderer->doc .= '<table class="'.$class.'">'.DOKU_LF;
            $renderer->doc .= DOKU_TAB.'<tr>'.DOKU_LF.DOKU_TAB.DOKU_TAB;
            $renderer->doc .= '<th class="'.$col.'">tag</th>';
            $renderer->doc .= '<th class="'.$col.'">#</th>';
            $renderer->doc .= DOKU_LF.DOKU_TAB.'</tr>'.DOKU_LF;

            if(empty($occurences)) {
                // Skip output
                $renderer->doc .= DOKU_TAB.'<tr>'.DOKU_LF.DOKU_TAB.DOKU_TAB;
                $renderer->doc .= DOKU_TAB.DOKU_TAB.'<td class="'.$class.'" colspan="2">'.$this->getLang('empty_output').'</td>'.DOKU_LF;
                $renderer->doc .= DOKU_LF.DOKU_TAB.'</tr>'.DOKU_LF;
            } else {
                foreach($occurences as $tagname => $count) {
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
