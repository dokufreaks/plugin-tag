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
        $tags = trim(substr($match, 6, -2));     // strip markup & whitespace
        $tags = preg_replace(array('/[[:blank:]]+/', '/\s+/'), " ", $tags);    // replace linebreaks and multiple spaces with one space character

        if (!$tags) return false;
        
        // load the helper_plugin_tag
        if (!$my =& plugin_load('helper', 'tag')) return false;
        
        // split tags and returns for renderer
        return $my->_parseTagList($tags);
    }      

    function render($mode, &$renderer, $data) {
        global $ID;
        global $REV;

        if ($data === false) return false;
        if (!$my =& plugin_load('helper', 'tag')) return false;

        // XHTML output
        if ($mode == 'xhtml') {
            $tags = $my->tagLinks($data);
            if (!$tags) return true;
            $renderer->doc .= '<div class="tags"><span>'.DOKU_LF.
                DOKU_TAB.$tags.DOKU_LF.
                '</span></div>'.DOKU_LF;
            return true;

        // for metadata renderer
        } elseif ($mode == 'metadata' && $ACT != 'preview' && !$REV) {
            // merge with previous tags
            $this->tags = array_merge($this->tags, $data);
            // update tags in topic.idx
            $my->_updateTagIndex($ID, $this->tags);

            if ($renderer->capture) $renderer->doc .= DOKU_LF.strip_tags($tags).DOKU_LF;

            // add references if tag page exists
            foreach ($data as $tag) {
                resolve_pageid($my->namespace, $tag, $exists); // resolve shortcuts
                if ($exists) $renderer->meta['relation']['references'][$tag] = $exists;
            }

            // erase tags on persistent metadata no more used
            if (isset($renderer->persistent['subject'])) unset($renderer->persistent['subject']);

            // update the metadata
            if (!is_array($renderer->meta['subject'])) $renderer->meta['subject'] = array();
            $renderer->meta['subject'] = $this->tags;
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et: 
