<?php

namespace Omnistate\Tests\Bridge;

use Omnisense\Contracts\Http\Data;
use Omnistate\Bridge\Symfony\CompanySearch;
use Omnistate\Bridge\Symfony\Controller\CompanySearchController;
use Omnistate\Bridge\Symfony\Form\CompanySearchType;
use Omnistate\Exception\UnavailableException;
use Omnistate\Model\Address;
use Omnistate\Model\Company;
use Omnistate\Model\CompanyStatus;
use Omnistate\Model\Establishment;
use Omnistate\Omnistate;
use Omnistate\Registry\CompanyRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;

final class CompanySearchTest extends TestCase
{
    public function testACompanyFoundByNameComesWithItsVatNumberAndHeadOffice(): void
    {
        $search = new CompanySearch(new Omnistate([$this->registry([$this->exampleShop()])]));

        $rows = $search->search('example shop');

        self::assertCount(1, $rows);
        self::assertSame('100000009', $rows[0]['siren']);
        self::assertSame('SARL EXAMPLE SHOP', $rows[0]['name']);
        self::assertSame('FR88100000009', $rows[0]['vatNumber'], 'derived from the SIREN');
        self::assertSame('10000000900017', $rows[0]['siret']);
        self::assertSame('1 RUE DES EXEMPLES 67000 STRASBOURG', $rows[0]['address']);
        self::assertTrue($rows[0]['active']);
    }

    public function testTooShortAQueryAsksNobody(): void
    {
        $registry = $this->registry([$this->exampleShop()]);
        $search = new CompanySearch(new Omnistate([$registry]));

        self::assertSame([], $search->search('la'));
        self::assertSame(0, $registry->asked, 'no request for two letters');
    }

    public function testTheEndpointAnswersTheRowsOr503(): void
    {
        $found = (new CompanySearchController(new CompanySearch(new Omnistate([$this->registry([$this->exampleShop()])]))))(new Request(['q' => 'example shop']));
        self::assertSame(200, $found->getStatusCode());
        self::assertSame('FR88100000009', Data::of(json_decode((string) $found->getContent(), true))->get('results.0.vatNumber'));

        $down = (new CompanySearchController(new CompanySearch(new Omnistate([$this->registry([], new UnavailableException('429'))]))))(new Request(['q' => 'example shop']));
        self::assertSame(503, $down->getStatusCode());
    }

    public function testTheFieldPointsItsSiblingsOutToTheScript(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType(new CompanySearchType())->getFormFactory();
        $form = $factory->createNamedBuilder('store', FormType::class)
            ->add('company', CompanySearchType::class, ['fill' => ['vatNumber' => 'vatNumber', 'missing' => 'name']])
            ->add('vatNumber', TextType::class)
            ->getForm();

        $attr = $form->createView()['company']->vars['attr'];
        self::assertIsArray($attr);
        self::assertIsString($attr['data-omnistate-fill']);

        self::assertSame('/omnistate/company/search', $attr['data-omnistate-company-search'], 'no router: the default path');
        self::assertSame(['store_vatNumber' => 'vatNumber'], json_decode($attr['data-omnistate-fill'], true), 'only the siblings that exist');
        self::assertFalse($form->get('company')->getConfig()->getMapped(), 'it only helps fill the others');
    }

    private function exampleShop(): Company
    {
        return new Company(
            identifier: '100000009',
            name: 'EXAMPLE SHOP',
            status: CompanyStatus::ACTIVE,
            country: 'FR',
            source: 'annuaire-entreprises',
            legalName: 'SARL EXAMPLE SHOP',
            headOffice: new Establishment('10000000900017', true, CompanyStatus::ACTIVE, new Address('1 RUE DES EXEMPLES', '67000', 'STRASBOURG')),
        );
    }

    /**
     * @param list<Company> $companies
     *
     * @return CompanyRegistryInterface&object{asked: int}
     */
    private function registry(array $companies, ?\Throwable $failure = null): CompanyRegistryInterface
    {
        return new class($companies, $failure) implements CompanyRegistryInterface {
            public int $asked = 0;

            /** @param list<Company> $companies */
            public function __construct(private readonly array $companies, private readonly ?\Throwable $failure)
            {
            }

            public function name(): string
            {
                return 'fake';
            }

            public function supports(string $identifier): bool
            {
                return true;
            }

            public function company(string $identifier): ?Company
            {
                return $this->companies[0] ?? null;
            }

            public function search(string $query, int $limit = 10): array
            {
                ++$this->asked;
                if (null !== $this->failure) {
                    throw $this->failure;
                }

                return \array_slice($this->companies, 0, $limit);
            }
        };
    }
}
