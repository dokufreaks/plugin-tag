<?php
/**
 * Syntax plugin part for displaying a tag search form with results.
 *
 * Usage: {{tagsearch[&flags]}}
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Michael Hamann <michael@content-space.de>
 */

/**
 * Tagsearch syntax, displays a tag search form with results similar to the topic syntax
 */
class syntax_plugin_tag_searchtags extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax type
     */
    function getType() { return 'substition'; }

    /**
     * @return string Paragraph type
     */
    function getPType() { return 'block'; }

    /**
     * @return int Sort order
     */
    function getSort() { return 295; }

    /**
     * @param string $mode Parser mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{searchtags}}', $mode,'plugin_tag_searchtags');
        // make sure that flags really start with & and media files starting with "searchtags" still work
        $this->Lexer->addSpecialPattern('\{\{searchtags&.*?\}\}',$mode,'plugin_tag_searchtags');
    }

    /**
     * Handle matches of the searchtags syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        $flags = substr($match, 12, -2); // strip {{searchtags from start and }} from end
        // remove empty flags by using array_filter (removes elements == false)
        return array_filter(explode('&', $flags));
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $format      Renderer mode (supported modes: xhtml and metadata)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler function
     * @return bool If rendering was successful.
     */
    function render($format, Doku_Renderer $renderer, $data) {
        global $lang;
        $flags = $data;

        if ($format == 'xhtml') {
            /* @var Doku_Renderer_xhtml $renderer */

            // prevent caching to ensure content is always fresh
            $renderer->nocache();

            /* @var helper_plugin_pagelist $pagelist */
            // let Pagelist Plugin do the work for us
            if (!$pagelist = $this->loadHelper('pagelist')) {
                return false;
            }

            // Prepare the flags for the pagelist plugin
            $configflags = explode(',', str_replace(" ", "", $this->getConf('pagelist_flags')));
            $flags = array_merge($configflags, $flags);
            foreach($flags as $key => $flag) {
                if($flag == "")	{
                    unset($flags[$key]);
                }
            }

            // print the search form
            $nonsform = in_array('nonsform', $flags);
            $renderer->doc .= $this->getForm($nonsform);

            // get the tag input data
            $tags = $this->getTagSearchString();

            if ($tags != null) {
                /* @var helper_plugin_tag $helper */
                if ($helper = $this->loadHelper('tag')) {
                    $pages = $helper->getTopic($this->getNS(), '', $tags);
                }

                // Display a message when no pages were found
                if (!isset($pages) || !$pages) {
                    $renderer->p_open();
                    $renderer->cdata($lang['nothingfound']);
                    $renderer->p_close();
                } else {

                    // display the actual search results
                    $pagelist->setFlags($flags);
                    $pagelist->startList();
                    foreach ($pages as $page) {
                        $pagelist->addPage($page);
                    }
                    $renderer->doc .= $pagelist->finishList();
                }
            }

            return true;
        }
        return false;
    }

    /**
     * Return the search form for the namespace and the tag selection
     *
     * @return string the HTML code of the search form
     */
    private function getForm($nonsform=false)  {
        global $conf, $lang;

        if (!$nonsform) {
            // Get the list of all namespaces for the dropdown
            $namespaces = [];
            search($namespaces,$conf['datadir'],'search_namespaces', []);

            // build the list in the form value => label from the namespace search result
            $ns_select = ['' => ''];
            foreach ($namespaces as $ns) {
                // only display namespaces the user can access when sneaky index is on
                if ($ns['perm'] > 0 || $conf['sneaky_index'] == 0) {
                    $ns_select[$ns['id']] = $ns['id'];
                }
            }
        }

        $form = new Doku_Form(array('action' => '', 'method' => 'post', 'class' => 'plugin__tag_search'));

        // add a paragraph around the inputs in order to get some margin around the form elements
        $form->addElement(form_makeOpenTag('p'));
        // namespace select
        if (!$nonsform) {
            $form->addElement(form_makeMenuField('plugin__tag_search_namespace', $ns_select, $this->getNS(), $lang['namespaces']));
        }

        // checkbox for AND
        $attr = array();
        if ($this->useAnd()) {
            $attr['checked'] = 'checked';
        }
        $form->addElement(form_makeCheckboxField('plugin__tag_search_and', 1, $this->getLang('use_and'), '', '', $attr));
        $form->addElement(form_makeCloseTag('p'));

        // load the tag list - only tags that actually have pages assigned that the current user can access are listed
        /* @var helper_plugin_tag $my */
        if ($my = $this->loadHelper('tag')) {
            $tags = $my->tagOccurrences(array(), NULL, true);
        }
        // sort tags by name ($tags is in the form $tag => $count)
        ksort($tags);

        // display error message when no tags were found
        if (!isset($tags) || $tags == NULL) {
            $form->addElement(form_makeOpenTag('p'));
            $form->addElement($this->getLang('no_tags'));
            $form->addElement(form_makeCloseTag('p'));
        } else {
            // the tags table
            $form->addElement(form_makeOpenTag('div', array('class' => 'table')));
            $form->addElement(form_makeOpenTag('table', array('class' => 'inline')));
            // print table header
            $form->addElement(form_makeOpenTag('tr'));
            $form->addElement(form_makeOpenTag('th'));
            $form->addElement($this->getLang('include'));
            $form->addElement(form_makeCloseTag('th'));
            $form->addElement(form_makeOpenTag('th'));
            $form->addElement($this->getLang('exclude'));
            $form->addElement(form_makeCloseTag('th'));
            $form->addElement(form_makeOpenTag('th'));
            $form->addElement($this->getLang('tags'));
            $form->addElement(form_makeCloseTag('th'));
            $form->addElement(form_makeCloseTag('tr'));

            // print tag checkboxes
            foreach ($tags as $tag => $count) {
                $form->addElement(form_makeOpenTag('tr'));
                $form->addElement(form_makeOpenTag('td'));
                $attr = array();
                if ($this->isSelected($tag)) {
                    $attr['checked'] = 'checked';
                }
                $form->addElement(form_makeCheckboxField('plugin__tag_search_tags[]', $tag, '+', '', 'plus', $attr));
                $form->addElement(form_makeCloseTag('td'));
                $form->addElement(form_makeOpenTag('td'));
                $attr = array();
                if ($this->isSelected('-'.$tag)) {
                    $attr['checked'] = 'checked';
                }
                $form->addElement(form_makeCheckboxField('plugin__tag_search_tags[]', '-'.$tag, '-', '', 'minus', $attr));
                $form->addElement(form_makeCloseTag('td'));
                $form->addElement(form_makeOpenTag('td'));
                $form->addElement(hsc($tag).' ['.$count.']');
                $form->addElement(form_makeCloseTag('td'));
                $form->addElement(form_makeCloseTag('tr'));
            }

            $form->addElement(form_makeCloseTag('table'));
            $form->addElement(form_makeCloseTag('div'));

            // submit button (doesn't use the button form element because it always submits an action which is not
            // recognized for $preact in inc/actions.php and thus always causes a redirect)
            $form->addElement(form_makeOpenTag('p'));
            $form->addElement(form_makeTag('input', array('type' => 'submit', 'value' => $lang['btn_search'])));
            $form->addElement(form_makeCloseTag('p'));
        }

        return $form->getForm();
    }

    /**
     * Returns the currently selected namespace
     * @return string the cleaned namespace id
     */
    private function getNS() {
        global $INPUT;
        if ($INPUT->post->has('plugin__tag_search_namespace')) {
            return cleanID($INPUT->post->str('plugin__tag_search_namespace'));
        } else {
            return '';
        }
    }

    /**
     * Returns the tag search string from the selected tags
     * @return string|null the tag search or null when no tags were selected
     */
    private function getTagSearchString() {
        global $INPUT;
        if ($INPUT->post->has('plugin__tag_search_tags') && is_array($INPUT->post->param('plugin__tag_search_tags'))) {
            $tags = $INPUT->post->arr('plugin__tag_search_tags');
            // When 'and' is set, prepend "+" to each tag
            $plus = $this->useAnd() ? '+' : '';
            $positive_tags = '';
            $negative_tags = '';
            foreach ($tags as $tag) {
                $tag = (string)$tag;
                if ($tag[0] == '-') {
                    $negative_tags .= $tag.' ';
                } else {
                    if ($positive_tags === '') {
                        $positive_tags = $tag.' ';
                    } else {
                        $positive_tags .= $plus.$tag.' ';
                    }
                }
            }
            return $positive_tags.$negative_tags;
        } else {
            return null; // return NULL when no tags were selected so no results will be displayed
        }
    }

    /**
     * Check if a tag was selected for search
     *
     * @param string $tag The tag to check
     * @return bool if the tag was checked
     */
    private function isSelected($tag) {
        global $INPUT;
        if ($INPUT->post->has('plugin__tag_search_tags')) {
            return in_array($tag, $INPUT->post->arr('plugin__tag_search_tags'), true);
        } else {
            return false; // no tags in the post data - no tag selected
        }
    }

    /**
     * Check if the tag query should use AND (instead of OR)
     *
     * @return bool if the query should use AND
     */
    private function useAnd() {
        global $INPUT;
        return $INPUT->post->has('plugin__tag_search_and');
    }
}
// vim:ts=4:sw=4:et:
