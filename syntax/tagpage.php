<?php
/**
 * Tag Plugin: Display a link to the listing of all pages with a certain tag.
 *
 * Usage: {{tagpage>mytag[&dynamic][|title]}}
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Matthias Schulte <dokuwiki@lupo49.de>
 */

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
        return 'normal';
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
    function handle($match, $state, $pos, Doku_Handler $handler) {
        $params = [];
        $match = trim(substr($match, 10, -2)); // get given tag
        $match = array_pad(explode('|', $match, 2), 2, ''); // split to tags, link name and options
        $params['title'] = $match[1];
        [$tag, $flag] = array_pad(explode('&', $match[0], 2), 2, '');
        $params['dynamic'] = ($flag == 'dynamic');
        $params['tag'] = trim($tag);

        return $params;
    }

    /**
     * Render xhtml output
     *
     * @param string         $format      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler function
     * @return bool If rendering was successful.
     */
    function render($format, Doku_Renderer $renderer, $data) {
        if($data['tag'] === '') return false;

        if($format == "xhtml") {
            if($data['dynamic']) {
                // deactivate (renderer) cache as long as there is no proper cache handling
                // implemented for the count syntax
                $renderer->nocache();
            }

            /** @var helper_plugin_tag $helper */
            if(!$helper = $this->loadHelper('tag')) {
                return false;
            }

            $renderer->doc .= $helper->tagLink($data['tag'], $data['title'], $data['dynamic']);
            return true;
        }
        return false;
    }
}
