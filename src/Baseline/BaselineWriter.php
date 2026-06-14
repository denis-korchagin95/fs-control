<?php

/*
 * This file is part of fs-control.
 *
 * (c) Denis Korchagin <denis.korchagin.1995@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FsControl\Baseline;

use FsControl\Core\Result;
use FsControl\Exception\BaselineException;
use Symfony\Component\Yaml\Yaml;

use function array_unique;
use function array_values;
use function count;
use function sort;

class BaselineWriter
{
    public function __construct(
        private readonly PathNormalizer $normalizer,
    ) {
    }

    public function generate(Result $result): string
    {
        $data = [];
        $sections = [
            Baseline::CATEGORY_VIOLATION => $result->getViolationPaths(),
            Baseline::CATEGORY_UNCOVERED => $result->getUncoveredPaths(),
            Baseline::CATEGORY_UNBOUNDED => $result->getUnboundedPaths(),
        ];
        foreach ($sections as $category => $paths) {
            $relativePaths = $this->collectRelativePaths($paths);
            if (count($relativePaths) > 0) {
                $data[$category] = $relativePaths;
            }
        }

        if (count($data) === 0) {
            return '# No findings to baseline.' . PHP_EOL;
        }

        return Yaml::dump($data, 4, 4);
    }

    /**
     * @throws BaselineException
     */
    public function writeToFile(Result $result, string $path): void
    {
        if (file_put_contents($path, $this->generate($result)) === false) {
            throw BaselineException::cannotWriteFile($path);
        }
    }

    /**
     * @param array<int, array<string, string>> $paths
     *
     * @return list<string>
     */
    private function collectRelativePaths(array $paths): array
    {
        $relativePaths = [];
        foreach ($paths as $path) {
            $relativePaths[] = $this->normalizer->toRelative($path['path']);
        }
        $relativePaths = array_values(array_unique($relativePaths));
        sort($relativePaths, SORT_STRING);
        return $relativePaths;
    }
}
