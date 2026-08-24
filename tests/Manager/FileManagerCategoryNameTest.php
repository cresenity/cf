<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers `CManager_File_Connector_FileManager_FM::getCategoryName()`, which
 * joins `root_path` with the folder category name and is what every
 * filemanager URL and storage path is built on.
 *
 * The bug this guards against was a missing second argument: `rtrim($rootPath)`
 * strips whitespace, not slashes, so a `root_path` ending in `/` produced a
 * doubled separator. It surfaced as an unreachable S3 object URL
 * (`.../filemanager/networkAccount//files/thumbs/...`) rather than as an error,
 * which is why it went unnoticed.
 */
class FileManagerCategoryNameTest extends TestCase {
    /**
     * @param string $rootPath
     *
     * @return string
     */
    protected function categoryNameFor($rootPath) {
        $fm = new CManager_File_Connector_FileManager_FM([
            'root_path' => $rootPath,
            'folder_categories' => [
                'file' => ['folder_name' => 'files'],
            ],
        ]);

        return $fm->getCategoryName();
    }

    public function testTrailingSlashDoesNotProduceDoubleSeparator() {
        $this->assertSame('filemanager/networkAccount/files', $this->categoryNameFor('filemanager/networkAccount/'));
    }

    public function testWithoutTrailingSlashGivesSameResult() {
        $this->assertSame('filemanager/networkAccount/files', $this->categoryNameFor('filemanager/networkAccount'));
    }

    public function testSeveralTrailingSlashesAreAllRemoved() {
        $this->assertSame('filemanager/networkAccount/files', $this->categoryNameFor('filemanager/networkAccount///'));
    }

    public function testLeadingSlashIsStripped() {
        $this->assertSame('filemanager/files', $this->categoryNameFor('/filemanager/'));
    }

    /**
     * Only the join is normalised - leading and trailing slashes on
     * `root_path`, however many. A `//` in the middle of the configured value
     * is passed through untouched, so `a//b` still yields `a//b/files`; that is
     * a malformed config value, not something this method repairs.
     */
    public function testLeadingAndTrailingSlashesNeverProduceDoubleSeparator() {
        foreach (['a/', 'a', 'a//', '/a/', '///a///'] as $rootPath) {
            $this->assertSame('a/files', $this->categoryNameFor($rootPath), 'root_path: ' . $rootPath);
        }
    }
}
