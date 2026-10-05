<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CodecheckIssuelabelsUnitTest.php
 *
 * @class CodecheckIssueLabelsUnitTest
 *
 * @brief Tests for the CodecheckIssueLabels class
 */
class CodecheckIssueLabelsUnitTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testAddLabels()
    {
        $labels = ['l1', 'l2'];
        $codecheckIssueLabels = new CodecheckIssueLabels($labels);
        $codecheckIssueLabels->add('l3');
        $codecheckIssueLabels->addLabelArray(['l4', 'l5']);
        $this->assertCount(5, $codecheckIssueLabels->get()->toArray());
    }

    /**
     * The venue list's labels, without the plugin's own and without entries
     * that carry none. Reading and storing the list needs the network and the
     * database, so only what is taken from it is tested here.
     */
    public function testLabelsFromTheVenueList()
    {
        $this->assertSame(
            ['lifecycle journal', 'check-nl', 'preprint'],
            CodecheckIssueLabels::labelsFrom([
                ['Issue label' => 'lifecycle journal'],
                ['Issue label' => ' check-nl '],
                ['Issue label' => 'development'],
                ['Issue label' => 'id assigned'],
                ['Issue label' => 'preprint'],
                ['Issue label' => 'preprint'],
                ['Issue label' => ''],
                ['Venue type' => 'journal'],
                'not a venue',
            ])
        );
    }

    public function testAListWithoutLabelsGivesNone()
    {
        $this->assertSame([], CodecheckIssueLabels::labelsFrom([]));
        $this->assertSame([], CodecheckIssueLabels::labelsFrom(['error' => 'Not Found']));
    }
}
