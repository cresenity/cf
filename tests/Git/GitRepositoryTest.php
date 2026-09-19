<?php
use PHPUnit\Framework\TestCase;

/**
 * CGit terhadap repositori git sementara yang dibuat di setUp — lokal, tanpa remote.
 * Ditambah parser murni: readDiffLogs() dari fixture teks dan CGit_PrettyFormat.
 */
class GitRepositoryTest extends TestCase {
    /** @var string */
    protected $path;

    /** @var CGit_Client */
    protected $client;

    /** @var CGit_Repository */
    protected $repository;

    /** @var string[] */
    protected $hashes = [];

    protected function setUp(): void {
        exec('git --version 2>/dev/null', $out, $code);
        if ($code !== 0) {
            $this->markTestSkipped('git tidak tersedia');
        }
        $this->path = rtrim(sys_get_temp_dir(), '/') . '/uji-git-' . uniqid();
        $this->client = new CGit_Client();
        $this->repository = $this->client->createRepository($this->path);
        $this->git('config user.email "uji@cf.test"');
        $this->git('config user.name "Uji CF"');
        $this->git('checkout -q -b master 2>/dev/null || git checkout -q master');
        file_put_contents($this->path . '/README.md', "# Uji\nbaris dua\n");
        mkdir($this->path . '/src');
        file_put_contents($this->path . '/src/a.php', "<?php\necho 1;\n");
        $this->git('add -A');
        $this->git('commit -q -m "Commit pertama"');
        $this->hashes[] = trim($this->git('rev-parse HEAD'));
        file_put_contents($this->path . '/README.md', "# Uji\nbaris dua diubah\nbaris tiga\n");
        $this->git('add -A');
        $this->git('commit -q -m "Ubah README" -m "Badan penjelasan"');
        $this->hashes[] = trim($this->git('rev-parse HEAD'));
    }

    protected function tearDown(): void {
        if ($this->path && is_dir($this->path)) {
            CFile::deleteDirectory($this->path);
        }
    }

    /**
     * @param string $command
     *
     * @return string
     */
    protected function git($command) {
        return (string) shell_exec('cd ' . escapeshellarg($this->path) . ' && git ' . $command . ' 2>&1');
    }

    public function testClientCreatesAndOpensRepositories() {
        $this->assertInstanceOf(CGit_Repository::class, $this->repository);
        $this->assertFileExists($this->path . '/.git/HEAD');
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', $this->client->getVersion());
        $again = $this->client->getRepository($this->path);
        $this->assertSame($this->path, $again->getPath());
        $this->assertSame(basename($this->path), $again->getName());
        try {
            $this->client->createRepository($this->path);
            $this->fail('repo sudah ada harus ditolak');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }
        $this->expectException(RuntimeException::class);
        $this->client->getRepository($this->path . '-tidak-ada');
    }

    public function testBranchesTagsAndHead() {
        $this->assertSame('master', $this->repository->getCurrentBranch());
        $this->assertTrue($this->repository->hasBranch('master'));
        $this->assertFalse($this->repository->hasBranch('fitur'));
        $this->assertSame('master', $this->repository->getHead());
        $this->assertSame([], $this->repository->getRemoteBranches());
        $this->assertNull($this->repository->getTags());

        $this->repository->createBranch('fitur');
        $this->repository->createTag('v1.0', 'rilis pertama');
        $fresh = $this->client->getRepository($this->path . '/');
        $this->assertSame(['fitur', 'master'], array_values($fresh->getBranches()));
        $this->assertSame(['v1.0'], $fresh->getTags());
        $this->assertSame('Uji CF', trim($fresh->getConfig('user.name')));
    }

    public function testCommitsAreParsedFromThePrettyFormat() {
        $commits = $this->repository->getCommits();
        $this->assertCount(2, $commits);
        $this->assertSame('2', $this->repository->getTotalCommits());
        $latest = $commits[0];
        $this->assertInstanceOf(CGit_Model_Commit::class, $latest);
        $this->assertSame($this->hashes[1], $latest->getHash());
        $this->assertSame(substr($this->hashes[1], 0, strlen($latest->getShortHash())), $latest->getShortHash());
        $this->assertSame('Ubah README', $latest->getMessage());
        $this->assertSame('Uji CF', $latest->getAuthor()->getName());
        $this->assertSame('uji@cf.test', $latest->getAuthor()->getEmail());
        $this->assertSame([$this->hashes[0]], $latest->getParentsHash());
        $this->assertInstanceOf(DateTime::class, $latest->getDate());
        $this->assertSame([], $commits[1]->getParentsHash(), 'commit akar tanpa induk');
        $this->assertCount(1, $this->repository->getCommits('src/a.php'), 'filter per berkas');
    }

