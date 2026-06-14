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

use FsControl\Core\PathNormalizer;
use FsControl\Core\Result;
use FsControl\Exception\BaselineException;
use Symfony\Component\Yaml\Yaml;

use function array_unique;
use function array_values;
use function count;
use function ksort;
use function sort;
use function strpos;
use function substr;

class BaselineWriter
{
    public function __construct(
        private readonly PathNormalizer $normalizer,
    ) {
    }

    /**
     * @param array<string, list<string>> $extensionFindings namespaced category => identities
     */
    public function generate(Result $result, array $extensionFindings = []): string
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

        $extensions = $this->buildExtensionsTree($extensionFindings);
        if (count($extensions) > 0) {
            $data['extensions'] = $extensions;
        }

        if (count($data) === 0) {
            return '# No findings to baseline.' . PHP_EOL;
        }

        return Yaml::dump($data, 10, 4);
    }

    /**
     * @param array<string, list<string>> $extensionFindings namespaced category => identities
     *
     * @throws BaselineException
     */
    public function writeToFile(Result $result, string $path, array $extensionFindings = []): void
    {
        if (file_put_contents($path, $this->generate($result, $extensionFindings)) === false) {
            throw BaselineException::cannotWriteFile($path);
        }
    }

    /**
     * Turns flat "<extensionKey>:<subCategory>" keys into a sorted nested tree.
     *
     * @param array<string, list<string>> $extensionFindings
     *
     * @return array<string, array<string, list<string>>>
     */
    private function buildExtensionsTree(array $extensionFindings): array
    {
        $extensions = [];
        foreach ($extensionFindings as $namespacedCategory => $identities) {
            if (count($identities) === 0) {
                continue;
            }
            $position = strpos($namespacedCategory, ':');
            if ($position === false) {
                continue;
            }
            $extensionKey = substr($namespacedCategory, 0, $position);
            $subCategory = substr($namespacedCategory, $position + 1);

            $values = array_values(array_unique($identities));
            sort($values, SORT_STRING);
            $extensions[$extensionKey][$subCategory] = $values;
        }

        ksort($extensions);
        foreach ($extensions as &$subCategories) {
            ksort($subCategories);
        }
        unset($subCategories);

        return $extensions;
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
