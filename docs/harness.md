# The Docker harness

`docker/` runs this package with every `omnistate/*` source installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNISTATE_PLUGINS=../..` is set in `docker/.env` -
against the real registries.

```sh
cd docker && cp .env.dist .env       # nothing is needed: the registries asked are open
docker compose run --rm omnistate bare
```

| Command | |
|---|---|
| `bare` | plain PHP: `Omnistate\Omnistate` built by hand, a company, a VAT number and a domain asked, what PHP loaded |
| `bare --recorded` | the same on the answers kept in `docker/harness/recorded/`, without a call |
| `bare --json` | the same, whole: every class loaded, every file since the autoloader |
| `test` | every package's tests |

## Bare: no bundle, no container

One PHP script, `docker/harness/bin/bare`, that requires the autoloader and nothing else. It
builds `Omnistate\Omnistate` from the source packages installed - each registry given the HTTP
client to call with -, says which registries it has, then asks for the company 901821074
(Recherche d'entreprises), the VAT number FR53901821074 (VIES) and the domain glitchr.dev
(IANA's RDAP bootstrap, then the registry's server): four calls to open services, no key. The
registries that take a key or are asked about people (annuaire-sante, the civil registers) are
built and named, not called. Then it lists what PHP loaded and exits 1 if a class of a framework
is among it (`Symfony\Component\DependencyInjection`, `Config`, `HttpKernel`, `HttpFoundation`,
`Form`, `Routing`, `Validator`, a bundle, a bridge, Doctrine, Twig):

```
$ docker compose run --rm omnistate bare
Omnistate in bare PHP: Omnistate\Omnistate built by hand, no bundle, no container.

  companies      annuaire-entreprises
  vat            vies
  internet       iana
  professionals  annuaire-sante
  civil          matchid, openarchieven, national-archives-uk, nara
                 matchid covers FR
                 openarchieven covers NL BE FR SR
                 national-archives-uk covers GB
                 nara covers US

The company 901821074, asked of recherche-entreprises.api.gouv.fr:
  GLITCH ART, SARL, active, created on 2021-07-03
  head office 90182107400019 in ILLKIRCH-GRAFFENSTADEN, VAT FR53901821074

The domain glitchr.dev, asked of https://pubapi.registry.google/rdap/ (found through IANA's bootstrap):
  registrar Gandi SAS, registered on 2022-05-19, expires on 2027-05-19
  name servers ns-1-b.gandi.net, ns-210-c.gandi.net, ns-238-a.gandi.net

The VAT number FR53901821074: the registry did not answer (VIES: MS_MAX_CONCURRENT_REQ.) - that says nothing about it, ask again later.

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, Form, Routing, Validator, a bundle, a bridge, Doctrine, Twig): none
```

(as answered on 2026-10-05: that day France's VIES service was refusing calls - a registry that
does not answer is said, never read as "not valid". With `--recorded`, the VAT number reads
`valid, SARL GLITCH ART, 5 RUE VINCENT SCOTTO, 67400 ILLKIRCH GRAFFENSTADEN`, as answered on
2026-09-29.)

`Tests/BareTest.php` runs `bare --recorded --json` in a process of its own and checks the list.
The files Composer requires together with the autoloader are not counted: they are loaded by
`vendor/autoload.php` itself, whatever the script does.

The image is `php:8.4-cli-alpine` with Composer and `intl`; the harness's packages live in the
`harness` volume of the `omnistate-harness` project. The sources are cloned from GitHub as plain
git repositories over HTTPS: no GitHub API (anonymous calls are rate limited), no ssh in the
image.
