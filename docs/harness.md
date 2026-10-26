---
title: The Docker harness
order: 70
---

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
| `test` | every package's tests (PHPUnit; arguments after `test` are passed on) |
| `composer ...`, `sh`, `php ...` | run as they are, in `/harness` |

## Settings

`docker/.env` (copied from `docker/.env.dist`, git-ignored) holds two variables, both optional:

| Variable | | 
|---|---|
| `OMNISTATE_PLUGINS` | where the sources' checkouts are, relative to `docker/` (`../..` when this core sits in the workspace beside them); unset, `docker/plugins` (empty) is mounted and every source comes from GitHub |
| `OMNISTATE_REQUESTER` | your own VAT number, sent with `bare`'s VAT check: VIES then answers a consultation number |

`bare` also reads `OMNISTATE_AUTOLOAD`, the autoloader to require in place of the harness's
`/harness/vendor/autoload.php`.

At each start the entrypoint runs `docker/harness/setup.php`, which writes the harness's
`composer.json` and `phpunit.xml` in the `harness` volume: this core from its checkout, each
source (the list is `docker/harness/plugins.php`) from its checkout under `OMNISTATE_PLUGINS` when
one is there, else from `https://github.com/glitchr-omnisense/omnistate-<slug>.git`, branch 1.x; one
PHPUnit suite per package. Composer installs when there is no `vendor/`, and updates when
`composer.json` changed.

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

Run again on 2026-10-25 (the same image, against the workspace's installed packages): the same
lines, France's VIES service again answering `MS_MAX_CONCURRENT_REQ`; with `--recorded`, the VAT
line `valid, SARL GLITCH ART, 5 RUE VINCENT SCOTTO, 67400 ILLKIRCH GRAFFENSTADEN`. Every package's
tests, the same day: `OK (124 tests, 718 assertions)`.

`Tests/BareTest.php` runs `bare --recorded --json` in a process of its own and checks the list.
The files Composer requires together with the autoloader are not counted: they are loaded by
`vendor/autoload.php` itself, whatever the script does.

The image is `php:8.4-cli-alpine` with Composer and `intl`; the harness's packages live in the
`harness` volume of the `omnistate-harness` project. The sources are cloned from GitHub as plain
git repositories over HTTPS: no GitHub API (anonymous calls are rate limited), no ssh in the
image.
