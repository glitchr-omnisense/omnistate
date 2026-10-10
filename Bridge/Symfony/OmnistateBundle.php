<?php

namespace Omnistate\Bridge\Symfony;

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\AnnuaireSante\AnnuaireSante;
use Omnistate\Bridge\Symfony\Controller\CompanySearchController;
use Omnistate\Bridge\Symfony\Form\CompanySearchType;
use Omnistate\Bridge\Symfony\Validator\VatNumberValidator;
use Omnistate\Iana\Bootstrap;
use Omnistate\Iana\Rdap;
use Omnistate\MatchId\MatchId;
use Omnistate\Nara\Nara;
use Omnistate\NationalArchivesUk\NationalArchivesUk;
use Omnistate\Omnistate;
use Omnistate\OpenArchieven\OpenArchieven;
use Omnistate\Registry\CivilRegistryInterface;
use Omnistate\Registry\CompanyRegistryInterface;
use Omnistate\Registry\InternetRegistryInterface;
use Omnistate\Registry\ProfessionalRegistryInterface;
use Omnistate\Registry\VatRegistryInterface;
use Omnistate\Vies\Vies;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnistate in a Symfony application: Omnistate\Omnistate autowired, every
 * registry package installed (omnistate/vies, omnistate/annuaire-entreprises,
 * omnistate/iana) registered, answers kept in a cache pool, and the
 * #[Siren], #[Siret] and #[VatNumber] constraints, and the company search:
 * CompanySearchType (a form field that fills its siblings from the company
 * picked) asking CompanySearchController (GET /omnistate/company/search,
 * routed by importing Bridge/Symfony/Controller/ as attributes).
 *
 *     omnistate:
 *         cache: cache.app          # null: every call asks the registry
 *         ttl: 86400
 *         timeout: 10
 *         requester: FR53901821074  # your VAT number: VIES answers a consultation number
 *         annuaire_sante:
 *             api_key: '%env(ESANTE_API_KEY)%'   # omnistate/annuaire-sante: health professionals (RPPS) and facilities (FINESS)
 *
 *         matchid:
 *             token: '%env(MATCHID_TOKEN)%'      # omnistate/matchid: optional, a higher quota
 *
 *         nara:
 *             api_key: '%env(NARA_API_KEY)%'     # omnistate/nara: the US National Archives Catalog, 10,000 calls a month
 *
 * Civil registers (omnistate/matchid, omnistate/openarchieven,
 * omnistate/national-archives-uk, omnistate/nara) are registered when
 * installed and tagged omnistate.civil_registry.
 *
 * An application's own registries (a CompanyRegistryInterface...) are asked
 * too, autoconfigured.
 */
