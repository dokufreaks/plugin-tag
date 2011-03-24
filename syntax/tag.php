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

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_tag_tag extends DokuWiki_Syntax_Plugin {

    var $tags = array();

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN.'tag/VERSION'),
                'name'   => 'Tag Plugin (tag component)',
                'desc'   => 'Displays links to categories the page belongs to',
                'url'    => 'http://www.dokuwiki.org/plugin:tag',
                );
    }

    function getType() { return 'substition'; }
    function getSort() { return 305; }
    function getPType() { return 'block';}

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{tag>.*?\}\}', $mode, 'plugin_tag_tag');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;
        global $REV;

        $tags = trim(substr($match, 6, -2));     // strip markup & whitespace
        if (!$tags) return false;
        if (!$my =& plugin_load('helper', 'tag')) return false;
        $tags = $my->_parseTagList($tags); // split tags
        $this->tags = array_merge($this->tags, $tags);
        // disable update of tags when viewing old page revisions
        if($ACT != 'preview' && !$REV) $my->_updateTagIndex($ID, $this->tags);
        return $tags;
    }      

    function render($mode, &$renderer, $data) {
        if ($data === false) return false;
        if (!$my =& plugin_load('helper', 'tag')) return false;
        $tags = $my->tagLinks($data);
        if (!$tags) return true;

        // XHTML output
        if ($mode == 'xhtml') {
            $renderer->doc .= '<div class="tags"><span>'.DOKU_LF.
                DOKU_TAB.$tags.DOKU_LF.
                '</span></div>'.DOKU_LF;
            return true;

        // for metadata renderer
        } elseif ($mode == 'metadata' && $ACT != 'preview' && !$REV) {
            if ($renderer->capture) $renderer->doc .= DOKU_LF.strip_tags($tags).DOKU_LF;
            foreach ($my->references as $ref => $exists) {
                $renderer->meta['relation']['references'][$ref] = $exists;
            }
            if (!is_array($renderer->meta['subject'])) $renderer->meta['subject'] = array();
            $renderer->meta['subject'] = array_merge($renderer->meta['subject'], $data);
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et: 
