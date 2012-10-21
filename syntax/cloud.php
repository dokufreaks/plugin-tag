<?php
/**
 * Tag Plugin: displays tag cloud of all tags
 *
 * Usage: {{cloud>[namespace1 namespace2][&showcounts]}}
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Matthias Schulte <mailinglist@lupo49.de>
 * @author   Robert McLeod <hamstar@telescum.co.nz>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

/** Count syntax, allows to list tag counts */
class syntax_plugin_tag_cloud extends DokuWiki_Syntax_Plugin {

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
        $this->Lexer->addSpecialPattern('\{\{cloud>.*?\}\}', $mode, 'plugin_tag_cloud');
    }

    /**
     * Handle matches of the count syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    function handle($match, $state, $pos, &$handler) {

    	$string = str_replace(array("{","}"), "", $match ); 			// get the string
		list( $string, $flags ) = explode("&", $string );   			// get the flags
		list( $string, $allowedNamespaces ) = explode(">", $string );	// get the namespaces
	
		$allowedNamespaces = explode(' ', $dump[1]); 					// split given namespaces into an array

        if($allowedNamespaces && $allowedNamespaces[0] == '') {
            unset($allowedNamespaces[0]);    // When exists, remove leading space after the delimiter
            $allowedNamespaces = array_values($allowedNamespaces);
        }

        if (empty($allowedNamespaces)) $allowedNamespaces = null;

		// Set some flags for passing
		$showCounts = strstr( $flags, "showcounts" );
		
        /** @var helper_plugin_tag $my */
        if(!($my = plugin_load('helper', 'tag'))) return false;

        return array( $my->_parseTagList('+'), $allowedNamespaces, $showCounts );
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
        if ($data == false) return false;

        list(
			$tags, 
			$allowedNamespaces, 
			$showCounts
		) = $data;

        // deactivate (renderer) cache as long as there is no proper cache handling
        // implemented for the count syntax
        $renderer->info['cache'] = false;

        if($mode == "xhtml") {
            /** @var helper_plugin_tag $my */
            if(!($my = plugin_load('helper', 'tag'))) return false;

            // get tags and their occurrences
            if($tags[0] == '+') {
                // no tags given, list all tags for allowed namespaces
                $occurrences = $my->tagOccurrences($tags, $allowedNamespaces, true);
            } else {
                $occurrences = $my->tagOccurrences($tags, $allowedNamespaces);
            }

            $renderer->doc .= '<div class="tagcloud">'.DOKU_LF;

            if(empty($occurrences)) {
                // Skip output
                $renderer->doc .= DOKU_TAB."<p>No tags yet</p>".DOKU_LF;
            } else {
                foreach($occurrences as $tagname => $count) {
                   
					if($count <= 0) continue; // don't display tags with zero occurrences
					
					if ( !$showCounts ) 	  // don't show count if flag is set
						$count = null;
						
                    $renderer->doc .= DOKU_TAB.'<span class="tag">'.$my->tagLink($tagname, $count).'</span>'.DOKU_LF;
                }
            }
            $renderer->doc .= '</div>'.DOKU_LF;
        }
        return true;
    }
}
// vim:ts=4:sw=4:et: 