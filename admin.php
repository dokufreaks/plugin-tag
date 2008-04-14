<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
 
/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_tag extends DokuWiki_Admin_Plugin {
 		var $cmd;

    /**
     * Constructor
     */
    function admin_plugin_tag(){
        $this->setupLocale();
    }

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Gina Häußge, Michael Klier',
            'email'  => 'dokuwiki@chimeric.de',
            'date'   => '2008-04-14',
            'name'   => 'Tagindex Manager',
            'desc'   => 'Allows to rebuild the tag index',
            'url'    => 'http://wiki.splitbrain.org/plugin:tag',
        );
    }
 
    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
        return 42;
    }
 
    /**
     * handle user request
     */
    function handle() {
    }
 
    /**
     * output appropriate html
     */
    function html() {
        print $this->plugin_locale_xhtml('intro');

        print '<fieldset class="pl_si_out">';
        
        print '<button class="button" id="pl_si_gobtn" onclick="plugin_tagindex_go()">';
        print 'Rebuild Tagindex'; #FIXME localize
        print '</button>';
        print '<div id="pl_si_out"></div>';
        print '<img src="'.DOKU_BASE.'lib/images/loading.gif" id="pl_si_throbber" style="visibility: hidden" />';

        print '</fieldset>';
        
    }

 
}
//Setup VIM: ex: et ts=4 enc=utf-8 :
