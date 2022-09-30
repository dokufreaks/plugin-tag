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

/**
 * Tag syntax plugin, allows to specify tags in a page
 */
class syntax_plugin_tag_tag extends DokuWiki_Syntax_Plugin {

    /**
     * @return string Syntax type
     */
    function getType() { return 'substition'; }
    /**
     * @return int Sort order
     */
    function getSort() { return 305; }
    /**
     * @return string Paragraph type
     */
    function getPType() { return 'block';}

    /**
     * @param string $mode Parser mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{tag>.*?\}\}', $mode, 'plugin_tag_tag');
    }

    /**
     * Handle matches of the tag syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array|false Data for the renderer
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        $tags = trim(substr($match, 6, -2));     // strip markup & whitespace
        $tags = trim($tags, "\xe2\x80\x8b"); // strip word/wordpad breaklines
        $tags = preg_replace(['/[[:blank:]]+/', '/\s+/'], " ", $tags);    // replace linebreaks and multiple spaces with one space character
        $tags = preg_replace('/[\x00-\x1F\x7F]/u', '', $tags); // strip unprintable ascii code out of utf-8 coded string

        if (!$tags) return false;

        // load the helper_plugin_tag
        /** @var helper_plugin_tag $helper */
        if (!$helper = $this->loadHelper('tag')) {
            return false;
        }

        // split tags and returns for renderer
        return $helper->parseTagList($tags);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $format      Renderer mode (supported modes: xhtml and metadata)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler function
     * @return bool If rendering was successful.
     */
    function render($format, Doku_Renderer $renderer, $data) {
        if ($data === false) return false;
        /** @var helper_plugin_tag $helper */
        if (!$helper = $this->loadHelper('tag')) return false;

        // XHTML output
        if ($format == 'xhtml') {
            $tags = $helper->tagLinks($data);
            if (!$tags) {
                return true;
            }
            $renderer->doc .= '<div class="'.$this->getConf('tags_list_css').'">'
                . '<span>'.DOKU_LF
                . DOKU_TAB.$tags.DOKU_LF
                . '</span>'
                . '</div>'.DOKU_LF;
            return true;

        // for metadata renderer
        } elseif ($format == 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */
            // erase tags on persistent metadata no more used
            if (isset($renderer->persistent['subject'])) {
                unset($renderer->persistent['subject']);
                $renderer->meta['subject'] = [];
            }

            if (!isset($renderer->meta['subject'])) {
                $renderer->meta['subject'] = [];
            }

            // each registered tags in metadata and index should be valid IDs
            $data = array_map('cleanID', $data);
            // merge with previous tags and make the values unique
            $renderer->meta['subject'] = array_unique(array_merge($renderer->meta['subject'], $data));

            if ($renderer->capture) {
                $renderer->doc .= DOKU_LF.implode(' ', $data).DOKU_LF;
            }

            // add references if tag page exists
            foreach ($data as $tag) {
                // resolve shortcuts
                // Igor and later
                if (class_exists('dokuwiki\File\PageResolver')) {
                    $resolver = new dokuwiki\File\PageResolver($helper->getNamespace() . ':something');
                    $tag = $resolver->resolveId($tag);
                } else {
                    // Compatibility with older releases
                    resolve_pageid($helper->getNamespace(), $tag, $exists);
                }
                $renderer->meta['relation']['references'][$tag] = page_exists($tag);
            }

            return true;
        }
        return false;
    }
}
