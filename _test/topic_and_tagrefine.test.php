<?php

if (!defined('DOKU_INC')) die();

/**
 * Tests the tagRefine function of the tag plugin
 */
class plugin_tag_topic_and_tagrefine_test extends DokuWikiTest {
    private $all_pages = [
        'tagged_page' => ['id' => 'tagged_page'],
        'negative_page' => ['id' => 'negative_page'],
        'third_page' => ['id' => 'third_page']
    ];
    public function setUp() : void {
        $this->pluginsEnabled[] = 'tag';
        parent::setUp();

        saveWikiText(
            'tagged_page',
            '{{tag>mytag test2tag}}', 'Test'
        );
        saveWikiText(
            'negative_page',
            '{{tag>negative_tag mytag}}',
            'Test setup'
        );
        saveWikiText(
            'third_page',
            '{{tag>third_tag}}',
            'Test setup'
        );
        idx_addPage('tagged_page');
        idx_addPage('negative_page');
        idx_addPage('third_page');
    }

    public function testEmptyTag() {
        $this->assertTopicRefine(['tagged_page', 'negative_page', 'third_page'], '');
    }

    public function testOnlyNegative() {
        $this->assertTopicRefine(['tagged_page', 'third_page'], '-negative_tag');
    }

    public function testMixed() {
        $this->assertTopicRefine(['tagged_page'], 'mytag -negative_tag');

    }

    public function testAnd() {
        $this->assertTopicRefine(['tagged_page'], '+mytag +test2tag');
    }

    public function testAndOr() {
        $this->assertTopicRefine(['tagged_page',  'third_page'], '+test2tag third_tag');
    }

    public function testOrAnd() {
        $this->assertTopicRefine(['tagged_page'], 'mytag +test2tag');
    }

    public function testRefineDoesntAdd() {
        /** @var helper_plugin_tag $helper */
        $helper = plugin_load('helper', 'tag');
        $pages = $helper->tagRefine([], 'mytag');
        $this->hasPages([], $pages, 'Refine with empty input array and "mytag" query: ');
    }

    /**
     * Test if the getTopic and the tagRefine function with all pages as input both return the expected pages
     *
     * @param array  $expected expected page ids
     * @param string $query    the query for the tagRefine/getTopic-functions
     */
    private function assertTopicRefine($expected, $query) {
        /** @var helper_plugin_tag $helper */
        $helper = plugin_load('helper', 'tag');
        $pages = $helper->tagRefine($this->all_pages, $query);
        $this->hasPages($expected, $pages, 'Refine: '.$query.': ');
        $pages = $helper->getTopic('', '', $query);
        $this->hasPages($expected, $pages, 'Topic: '.$query.': ');
    }

    /**
     * Makes sure that all pages were found and not more
     *
     * @param array $expected List of page ids
     * @param array $actual   Result list from getTopic/tagRefine
     * @param string $msg_prefix A prefix that is prepended to all messages
     */
    private function hasPages($expected, $actual, $msg_prefix = '') {
        foreach ($expected as $id) {
            $found = false;
            foreach ($actual as $page) {
                if ($page['id'] === $id) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, $msg_prefix.'Page '.$id.' expected but not found in the result');
        }

        foreach ($actual as $page) {
            $this->assertTrue(in_array($page['id'], $expected), $msg_prefix.'Page '.$page['id'].' is in the result but wasn\'t expected');
        }
    }
}
