<?php
/**
 * Tag Plugin, topic component: displays links to all wiki pages with a certain tag
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_tag_topic extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN.'tag/VERSION'),
                'name'   => 'Tag Plugin (topic component)',
                'desc'   => 'Displays a list of wiki pages with a given category tag',
                'url'    => 'http://www.dokuwiki.org/plugin:tag',
                );
    }

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 306; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{topic>.+?\}\}',$mode,'plugin_tag_topic');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;

        $match = substr($match, 8, -2); // strip {{topic> from start and }} from end
        list($match, $flags) = explode('&', $match, 2);
        $flags = explode('&', $flags);
        list($ns, $tag) = explode('?', $match);

        if (!$tag) {
            $tag = $ns;
            $ns   = '';
        }

        if (($ns == '*') || ($ns == ':')) $ns = '';
        elseif ($ns == '.') $ns = getNS($ID);
        else $ns = cleanID($ns);

        return array($ns, trim($tag), $flags);
    }

    function render($mode, &$renderer, $data) {
        list($ns, $tag, $flags) = $data;

        if ($my =& plugin_load('helper', 'tag')) $pages = $my->getTopic($ns, '', $tag);
        if (!$pages) return true; // nothing to display

        if ($mode == 'xhtml') {

            // prevent caching to ensure content is always fresh
            $renderer->info['cache'] = false;

            // let Pagelist Plugin do the work for us
            if (plugin_isdisabled('pagelist')
                    || (!$pagelist = plugin_load('helper', 'pagelist'))) {
                msg($this->getLang('missing_pagelistplugin'), -1);
                return false;
            }

            $configflags = explode(',', str_replace(" ", "", $this->getConf('pagelist_flags')));
           	$flags = array_merge($configflags, $flags);	
           	foreach($flags as $key => $flag) {
           		if($flag == "")	unset($flags[$key]);
           	}     

            $pagelist->setFlags($flags);
            $pagelist->startList();
            foreach ($pages as $page) {
                $pagelist->addPage($page);
            }
            $renderer->doc .= $pagelist->finishList();      
            return true;

        // for metadata renderer
        } elseif ($mode == 'metadata') {
            foreach ($pages as $page) {
                $renderer->meta['relation']['references'][$page['id']] = true;
            }

            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et: 
