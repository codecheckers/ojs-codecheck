<?php

namespace APP\plugins\generic\codecheck\tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PKP\tests\PKPTestCase;

/**
 * plugins.xml is the Plugin Gallery listing a journal adds to
 * `plugin_gallery_urls` (#157). OJS reads it without validating it, so a
 * listing that is wrong is only found by a journal that fails to install from
 * it. These tests are that validation.
 *
 * Deliberately not asserted: that the newest release equals version.xml. The
 * listing is updated after a release is published, so on the release branch
 * version.xml is ahead of it by design.
 */
class PluginsXmlUnitTest extends PKPTestCase
{
    private const NAMESPACE = 'http://pkp.sfu.ca';
    private const RELEASES_URL = 'https://github.com/codecheckers/ojs-codecheck/releases/download/';

    private function loadListing(): DOMDocument
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->load(dirname(__DIR__) . '/plugins.xml'), 'plugins.xml is not well-formed XML');
        return $doc;
    }

    private function query(DOMDocument $doc, string $expression, ?DOMElement $context = null): array
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('p', self::NAMESPACE);
        return iterator_to_array($xpath->query($expression, $context));
    }

    public function testListingValidatesAgainstPkpSchema()
    {
        // The schema OJS itself ships, so the listing is held to the version of
        // the format the installed OJS reads.
        $schema = BASE_SYS_DIR . '/lib/pkp/xml/schema/plugins.xsd';
        $this->assertFileExists($schema);

        $doc = $this->loadListing();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $valid = $doc->schemaValidate($schema);
            $errors = array_map(
                fn ($error) => sprintf('line %d: %s', $error->line, trim($error->message)),
                libxml_get_errors()
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $this->assertTrue($valid, "plugins.xml does not validate against {$schema}:\n" . implode("\n", $errors));
    }

    public function testListingNamesThePluginVersionXmlDescribes()
    {
        $version = simplexml_load_file(dirname(__DIR__) . '/version.xml');
        $plugins = $this->query($this->loadListing(), '/p:plugins/p:plugin');

        $this->assertCount(1, $plugins);
        // OJS installs into plugins/{category}/{product} and then compares with
        // version.xml; a mismatch installs a second copy beside the first.
        $this->assertSame((string) $version->application, $plugins[0]->getAttribute('product'));
        $this->assertSame((string) $version->type, 'plugins.' . $plugins[0]->getAttribute('category'));
    }

    public function testEveryPackageIsTheReleaseAssetOfItsVersion()
    {
        $doc = $this->loadListing();
        $releases = $this->query($doc, '/p:plugins/p:plugin/p:release');
        $this->assertNotEmpty($releases);

        foreach ($releases as $release) {
            /** @var DOMElement $release */
            $version = $release->getAttribute('version');
            $packages = $this->query($doc, 'p:package', $release);

            // The asset package-plugin.sh builds and the release uploads.
            $this->assertCount(1, $packages, "release {$version}");
            $this->assertSame(
                self::RELEASES_URL . "v{$version}/codecheck-{$version}.tar.gz",
                trim($packages[0]->textContent),
                "release {$version}"
            );
        }
    }

    public function testEveryMd5IsLowercase()
    {
        // plugins.xsd accepts either case, but OJS compares md5_file(), which
        // is lowercase, with `!==`: an uppercase md5 fails every install.
        foreach ($this->query($this->loadListing(), '/p:plugins/p:plugin/p:release') as $release) {
            /** @var DOMElement $release */
            $this->assertSame(
                strtolower($release->getAttribute('md5')),
                $release->getAttribute('md5'),
                "release {$release->getAttribute('version')}"
            );
        }
    }

    public function testNoReleaseVersionIsListedTwice()
    {
        $versions = array_map(
            fn (DOMElement $release) => $release->getAttribute('version'),
            $this->query($this->loadListing(), '/p:plugins/p:plugin/p:release')
        );

        $this->assertSame(array_values(array_unique($versions)), $versions, 'a release version appears more than once');
    }
}
