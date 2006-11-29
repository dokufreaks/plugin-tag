<?php
/**
 * XML feed export
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once(DOKU_INC.'inc/feedcreator.class.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/pageutils.php');

//close session
session_write_close();

$type  = $_REQUEST['type'];
$tag   = $_REQUEST['tag'];

echo $tag;

if ($type == '') $type = $conf['rss_type'];

switch ($type){
  case 'rss':
    $type = 'RSS0.91';
    $mime = 'text/xml';
    break;
  case 'rss2':
    $type = 'RSS2.0';
    $mime = 'text/xml';
    break;
  case 'atom':
    $type = 'ATOM0.3';
    $mime = 'application/xml';
    break;
  case 'atom1':
    $type = 'ATOM1.0';
    $mime = 'application/atom+xml';
    break;
  default:
    $type = 'RSS1.0';
    $mime = 'application/xml';
}

// the feed is dynamic - we need a cache for each combo
// (but most people just use the default feed so it's still effective)
// $cache = getCacheName('tag'.$tag.$type.$_SERVER['REMOTE_USER'], '.feed');

// check cacheage and deliver if nothing has changed since last
// time or the update interval has not passed, also handles conditional requests
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Type: application/xml; charset=utf-8');
$cmod = @filemtime($cache); // 0 if not exists
if ($cmod &&
  (($cmod + $conf['rss_update'] > time()) || ($cmod > @filemtime($conf['changelog'])))){
  http_conditionalRequest($cmod);
  if($conf['allowdebug']) header("X-CacheUsed: $cache");
  print io_readFile($cache);
  exit;
} else {
  http_conditionalRequest(time());
}

// create new feed
$rss = new DokuWikiFeedCreator();
$rss->title = $conf['title'].(($tag) ? ' '.ucwords($tag) : '');
$rss->link  = DOKU_URL;
$rss->syndicationURL = DOKU_URL.'lib/plugins/tag/feed.php';
$rss->cssStyleSheet  = DOKU_URL.'lib/styles/feed.css';

$image = new FeedImage();
$image->title = $conf['title'];
$image->url = DOKU_URL."lib/images/favicon.ico";
$image->link = DOKU_URL;
$rss->image = $image;

echo 'ok';

rssTagList($rss, $tag);

$feed = $rss->createFeed($type, 'utf-8');

// save cachefile
io_saveFile($cache, $feed);

// finally deliver
print $feed;

/* ---------- */

/**
 * Add tagged pages to feed object
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Esther Brunner <wikidesign@gmail.com>
 */
function rssTagList(&$rss, $tag){
  global $conf;
  
  $entries = _topicList($tag);
  var_dump($entries);
  
  foreach ($entries as $entry){
    $item = new FeedItem();

    if ($entry['title']) $item->title = $entry['title'];
    else $item->title = ucwords($entry['id']);

    $item->link = wl($entry['id'], '', true);

    $item->description = htmlspecialchars($entry['desc']);
    $item->date        = date('r', $entry['date']);
    if ($entry['cat']) $item->category = $entry['cat'];
    $item->author = $entry['user'];

    $rss->addItem($item);
  }
}

/**
 * returns a list of pages with a certain tag; very similar to ft_backlinks()
 * caution: not exactly the same as the function with the same name in syntax/topic.php!
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @author  Esther Brunner <wikidesign@gmail.com>
 */
function _topicList($tag){
  require_once (DOKU_INC.'inc/indexer.php');
  
  $tag = utf8_strtolower($tag);
  $result = array();
  
  // quick lookup of the pagename
  $sw    = array(); // we don't use stopwords here
  $matches = idx_lookup(idx_tokenizer($tag, $sw));  // pagename may contain specials (_ or .)
  $docs  = array_keys(array_values($matches));
  echo $matches;
  
  $docs  = array_filter($docs, 'isVisiblePage'); // discard hidden pages
  if (!count($docs)) return $result;

  // check metadata for matching subject
  foreach ($docs as $match){
        
    // get metadata
    $meta = array();
    $meta = p_get_metadata($match);
    $tags = $meta['subject'];
    if (!is_array($tags)) $tags = explode(' ', $tags);
    
    // does it match?
    foreach ($tags as $word){
      if ($tag == utf8_strtolower($word)){

        // check ACL permission; if okay, then add the page
        if (auth_quickaclcheck($match) >= AUTH_READ){
          $title = $meta['title'];
          $result[$title] = array(
            'id'    => $match,
            'title' => $title,
            'date'  => $meta['date']['created'],
            'user'  => $meta['creator'],
            'desc'  => $meta['description']['abstract'],
            'cat'   => $tags[0],
          );
        }
        break;
      }
    }
  }
  if (!count($result)) return $result;
      
  ksort($result);
  return $result;
}

//Setup VIM: ex: et ts=2 enc=utf-8 :
