<?php

namespace Omnistate\Tests;

use Omnistate\Bridge\Symfony\OmnistateBundle;
use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Iana\Rdap;
use Omnistate\Omnistate;
use Omnistate\Vies\Vies;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnistate outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds Omnistate\Omnistate by hand, asks it for a company, a VAT number
 * and a domain from the answers kept in docker/harness/recorded/, and reports
 * every class PHP loaded on the way and every file since the autoloader.
 * None may be a framework's.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Form|Routing|Validator)|Symfony\\\\Bundle|Symfony\\\\Bridge|Doctrine|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|form|routing|validator|[a-z-]*bundle|[a-z-]*bridge)|doctrine|twig)/~';

    public function testOmnistateIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertContains(Omnistate::class, $report['symbols'], 'Omnistate was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        $installed = array_values(array_filter(array_column(require __DIR__.'/../docker/harness/plugins.php', 1), 'class_exists'));
        foreach ($installed as $registry) {
            self::assertContains($registry, $report['symbols'], 'every source package installed, its registry built');
        }
        self::assertCount(\count($installed), array_merge(...array_values($report['registries'])));
    }

    public function testRegistriesAnswerFromRecordedAnswersWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(AnnuaireEntreprises::class) || !class_exists(Vies::class) || !class_exists(Rdap::class)) {
            self::markTestSkipped('omnistate/annuaire-entreprises, omnistate/vies and omnistate/iana are not all installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertTrue($report['recorded']);
        self::assertSame([], $report['unavailable']);
        self::assertSame(['identifier' => '901821074', 'name' => 'GLITCH ART', 'legal_form' => 'SARL', 'status' => 'active', 'created' => '2021-07-03', 'head_office' => '90182107400019', 'city' => 'ILLKIRCH-GRAFFENSTADEN', 'vat' => 'FR53901821074', 'source' => 'annuaire-entreprises'], $report['company']);
        self::assertSame(['FR53901821074', true, 'SARL GLITCH ART', 'vies'], [$report['vat']['number'], $report['vat']['valid'], $report['vat']['name'], $report['vat']['source']]);
        self::assertSame(['glitchr.dev', 'Gandi SAS', '2022-05-19', '2027-05-19'], [$report['domain']['name'], $report['domain']['registrar'], $report['domain']['registered'], $report['domain']['expires']]);
        self::assertSame('https://pubapi.registry.google/rdap/', $report['domain']['server'], 'found through IANA\'s bootstrap');
        self::assertContains('Symfony\\Component\\HttpClient\\MockHttpClient', $report['symbols'], 'the answers came through the HTTP client given');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNISTATE_AUTOLOAD"); $autoloaded = get_included_files(); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => array_values(array_diff(get_included_files(), $autoloaded))]);', '--', OmnistateBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array{symbols: list<string>, files: list<string>} $report
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        return [...array_values(preg_grep(self::FRAMEWORK, $report['symbols'])), ...array_values(preg_grep(self::FRAMEWORK_FILES, $report['files']))];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<string, mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNISTATE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