final class OmnistateBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnistate';

    /** This directory: its templates/ are @Omnistate (the default is two levels up). */
    public function getPath(): string
    {
        return __DIR__;
    }

    /** The company search field's widget, in every form theme. */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', ['form_themes' => ['@Omnistate/form/company_search.html.twig']]);
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('cache')->defaultValue('cache.app')->info('A cache pool service; null to ask the registries every time.')->end()
                ->integerNode('ttl')->defaultValue(86400)->min(0)->info('How long an answer is kept, in seconds.')->end()
                ->floatNode('timeout')->defaultValue(10)->info('Seconds a registry has to answer.')->end()
                ->scalarNode('requester')->defaultNull()->info('Your own VAT number, sent with VAT checks: VIES answers a consultation number, the proof of the check.')->end()
                ->arrayNode('matchid')
                    ->info('omnistate/matchid: French deaths since 1970 (INSEE\'s file).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('token')->defaultNull()->info('A matchID API token (Authorization: Bearer): optional, for a higher quota.')->end()
                    ->end()
                ->end()
                ->arrayNode('nara')
                    ->info('omnistate/nara: the National Archives Catalog of the United States (Catalog API v2).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('api_key')->defaultNull()->info('The Catalog API key (header x-api-key; ask Catalog_API@nara.gov), 10,000 calls a month; empty: the Catalog answers no data, every call UnavailableException.')->end()
                    ->end()
                ->end()
                ->arrayNode('annuaire_sante')
                    ->info('omnistate/annuaire-sante: the ANS\'s FHIR API (health professionals, facilities).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('api_key')->defaultNull()->info('The Gravitee key (header ESANTE-API-KEY); empty: every call answers UnavailableException.')->end()
                        ->scalarNode('url')->defaultValue('https://gateway.api.esante.gouv.fr/fhir/v2')->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{cache: ?string, ttl: int, timeout: float, requester: ?string, matchid: array{token: ?string}, nara: array{api_key: ?string}, annuaire_sante: array{api_key: ?string, url: string}} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(CompanyRegistryInterface::class)->addTag('omnistate.company_registry');
        $builder->registerForAutoconfiguration(VatRegistryInterface::class)->addTag('omnistate.vat_registry');
        $builder->registerForAutoconfiguration(ProfessionalRegistryInterface::class)->addTag('omnistate.professional_registry');
        $builder->registerForAutoconfiguration(CivilRegistryInterface::class)->addTag('omnistate.civil_registry');

        $services = $container->services();
        $http = service('http_client');

        if (class_exists(AnnuaireEntreprises::class)) {
            $services->set(AnnuaireEntreprises::class)->args([$http, $config['timeout']])->tag('omnistate.company_registry');
        }
        if (class_exists(Vies::class)) {
            $services->set(Vies::class)->args([$http, $config['timeout']])->tag('omnistate.vat_registry');
        }
        if (class_exists(AnnuaireSante::class)) {
            $services->set(AnnuaireSante::class)
                ->args([$http, $config['annuaire_sante']['api_key'], $config['timeout'], $config['annuaire_sante']['url']])
                ->tag('omnistate.professional_registry');
        }
        if (class_exists(MatchId::class)) {
            $services->set(MatchId::class)->args([$http, $config['matchid']['token'], $config['timeout']])->tag('omnistate.civil_registry');
        }
        if (class_exists(OpenArchieven::class)) {
            $services->set(OpenArchieven::class)->args([$http, $config['timeout']])->tag('omnistate.civil_registry');
        }
        if (class_exists(NationalArchivesUk::class)) {
            $services->set(NationalArchivesUk::class)->args([$http, $config['timeout']])->tag('omnistate.civil_registry');
        }
        if (class_exists(Nara::class)) {
            $services->set(Nara::class)->args([$http, $config['nara']['api_key'], $config['timeout']])->tag('omnistate.civil_registry');
        }
        $internet = null;
        if (class_exists(Rdap::class)) {
            $services->set(Bootstrap::class)->args([$http, null === $config['cache'] ? null : service($config['cache']), $config['timeout']]);
            $services->set(Rdap::class)->args([$http, service(Bootstrap::class), $config['timeout']]);
            $services->alias(InternetRegistryInterface::class, Rdap::class);
            $internet = service(Rdap::class);
        }

        $services->set(Omnistate::class)
            ->args([
                tagged_iterator('omnistate.company_registry'),
                tagged_iterator('omnistate.vat_registry'),
                $internet,
                null === $config['cache'] ? null : service($config['cache']),
                $config['ttl'],
                $config['requester'],
                tagged_iterator('omnistate.professional_registry'),
                tagged_iterator('omnistate.civil_registry'),
            ])
            ->public();

        $services->set(VatNumberValidator::class)
            ->args([service(Omnistate::class)])
            ->tag('validator.constraint_validator');

        $services->set(CompanySearch::class)->args([service(Omnistate::class)])->public();
        $services->set(CompanySearchController::class)
            ->args([service(CompanySearch::class)])
            ->tag('controller.service_arguments')
            ->public();
        if (class_exists(AbstractType::class)) {
            $services->set(CompanySearchType::class)
                ->args([service('router')->nullOnInvalid()])
                ->tag('form.type');
        }
    }
}
