/**
 * Javascript for tagindex management
 *
 * @author Gina Haeussge, Michael Klier <dokuwiki@chimeric.de>
 * @author Andreas Gohr <andi@splitbrain.org>
 */

/**
 * Class to hold some values
 */
function plugin_tagindex_class(){
    this.pages = null;
    this.page = null;
    this.sack = null;
    this.done = 1;
    this.count = 0;
}
var pl_si = new plugin_tagindex_class();
pl_si.sack = new sack(DOKU_BASE + 'lib/plugins/tag/ajax.php');
pl_si.sack.AjaxFailedAlert = '';
pl_si.sack.encodeURIString = false;

/**
 * Display the loading gif
 */
function plugin_tagindex_throbber(on){
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
function plugin_tagindex_status(text){
    obj = document.getElementById('pl_si_out');
    obj.innerHTML = text;
}

/**
 * Callback. Gets the list of all pages
 */
function plugin_tagindex_cb_clear(){
    ok = this.response;
    if(ok == 1){
        // start indexing
        window.setTimeout("plugin_tagindex_index()",1000);
    }else{
        plugin_tagindex_status(ok);
        // retry
        window.setTimeout("plugin_tagindex_clear()",5000);
    }
}

/**
 * Callback. Gets the list of all pages
 */
function plugin_tagindex_cb_pages(){
    data = this.response;
    pl_si.pages = data.split("\n");
    pl_si.count = pl_si.pages.length;
    plugin_tagindex_status(pl_si.pages.length+" pages found");

    pl_si.page = pl_si.pages.shift();
    window.setTimeout("plugin_tagindex_clear()",1000);
}

/**
 * Callback. Gets the info if indexing of a page was successful
 *
 * Calls the next index run.
 */
function plugin_tagindex_cb_index(){
    ok = this.response;
    if(ok == 1){
        pl_si.page = pl_si.pages.shift();
        pl_si.done++;
        // get next one
        window.setTimeout("plugin_tagindex_index()",1000);
    }else{
        plugin_tagindex_status(ok);
        // get next one
        window.setTimeout("plugin_tagindex_index()",5000);
    }
}

/**
 * Starts the indexing of a page.
 */
function plugin_tagindex_index(){
    if(pl_si.page){
        plugin_tagindex_status('indexing<br />'+pl_si.page+'<br />('+pl_si.done+'/'+pl_si.count+')<br />');
        pl_si.sack.onCompletion = plugin_tagindex_cb_index;
        pl_si.sack.URLString = '';
        pl_si.sack.runAJAX('call=indexpage&page='+encodeURI(pl_si.page));
    }else{
        plugin_tagindex_status('finished');
        plugin_tagindex_throbber(false);
    }
}

/**
 * Cleans the index
 */
function plugin_tagindex_clear(){
    plugin_tagindex_status('clearing index...');
    pl_si.sack.onCompletion = plugin_tagindex_cb_clear;
    pl_si.sack.URLString = '';
    pl_si.sack.runAJAX('call=clearindex');
}

/**
 * Starts the whole index rebuild process
 */
function plugin_tagindex_go(){
    document.getElementById('pl_si_gobtn').style.display = 'none';
    plugin_tagindex_throbber(true);

    plugin_tagindex_status('Finding all pages');
    pl_si.sack.onCompletion = plugin_tagindex_cb_pages;
    pl_si.sack.URLString = '';
    pl_si.sack.runAJAX('call=pagelist');
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