    public function testGetCommitIncludesDiffsWithLineNumbers() {
        $commit = $this->repository->getCommit($this->hashes[1]);
        $this->assertSame('Ubah README', $commit->getMessage());
        $this->assertSame('Badan penjelasan', trim($commit->getBody()));
        $diffs = $commit->getDiffs();
        $this->assertCount(1, $diffs);
        $diff = $diffs[0];
        $this->assertInstanceOf(CGit_Model_Commit_Diff::class, $diff);
        $this->assertSame('README.md', $diff->getFile());
        $this->assertStringStartsWith('index ', $diff->getIndex());
        $types = array_map(function (CGit_Model_Line $line) {
            return $line->getType();
        }, $diff->getLines());
        $this->assertContains('chunk', $types);
        $this->assertContains('old', $types);
        $this->assertContains('new', $types);
        $this->assertSame(1, $commit->getChangedFiles(), 'jumlah berkas berubah');
    }

    public function testReadDiffLogsParsesAUnifiedDiffFixture() {
        $logs = [
            'diff --git a/src/a.php b/src/a.php',
            'index 1111111..2222222 100644',
            '--- a/src/a.php',
            '+++ b/src/a.php',
            '@@ -1,3 +1,4 @@',
            ' <?php',
            '-echo 1;',
            '+echo 2;',
            '+echo 3;',
            'diff --git a/bin/logo.png b/bin/logo.png',
            'Binary files a/bin/logo.png and b/bin/logo.png differ',
        ];
        $diffs = $this->repository->readDiffLogs($logs);
        $this->assertCount(2, $diffs);
        $this->assertSame('src/a.php', $diffs[0]->getFile());
        $this->assertSame('--- a/src/a.php', $diffs[0]->getOld());
        $this->assertSame('+++ b/src/a.php', $diffs[0]->getNew());
        $lines = $diffs[0]->getLines();
        $this->assertSame('chunk', $lines[0]->getType());
        $this->assertNull($lines[1]->getType(), 'baris konteks tanpa tipe');
        $this->assertSame('old', $lines[2]->getType());
        $this->assertSame('new', $lines[3]->getType());
        $this->assertSame('-echo 1;', $lines[2]->getLine());
        $this->assertSame('bin/logo.png', $diffs[1]->getFile());
        $this->assertSame('a/bin/logo.png', $diffs[1]->getOld(), 'berkas biner: nama lama/baru dari baris Binary files');
        $this->assertSame('    b/bin/logo.png', $diffs[1]->getNew());
    }

    public function testTreeAndBlobFromTheBranchTip() {
        $treeHash = $this->repository->getBranchTree('master');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $treeHash);
        try {
            $this->repository->getBranchTree('cabang-tidak-ada');
            $this->fail('cabang tak dikenal → git error → RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cabang-tidak-ada', $e->getMessage());
        }
        $tree = $this->repository->getTree($treeHash);
        $names = [];
        $entries = [];
        foreach ($tree as $entry) {
            $names[] = $entry->getName();
            $entries[$entry->getName()] = $entry;
        }
        sort($names);
        $this->assertSame(['README.md', 'src'], $names);
        $this->assertInstanceOf(CGit_Model_Blob::class, $entries['README.md']);
        $this->assertInstanceOf(CGit_Model_Tree::class, $entries['src']);
        $this->assertSame("# Uji\nbaris dua diubah\nbaris tiga\n", $entries['README.md']->output());
        $this->assertSame((string) strlen("# Uji\nbaris dua diubah\nbaris tiga\n"), (string) $entries['README.md']->getSize());
        $this->assertSame('100644', $entries['README.md']->getMode());
    }

    public function testBlameGroupsLinesByCommit() {
        $blame = $this->repository->getBlame('README.md');
        $this->assertCount(2, $blame, 'baris 1 dari commit pertama, baris 2-3 dari commit kedua');
        $this->assertStringStartsWith('^', $blame[1]['commit'], 'commit akar ditandai ^ oleh git blame (boundary)');
        $this->assertStringStartsWith(ltrim($blame[1]['commit'], '^'), $this->hashes[0]);
        $this->assertStringContainsString('# Uji', $blame[1]['line']);
        $this->assertSame(substr($this->hashes[1], 0, 8), $blame[2]['commit']);
        $this->assertStringContainsString('baris tiga', $blame[2]['line']);
    }

    public function testDateStatisticsGroupCommitsPerDay() {
        $statistics = new CGit_Statistics_Date();
        $this->repository->addStatistics($statistics);
        $this->repository->getCommits();
        $this->assertTrue($this->repository->getCommitsHaveBeenParsed());
        $statistics->sortCommits();
        $this->assertCount(1, $statistics, 'kedua commit hari ini');
        $this->assertCount(2, $statistics->first());
        $this->assertSame(date('Y-m-d'), $statistics->keys()->first());
    }

    public function testPrettyFormatParsesItemsAndEscapesControlCharacters() {
        $format = new CGit_PrettyFormat();
        $items = $format->parse('<item><hash>abc</hash><message><![CDATA[Halo]]></message></item><item><hash>def</hash><message><![CDATA[Dua]]></message></item>');
        $this->assertCount(2, $items);
        $this->assertSame('abc', $items[0]['hash']);
        $this->assertSame('Dua', $items[1]['message']);
        $items = $format->parse("<item><hash>abc</hash><message>kontrol\x01</message></item>");
        $this->assertSame('kontrol?', $items[0]['message'], 'karakter kontrol yang merusak XML diganti ?');
        $this->expectException(RuntimeException::class);
        $format->parse('');
    }
}
