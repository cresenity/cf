<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 3, 2019, 1:27:47 PM
 */
class CGit_Model_Tree extends CGit_Model_GitObject implements \RecursiveIterator {
    protected $mode;

    protected $name;

    protected $data;

    protected $position = 0;

    public function __construct($hash, CGit_Repository $repository) {
        $this->setHash($hash);
        $this->setRepository($repository);
    }

    public function parse() {
        $data = $this->getRepository()->getClient()->run($this->getRepository(), 'ls-tree -lz ' . $this->getHash());
        $lines = explode("\0", $data);
        $files = [];
        $root = [];
        foreach ($lines as $key => $line) {
            if (empty($line)) {
                unset($lines[$key]);
                continue;
            }
            $files[] = preg_split("/[\s]+/", $line, 5);
        }
        foreach ($files as $file) {
            if ('commit' == $file[1]) {
                // submodule
                continue;
            }
            if ('120000' == $file[0]) {
                $show = $this->getRepository()->getClient()->run($this->getRepository(), 'show ' . $file[2]);
                $tree = new CGit_Model_Symlink();
                $tree->setMode($file[0]);
                $tree->setName($file[4]);
                $tree->setPath($show);
                $root[] = $tree;
                continue;
            }
            if ('blob' == $file[1]) {
                $blob = new CGit_Model_Blob($file[2], $this->getRepository());
                $blob->setMode($file[0]);
                $blob->setName($file[4]);
                $blob->setSize($file[3]);
                $root[] = $blob;
                continue;
            }
            $tree = new CGit_Model_Tree($file[2], $this->getRepository());
            $tree->setMode($file[0]);
            $tree->setName($file[4]);
            $root[] = $tree;
        }
        $this->data = $root;
    }

    public function output() {
        $files = $folders = [];
        foreach ($this as $node) {
            if ($node instanceof CGit_Model_Blob) {
                $file['type'] = 'blob';
                $file['name'] = $node->getName();
                $file['size'] = $node->getSize();
                $file['mode'] = $node->getMode();
                $file['hash'] = $node->getHash();
                $files[] = $file;
                continue;
            }
            if ($node instanceof CGit_Model_Tree) {
                $folder['type'] = 'folder';
                $folder['name'] = $node->getName();
                $folder['size'] = '';
                $folder['mode'] = $node->getMode();
                $folder['hash'] = $node->getHash();
                $folders[] = $folder;
                continue;
            }
            if ($node instanceof CGit_Model_Symlink) {
                $folder['type'] = 'symlink';
                $folder['name'] = $node->getName();
                $folder['size'] = '';
                $folder['mode'] = $node->getMode();
                $folder['hash'] = '';
                $folder['path'] = $node->getPath();
                $folders[] = $folder;
            }
        }
        // Little hack to make folders appear before files
        $files = array_merge($folders, $files);
        return $files;
    }

    public function valid() {
        return isset($this->data[$this->position]);
    }

    public function hasChildren() {
        return is_array($this->data[$this->position]);
    }

    public function next() {
        $this->position++;
    }

    public function current() {
        return $this->data[$this->position];
    }

    public function getChildren() {
        return $this->data[$this->position];
    }

    public function rewind() {
        $this->position = 0;
    }

    public function key() {
        return $this->position;
    }

    public function getMode() {
        return $this->mode;
    }

    public function setMode($mode) {
        $this->mode = $mode;
        return $this;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function isTree() {
        return true;
    }
}
