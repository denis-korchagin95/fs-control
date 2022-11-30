<?php

declare(strict_types=1);

namespace FsControl\Loader;

use FilesystemIterator;
use RecursiveDirectoryIterator;

use function array_pop;

class DirectoryTreeLoader
{
    /**
     * @param string[] $ignoreDirectoryNames
     */
    public function __construct(private readonly array $ignoreDirectoryNames)
    {
    }

    /**
     * @param string $path
     * @return iterable<string>
     */
    public function loadDirectoryTree(string $path): iterable
    {
        $flags = FilesystemIterator::KEY_AS_PATHNAME
            | FilesystemIterator::CURRENT_AS_FILEINFO
            | FilesystemIterator::SKIP_DOTS;

        $iterator = new RecursiveDirectoryIterator($path, $flags);

        $stack = [$iterator];

        while (count($stack) > 0) {
            $currentIterator = array_pop($stack);
            foreach ($currentIterator as $entry) {
                if (! $entry->isDir()) {
                    continue;
                }
                $directoryName = $entry->getFilename();
                if (in_array($directoryName, $this->ignoreDirectoryNames, true)) {
                    continue;
                }
                $stack[] = $currentIterator->getChildren();
                yield $entry->getPath() . DIRECTORY_SEPARATOR . $directoryName;
            }
        }
    }
}
