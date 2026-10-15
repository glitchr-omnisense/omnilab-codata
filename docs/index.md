# omnilab/codata

## Installation

```sh
composer require omnilab/codata
```

```php
use Omnilab\Codata\CodataSourceFactory;

$codata = (new CodataSourceFactory($httpClient))->create(['mailto' => 'support@glitchr.io']);
```

## The scheme

A constant is named in the core's `codata` scheme by NIST's short name, case kept (`mp`, `c`,
`bg`, `Kj`), as its page `physics.nist.gov/cgi-bin/cuu/Value?mp` names it; read from
`codata:mp` and from that page. Wikidata keeps these names in P1645 ("NIST/CODATA ID", on 70
items on 2026-10-15), which omnilab/wikidata does not read yet: no `Collector` path leads here.

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

## Verified, and not

Against the real service on 2026-10-15: both files (355 constants, the 2022 adjustment; 40.8 and
38.8 kB, under a second each); the proton mass, the speed of light, the Planck constant, the
Newtonian constant of gravitation, every line of the table matched to its short name. The fixtures
in `Tests/Fixtures/` are those two files.

Not verified: the older adjustments NIST keeps (2018 and before: other files, other addresses, not
read); the correlation coefficients between constants (not read).
