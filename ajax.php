<?php
/**
 * AJAX call handler for tagindex admin plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Häußge, Michael Klier <dokuwiki@chimeric.de>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

//fix for Opera XMLHttpRequests
if(!count($_POST) && $HTTP_RAW_POST_DATA) {
	parse_str($HTTP_RAW_POST_DATA, $_POST);
}

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');

if (!defined('NL'))
    define('NL', "\n");

require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/indexer.php');

//close session
session_write_close();

header('Content-Type: text/plain; charset=utf-8');

if (!auth_isadmin()) {
    die('for admins only');
}

//clear all index files
if (@file_exists($conf['indexdir'].'/page.idx')) { // new word length based index
	$tag_idx = $conf['indexdir'].'/topic.idx';
} else {                                          // old index
	$tag_idx = $conf['cachedir'].'/topic.idx';
}

$tag_helper =& plugin_load('helper', 'tag');

//call the requested function
$call = 'ajax_'.$_POST['call'];
if(function_exists($call)) {
    $call();
}else{
    print "The called function '".htmlspecialchars($call)."' does not exist!";
}

/**
 * Searches for pages
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function ajax_pagelist() {
    global $conf;

    $pages = array();
    search($pages, $conf['datadir'], 'search_allpages', array());

    foreach($pages as $page) {
        print $page['id']."\n";
    }
}

/**
 * Clear all index files
 */
function ajax_clearindex() {
    global $conf;
    global $tag_idx;
    
    // keep running
    @ignore_user_abort(true);

    // try to aquire a lock
    $lock = $conf['lockdir'].'/_tagindexer.lock';
    while(!@mkdir($lock)) {
        if(time()-@filemtime($lock) > 60*5) {
            // looks like a stale lock - remove it
            @rmdir($lock);
        }else{
            print 'tagindexer is locked.';
            exit;
        }

    }

    io_saveFile($tag_idx,'');

    // we're finished
    @rmdir($lock);

    print 1;
}

/**
 * Index the given page's tags
 */
function ajax_indexpage() {
    global $conf;
    global $tag_helper;

    if(!$_POST['page']) {
        print 1;
        exit;
    }

    // keep running
    @ignore_user_abort(true);

    // try to aquire a lock
    $lock = $conf['lockdir'].'/_tagindexer.lock';
    while(!@mkdir($lock)) {
        if(time()-@filemtime($lock) > 60*5) {
            // looks like a stale lock - remove it
            @rmdir($lock);
        }else{
            print 'tagindexer is locked.';
            exit;
        }

    }

    // do the work
    $page = array(
    	'id' => $_POST['page'],
    );
    
    $tag_helper->_generateTagData($page);

    // we're finished
    @rmdir($lock);

    print 1; 
}
// vim:ts=4:sw=4:et:enc=utf-8:
?>
