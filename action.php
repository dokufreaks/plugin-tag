<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_tag extends DokuWiki_Action_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
      'email'  => 'dokuwiki@chimeric.de',
      'date'   => '2008-04-20',
      'name'   => 'Tag Plugin (ping component)',
      'desc'   => 'Ping technorati when a new page is created',
      'url'    => 'http://wiki.splitbrain.org/plugin:tag',
    );
  }

  /**
   * register the eventhandlers
   */
  function register(&$contr){
    $contr->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'ping', array());
    $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_act', array());
    $contr->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, '_handle_tpl_act', array());
  }

  /**
   * Ping Technorati
   *
   * @author  Rui Carmo       <http://the.taoofmac.com/space/blog/2005-08-07>
   * @author  Esther Brunner  <wikidesign@gmail.com>
   */
  function ping(&$event, $param){
    if (!$this->getConf('pingtechnorati')) return false; // config: don't ping
    if ($event->data[3]) return false;                   // old revision saved
    if (@file_exists($event->data[0][0])) return false;  // file not new
    if (!$event->data[0][1]) return false;               // file is empty
        
    // okay, then let's do it!
    global $conf;
    
    $request = '<?xml version="1.0"?><methodCall>'.
      '<methodName>weblogUpdates.ping</methodName>'.
      '<params>'.
      '<param><value>'.$conf['title'].'</value></param>'.
      '<param><value>'.DOKU_URL.'</value></param>'.
      '</params>'.
      '</methodCall>';
    $url = 'http://rpc.technorati.com:80/rpc/ping';
    $header[] = 'Host: rpc.technorati.com';
    $header[] = 'Content-type: text/xml';
    $header[] = 'Content-length: '.strlen($request);
    
    $http = new DokuHTTPClient();
    // $http->headers = $header;
    return $http->post($url, $request);
  }

  /**
   * catch tag action
   *
   * @author Michael Klier <chi@chimeric.de>
   */
  function _handle_act(&$event, $param) {
      if($event->data != 'showtag') return;
      $event->preventDefault();
  }

  function _handle_tpl_act(&$event, $param) {
      global $lang;

      if($event->data != 'showtag') return;
      $event->preventDefault();

      $tagns = $this->getConf('namespace');
      $flags = explode(',', trim($this->getConf('pagelist_flags')));
 
      $tag   = trim($_REQUEST['tag']);
      $ns    = trim($_REQUEST['ns']);

      if ($helper =& plugin_load('helper', 'tag')) $pages = $helper->getTopic($ns, '', $tag);

      if(!empty($pages)) {

          // let Pagelist Plugin do the work for us
          if (plugin_isdisabled('pagelist') || (!$pagelist = plugin_load('helper', 'pagelist'))) {
            msg($this->getLang('missing_pagelistplugin'), -1);
            return false;
          }

          $pagelist->setFlags($flags);
          $pagelist->startList();
          foreach ($pages as $page){
              $pagelist->addPage($page);
          }

          print '<h1>TAG: ' . $tag . '</h1>' . DOKU_LF;
          print '<div class="level1">' . DOKU_LF;
          print $pagelist->finishList();      
          print '</div>' . DOKU_LF;

      } else {
          print '<div class="level1"><p>' . $lang['nothingfound'] . '</p></div>';
      }
  }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
