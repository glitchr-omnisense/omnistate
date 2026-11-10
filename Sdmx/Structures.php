<?php

namespace Omnistate\Sdmx;

use Omnistate\Exception\UnavailableException;
use Omnistate\Model\Dataset;

/**
 * SDMX 2.1 structure messages (SDMX-ML), as INSEE's BDM and the ECB answer
 * GET /dataflow/<agency>: each dataflow as a Dataset - its id, its name in
 * the language asked (English otherwise, the first one else), the page an
 * annotation links to, the number of series an annotation counts (INSEE:
 * "Nombre de séries : 197"). Needs PHP's SimpleXML.
 */
final class Structures
{
    private const STRUCTURE = 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/structure';
    private const COMMON = 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/common';
    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @param \Closure(string): ?string|null $url the page of a dataflow, when no annotation gives one
     *
     * @return list<Dataset>
     *
     * @throws UnavailableException the answer is not a structure message
     */
    public static function dataflows(string $xml, string $source, string $language = 'en', ?\Closure $url = null): array
    {
        $root = self::read($xml, $source);
        $datasets = [];
        foreach ($root->xpath('//str:Dataflow') ?: [] as $flow) {
            $id = (string) $flow['id'];
            if ('' === $id) {
                continue;
            }
            $names = [];
            foreach ($flow->children(self::COMMON)->Name as $name) {
                $names[(string) ($name->attributes(self::XML)['lang'] ?? '')] = trim((string) $name);
            }
            $page = null;
            $series = null;
            foreach ($flow->children(self::COMMON)->Annotations->Annotation ?? [] as $annotation) {
                $common = $annotation->children(self::COMMON);
                $page ??= '' !== trim((string) $common->AnnotationURL) ? trim((string) $common->AnnotationURL) : null;
                if (1 === preg_match('/(\d[\d\s ]*)\s*$/u', (string) $common->AnnotationText, $match) && preg_match('/s[ée]ries/iu', (string) $common->AnnotationText)) {
                    $series = (int) preg_replace('/\D/', '', $match[1]);
                }
            }
            $datasets[] = new Dataset($source, $id, $names[$language] ?? $names['en'] ?? (reset($names) ?: $id), null, $page ?? (null === $url ? null : $url($id)), $series);
        }

        return $datasets;
    }

    /**
     * An SDMX-ML message, its str:, com: and mes: namespaces registered for xpath().
     *
     * @throws UnavailableException not XML, or an HTML page in its place
     */
    public static function read(string $xml, string $source): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $root = '' === trim($xml) ? false : simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (false === $root || 'html' === strtolower($root->getName())) {
            throw new UnavailableException(\sprintf('%s answered something that is not an SDMX message.', $source));
        }
        $root->registerXPathNamespace('str', self::STRUCTURE);
        $root->registerXPathNamespace('com', self::COMMON);
        $root->registerXPathNamespace('mes', 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message');

        return $root;
    }
}
