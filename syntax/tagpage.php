<?php
/**
 * Tag Plugin: displays list of keywords with links to categories this page
 * belongs to. The links are marked as tags for Technorati and other services
 * using tagging.
 *
 * Usage: {{tag>category tags space separated}}
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Matthias Schulte <dokuwiki@lupo49.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_LF')) define('DOKU_LF', "\n");
if(!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

/** Tagpage syntax, allows to link to a given tag */
class syntax_plugin_tag_tagpage extends DokuWiki_Syntax_Plugin {

    /**
     * @return string Syntax type
     */
    function getType() {
        return 'substition';
    }

    /**
     * @return int Sort order
     */
    function getSort() {
        return 305;
    }

    /**
     * @return string Paragraph type
     */
    function getPType() {
        return 'block';
    }

    /**
     * @param string $mode Parser mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{tagpage>.*?\}\}', $mode, 'plugin_tag_tagpage');
    }

    /**
     * Handle matches of the count syntax
     *
     * @param string          $match The match of the syntax
     * @param int             $state The state of the handler
     * @param int             $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    function handle($match, $state, $pos, &$handler) {
        $dump     = trim(substr($match, 10, -2)); // get given tag
        $dump     = explode('|', $dump); // split to tags and link name
        $tag      = $dump[0];
        $linkname = $dump[1];

        return array($tag, $linkname);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml and metadata)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler function
     * @return bool If rendering was successful.
     */
    function render($mode, &$renderer, $data) {
        if($data == false) return false;
        global $conf;

        list($tag, $title) = $data;

        // deactivate (renderer) cache as long as there is no proper cache handling
        // implemented for the count syntax
        $renderer->info['cache'] = false;

        if($mode == "xhtml") {
            /** @var helper_plugin_tag $my */
            if(!($my = plugin_load('helper', 'tag'))) return false;

            $pages = $my->_tagIndexLookup(array($tag));
            $url   = wl($tag, array('do'=> 'showtag', 'tag'=> $tag));

            // Set titel to tagname if no titel is specified
            if(empty($title)) $title = $tag;

            if(empty($pages)) {
                // No pages with given tag found, link will be red
                $class = 'wikilink2';
            } else {
                // At least one page found, show link as green
                $class = 'wikilink1';
                // Link to page, if only one page found else show a pagelist
                if(count($pages) == 1) {
                    $url = wl($pages[0]);
                } else {
                    $url = wl($tag, array('do'=> 'showtag', 'tag'=> $tag));
                }
            }

            $link = '<a href="'.$url.'" class="'.$class.'" title="'.hsc($tag).
                '" rel="tag">'.hsc($title).'</a>';
            $renderer->doc .= $link;
        }
        return true;
    }
}
// vim:ts=4:sw=4:et: 
