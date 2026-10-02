<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use APP\plugins\generic\codecheck\classes\migration\GalleryUpgradeMigration;
use DOMDocument;
use PKP\tests\PKPTestCase;
use PKP\xml\PKPXMLParser;

/**
 * A Plugin Gallery upgrade replaces the plugin's files and records the new
 * version, and runs a migration only if the package carries `upgrade.xml`. The
 * file names a class, so a rename would turn it into a migration that fails —
 * or, if the element went missing, into one that runs nothing — and only a
 * journal that upgrades would find out. These tests are that check; running it
 * against a database is `make check-migration UPGRADE_XML=1`.
 */
class UpgradeXmlUnitTest extends PKPTestCase
{
    private function loadDescriptor(): DOMDocument
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->load(dirname(__DIR__) . '/upgrade.xml'), 'upgrade.xml is not well-formed XML');

        return $doc;
    }

    public function testTheDescriptorRunsTheGuardedUpgradeMigrationOnce()
    {
        $migrations = $this->loadDescriptor()->getElementsByTagName('migration');

        $this->assertSame(1, $migrations->length, 'one migration element: it runs the install migration, which calls every upgrade step');
        $this->assertSame(GalleryUpgradeMigration::class, $migrations->item(0)->getAttribute('class'));
    }

    /**
     * Read the way the installer reads it, so a descriptor the installer would
     * refuse ("installer.installFileError", which fails the whole upgrade) fails
     * here instead.
     */
    public function testOjsParsesTheDescriptor()
    {
        $tree = (new PKPXMLParser())->parse(dirname(__DIR__) . '/upgrade.xml');

        $this->assertNotNull($tree, 'PKPXMLParser could not parse upgrade.xml');
        $this->assertSame('install', $tree->getName());

        $migrations = array_values(array_filter($tree->getChildren(), fn ($node) => $node->getName() === 'migration'));
        $this->assertCount(1, $migrations);
        $this->assertSame(GalleryUpgradeMigration::class, $migrations[0]->getAttribute('class'));
        $this->assertStringContainsString('\\', $migrations[0]->getAttribute('class'), 'fully qualified, which is how the installer autoloads it');
    }

    /**
     * The installer records a descriptor's `version` as OJS's own when it is
     * newer than the installed one, so a plugin's descriptor must not carry one.
     */
    public function testTheDescriptorDoesNotClaimAnOjsVersion()
    {
        $this->assertFalse($this->loadDescriptor()->documentElement->hasAttribute('version'));
    }

    public function testTheNamedMigrationIsOneThePluginCanRun()
    {
        $class = $this->loadDescriptor()->getElementsByTagName('migration')->item(0)->getAttribute('class');

        $this->assertTrue(class_exists($class), "{$class} does not exist");
        $this->assertTrue(is_subclass_of($class, CodecheckMigration::class), "{$class} is not a CodecheckMigration");
    }

    /**
     * The release package is a `git archive`, which leaves out what
     * `.gitattributes` marks `export-ignore`; asking git, rather than reading
     * the file, also covers a pattern that matches by glob.
     */
    public function testTheDescriptorIsNotExcludedFromThePackage()
    {
        $dir = escapeshellarg(dirname(__DIR__));
        $answer = trim((string) shell_exec("git -C {$dir} check-attr export-ignore -- upgrade.xml 2>&1"));

        $this->assertSame('upgrade.xml: export-ignore: unspecified', $answer);
    }
}
