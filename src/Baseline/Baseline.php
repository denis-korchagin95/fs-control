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

use FsControl\Exception\BaselineException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_is_list;
use function in_array;
use function is_array;
use function is_string;

class Baseline
{
    public const CATEGORY_VIOLATION = 'violations';
    public const CATEGORY_UNCOVERED = 'uncovered';
    public const CATEGORY_UNBOUNDED = 'unbounded';

    private const CATEGORIES = [
        self::CATEGORY_VIOLATION,
        self::CATEGORY_UNCOVERED,
        self::CATEGORY_UNBOUNDED,
    ];

    /**
     * @param array<string, list<string>> $entries
     */
    private function __construct(
        private readonly array $entries,
    ) {
    }

    /**
     * @param list<string> $violations
     * @param list<string> $uncovered
     * @param list<string> $unbounded
     */
    public static function fromPaths(array $violations, array $uncovered, array $unbounded): self
    {
        return new self([
            self::CATEGORY_VIOLATION => $violations,
            self::CATEGORY_UNCOVERED => $uncovered,
            self::CATEGORY_UNBOUNDED => $unbounded,
        ]);
    }

    /**
     * @throws BaselineException
     */
    public static function loadFromFile(string $path): self
    {
        if (! is_file($path)) {
            throw BaselineException::cannotReadFile($path);
        }
        try {
            $rawBaseline = Yaml::parseFile($path);
        } catch (ParseException $exception) {
            throw BaselineException::malformed($path, $exception->getMessage());
        }
        if (! is_array($rawBaseline) || (array_is_list($rawBaseline) && $rawBaseline !== [])) {
            throw BaselineException::malformed($path, 'the root element must be a mapping');
        }

        $entries = [];
        foreach (self::CATEGORIES as $category) {
            $rawPaths = $rawBaseline[$category] ?? [];
            if (! is_array($rawPaths)) {
                throw BaselineException::malformed($path, 'the "' . $category . '" section must be a list');
            }
            $paths = [];
            foreach ($rawPaths as $rawPath) {
                if (! is_string($rawPath)) {
                    throw BaselineException::malformed(
                        $path,
                        'the "' . $category . '" section must contain only strings',
                    );
                }
                $paths[] = $rawPath;
            }
            $entries[$category] = $paths;
        }

        return new self($entries);
    }

    public function has(string $category, string $relativePath): bool
    {
        return in_array($relativePath, $this->entries[$category] ?? [], true);
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
