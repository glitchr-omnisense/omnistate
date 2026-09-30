<?php

namespace Omnistate\Bridge\Symfony\Controller;

use Omnistate\Bridge\Symfony\CompanySearch;
use Omnistate\Exception\OmnistateException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /omnistate/company/search?q=example shop[&limit=10]:
 * {results: [...]} (CompanySearch::row()), 503 when no registry answers.
 * What the company search field asks; answers are kept by Omnistate's cache.
 *
 * Routed by importing the directory (type: attribute), or by a subclass in a
 * directory already imported - base-bundle-market does, so every shop has it.
 */
class CompanySearchController
{
    public function __construct(private readonly CompanySearch $companies)
    {
    }

    #[Route('/omnistate/company/search', name: 'omnistate_company_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $results = $this->companies->search((string) $request->query->get('q', ''), $request->query->getInt('limit', 10));
        } catch (OmnistateException $e) {
            return new JsonResponse(['results' => [], 'error' => 'unavailable'], 503);
        }

        return new JsonResponse(['results' => $results], 200, ['Cache-Control' => 'private, max-age=3600']);
    }
}
