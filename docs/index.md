# omnilab/codata

The fundamental physical constants of CODATA as NIST publishes them (physics.nist.gov/constants:
the 2022 adjustment, 355 constants) for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab):
each constant an entity by NIST's short name or its name - its value, standard uncertainty and
unit, exact or not, its digits as published -, and a search. Two files read, once.

## Installation

```sh
composer require omnilab/codata
```

```php
use Omnilab\Codata\CodataSourceFactory;

$codata = (new CodataSourceFactory($httpClient))->create(['mailto' => 'support@glitchr.io']);
```

## Credentials

None: NIST's files are public, no key, no account.

## Options

The factory's options, with the defaults it applies (`CodataSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: the source makes two calls in all, for its life |
| `base_uri` | `string` | `https://physics.nist.gov/` | no | NIST's physics site: the table (`cuu/Constants/Table/allascii.txt`) and the list (`cgi-bin/cuu/Category?...`) are joined to it |

## Capabilities

`capabilities()` answers `entity`, `search`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | NIST's short name (`mp`, `codata:mp`, its NIST page) or a constant's name, NIST's or the table's abbreviated one, case aside; another base's identifier refused before any call |
| `search` | yes | every word of the text in the constant's name, the name itself first; paged by offset with a `total`; a domain other than `other` answers an empty page |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no |  |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no | none: a constant names no other base (Wikidata keeps its short name in P1645) |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

Beside the contract: `adjustment(): ?string`, the CODATA adjustment NIST's table holds (`2022`),
read from its header.

## The scheme

A constant is named in the core's `codata` scheme by NIST's short name, case kept (`mp`, `c`,
`bg`, `Kj`), as its page `physics.nist.gov/cgi-bin/cuu/Value?mp` names it; read from
`codata:mp` and from that page. Wikidata keeps these names in P1645 ("NIST/CODATA ID", on 70
items on 2026-10-15), which omnilab/wikidata reads as a cross-reference and looks items up by: the
`Collector` follows a Wikidata item into CODATA, and a constant into its Wikidata item.

## Which files

| | Address | What |
|---|---|---|
| the values | `cuu/Constants/Table/allascii.txt` | NIST's text table: "2022 CODATA adjustment" in its header, then one line a constant: its name in 60 columns, its value in 25, its standard uncertainty in 25 (`(exact)` for an exact one), its unit |
| the names | `cgi-bin/cuu/Category?view=html&All+values.x=87&All+values.y=21` | NIST's list of every constant, each a link `Value?{short name}`: the only place the short names are given for all |

Both are asked at the first question and kept for the source's life: any number of entities and
searches cost two calls. The table abbreviates some names ("proton mag. mom.", "gyromag."), the
list does not ("proton magnetic moment"): the source expands the three abbreviations NIST uses
(`mag. mom.`, `mom.`, `mag.`, `gyromag.`) to match each line of the table to its link; every one of
the 355 lines is matched (a test checks it). A table or a list not in that form is a
`ProviderException`.

## What an entity holds

`id` (`codata:mp`), `label` (NIST's name in full), `synonyms` (the table's abbreviated name when it
differs), `domain` `other`, `kind` `physical constant`, `url` (its NIST page), and:

| Key | Value |
|---|---|
| `value` | a `Quantity`: the value (a float), NIST's unit as written (`kg`, `m s^-1`, `J Hz^-1`; none for a ratio), its standard uncertainty (none when exact) |
| `exact` | boolean |
| `value_text`, `uncertainty_text` | as NIST writes them: `1.672 621 925 95 e-27`, `0.000 000 000 52 e-27`; an exact value that goes on ends with `...` (`4.135 667 696... e-15`) |
| `unit` | the unit, as text |
| `adjustment` | `2022` |

A float keeps fifteen to seventeen significant digits: `value_text` keeps every digit NIST gives.

`search()` keeps the constants whose names hold every word of the text (case aside): the name
itself first, then those that begin with it, then the others, each in NIST's order; paged by
offset, with a total.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Codata\CodataSourceFactory;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$codata = (new CodataSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);

$mp = $codata->entity('mp');
echo $mp->label, ' (', $mp->id, '): ', $mp->property('value'), ', exact: ', $mp->property('exact'), "\n";
echo '  as NIST writes it: ', $mp->value('value_text'), ' ± ', $mp->value('uncertainty_text'), ' ', $mp->value('unit'), "\n";
echo 'c: ', $codata->entity('speed of light in vacuum')->property('value'), "\n";

$page = $codata->search(new Query('proton mass', limit: 4));
printf("%d constants hold \"proton mass\"; the first %d:\n", $page->total, count($page));
foreach ($page as $constant) {
    echo '  ', $constant->id->value, '  ', $constant->label, "\n";
}
echo 'adjustment ', $codata->adjustment(), "\n";
```

```
proton mass (codata:mp): 1.67262192595e-27 ± 0.00000000052e-27 kg, exact: no
  as NIST writes it: 1.672 621 925 95 e-27 ± 0.000 000 000 52 e-27 kg
c: 299792458 m s^-1
23 constants hold "proton mass"; the first 4:
  mp  proton mass
  mpc2  proton mass energy equivalent
  mpc2mev  proton mass energy equivalent in MeV
  mpu  proton mass in u
adjustment 2022
```

(as answered on 2026-10-27: two calls for the whole script)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        codata: { factory: codata, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
```

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: an identifier of another base, or an empty text, given to `entity()`
(`NotSupportedException`). A table or a list not in the form expected (a line of dashes, then
columns of 60, 25 and 25 characters; links to `Value?...`) is a `ProviderException`. A name no
constant has is null.

## Verified, and not

Against the real service on 2026-10-15: both files (355 constants, the 2022 adjustment; 40.8 and
38.8 kB, under a second each); the proton mass, the speed of light, the Planck constant, the
Newtonian constant of gravitation, every line of the table matched to its short name. The fixtures
in `Tests/Fixtures/` are those two files.

Not verified: the older adjustments NIST keeps (2018 and before: other files, other addresses, not
read); the correlation coefficients between constants (not read).

Again on 2026-10-27: the proton mass by its short name, the speed of light by its name, the search
for "proton mass" (23 constants), the 2022 adjustment - two calls (the example above).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by NIST or CODATA. The values are NIST Standard Reference Database 121, data of the US
government under NIST's disclaimer, to be cited as NIST asks, as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-codata#terms-of-use) says. Whoever runs
the package is responsible for complying with them and for any use made of what it returns: see
the README's [Responsibility](https://github.com/glitchr-omnisense/omnilab-codata#responsibility)
section.
