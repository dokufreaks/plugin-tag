<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the tag plugin
 *
 * @author    Esther Brunner <wikidesign@gmail.com>
 */

$meta['namespace']          = array('string');
$meta['sortkey']            = array('multichoice',
                                    '_choices' => array('cdate', 'mdate', 'pagename', 'id', 'ns', 'title'));
$meta['sortorder']          = array('multichoice',
                                    '_choices' => array('ascending', 'descending'));
$meta['pagelist_flags']     = array('string');
$meta['toolbar_icon']       = array('onoff');
$meta['list_tags_of_subns'] = array('onoff');
$meta['style']              = array('multichoice',
                                    '_choices' => array('table', 'inline'));
$meta['withsize']           = array('onoff');
$meta['max_fontsize']       = array('numeric','_pattern' => '/[0-9]+/');
$meta['min_fontsize']       = array('numeric','_pattern' => '/[0-9]+/');
$meta['fontsize_unit']      = array('multichoice',
                                    '_choices' => array('px', 'pt'));

//Setup VIM: ex: et ts=2 :
