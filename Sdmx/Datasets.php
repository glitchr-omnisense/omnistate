<?php

namespace Omnistate\Sdmx;

use Omnistate\Model\Dataset;

/**
 * The datasets of a source's catalogue that match a query: every word of
 * it in the dataset's id or title, accents and case aside. Its id itself
 * first, then the titles that start with the query, then the others, in
 * the catalogue's order.
 */
final class Datasets
{
    /**
     * @param iterable<Dataset> $datasets
     *
     * @return list<Dataset>
     */
    public static function search(iterable $datasets, string $query, int $limit = 10): array
    {
        $query = self::fold($query);
        $words = array_values(array_filter(preg_split('/[^a-z0-9_]+/', $query) ?: [], static fn (string $word): bool => '' !== $word));
        if ([] === $words || $limit < 1) {
            return [];
        }
        $ranked = [];
        foreach ($datasets as $position => $dataset) {
            $id = self::fold($dataset->id);
            $title = self::fold($dataset->title);
            $haystack = $id.' '.$title;
            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    continue 2;
                }
            }
            $rank = $id === $query ? 0 : (str_starts_with($title, $query) ? 1 : 2);
            $ranked[] = [$rank, $position, $dataset];
        }
        usort($ranked, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_slice(array_column($ranked, 2), 0, $limit);
    }

    /** Lower case, without the accents of Latin letters. */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower(trim($text)), [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss', '’' => "'",
        ]);
    }
}
