/**
 * Javascript for tagindex management
 *
 * @author Gina Haeussge, Michael Klier <dokuwiki@chimeric.de>
 * @author Andreas Gohr <andi@splitbrain.org>
 */

/**
 * Class to hold some values
 */
function plugin_searchindex_class(){
    this.pages = null;
    this.page = null;
    this.sack = null;
    this.done = 1;
    this.count = 0;
}
var pl_si = new plugin_searchindex_class();
pl_si.sack = new sack(DOKU_BASE + 'lib/plugins/tag/ajax.php');
pl_si.sack.AjaxFailedAlert = '';
pl_si.sack.encodeURIString = false;

/**
 * Display the loading gif
 */
function plugin_searchindex_throbber(on){
    obj = document.getElementById('pl_si_throbber');
    if(on){
        obj.style.visibility='visible';
    }else{
        obj.style.visibility='hidden';
    }
}

/**
 * Gives textual feedback
 */
function plugin_searchindex_status(text){
    obj = document.getElementById('pl_si_out');
    obj.innerHTML = text;
}

/**
 * Callback. Gets the list of all pages
 */
function plugin_searchindex_cb_clear(){
    ok = this.response;
    if(ok == 1){
        // start indexing
        window.setTimeout("plugin_searchindex_index()",1000);
    }else{
        plugin_searchindex_status(ok);
        // retry
        window.setTimeout("plugin_searchindex_clear()",5000);
    }
}

/**
 * Callback. Gets the list of all pages
 */
function plugin_searchindex_cb_pages(){
    data = this.response;
    pl_si.pages = data.split("\n");
    pl_si.count = pl_si.pages.length;
    plugin_searchindex_status(pl_si.pages.length+" pages found");

    pl_si.page = pl_si.pages.shift();
    window.setTimeout("plugin_searchindex_clear()",1000);
}

/**
 * Callback. Gets the info if indexing of a page was successful
 *
 * Calls the next index run.
 */
function plugin_searchindex_cb_index(){
    ok = this.response;
    if(ok == 1){
        pl_si.page = pl_si.pages.shift();
        pl_si.done++;
        // get next one
        window.setTimeout("plugin_searchindex_index()",1000);
    }else{
        plugin_searchindex_status(ok);
        // get next one
        window.setTimeout("plugin_searchindex_index()",5000);
    }
}

/**
 * Starts the indexing of a page.
 */
function plugin_searchindex_index(){
    if(pl_si.page){
        plugin_searchindex_status('indexing<br />'+pl_si.page+'<br />('+pl_si.done+'/'+pl_si.count+')<br />');
        pl_si.sack.onCompletion = plugin_searchindex_cb_index;
        pl_si.sack.URLString = '';
        pl_si.sack.runAJAX('call=indexpage&page='+encodeURI(pl_si.page));
    }else{
        plugin_searchindex_status('finished');
        plugin_searchindex_throbber(false);
    }
}

/**
 * Cleans the index
 */
function plugin_searchindex_clear(){
    plugin_searchindex_status('clearing index...');
    pl_si.sack.onCompletion = plugin_searchindex_cb_clear;
    pl_si.sack.URLString = '';
    pl_si.sack.runAJAX('call=clearindex');
}

/**
 * Starts the whole index rebuild process
 */
function plugin_searchindex_go(){
    document.getElementById('pl_si_gobtn').style.display = 'none';
    plugin_searchindex_throbber(true);

    plugin_searchindex_status('Finding all pages');
    pl_si.sack.onCompletion = plugin_searchindex_cb_pages;
    pl_si.sack.URLString = '';
    pl_si.sack.runAJAX('call=pagelist');
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
