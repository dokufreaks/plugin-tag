<?php
/**
 * Options for the Tag Plugin
 */
$conf['namespace']          = 'tag';       // where should tag links point to? default: 'tag'
$conf['sortkey']            = 'title';     // sort key for topic lists
$conf['sortorder']          = 'ascending'; // ascending or descending
$conf['pagelist_flags']     = 'list';      // formatting options for the page list plugin
$conf['toolbar_icon']       = 0;	       // enables/disables the toolbar icon
$conf['list_tags_of_subns'] = 0;           // list also tags in subnamespaces of a specified namespace (count syntax)

$conf['style']              = 'table';
$conf['withsize']           = false;
$conf['max_fontsize']       = 30;
$conf['min_fontsize']       = 10;
$conf['fontsize_unit']      = 'px';


//Setup VIM: ex: et ts=2 :
