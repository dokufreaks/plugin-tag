<?php
/**
 *
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
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2007-08-03',
      'name'   => 'Tag Plugin (ping component)',
      'desc'   => 'Ping technorati when a new page is created',
      'url'    => 'http://www.wikidesign.ch/en/plugin/tag/start',
    );
  }

  /**
   * register the eventhandlers
   */
  function register(&$contr){
    $contr->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'ping', array());
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
    
    /*
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    
    curl_exec($ch);
    if (curl_errno($ch)){
      curl_close($ch);
      return false;
    } else {
      curl_close($ch);
      return true;
    }
    */
  }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
