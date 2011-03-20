<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the tag plugin
 *
 * @author    Esther Brunner <wikidesign@gmail.com>
 */

$meta['namespace']      = array('string');
$meta['pingtechnorati'] = array('onoff');
$meta['sortkey']        = array('multichoice',
                          '_choices' => array('cdate', 'mdate', 'pagename', 'id', 'title'));
$meta['sortorder']      = array('multichoice',
                          '_choices' => array('ascending', 'descending'));
$meta['pagelist_flags'] = array('string');
$meta['toolbar_icon']   = array('onoff');

//Setup VIM: ex: et ts=2 :
