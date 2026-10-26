# omnilab/codata

The fundamental physical constants of CODATA, as NIST publishes them, for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): each constant an entity - the proton
mass, the speed of light in vacuum, the Newtonian constant of gravitation... 355 of the 2022
adjustment - by NIST's short name (`mp`, `c`, `bg`) or its name, with its value, standard
uncertainty and unit (a `Quantity`), whether it is exact, the value as NIST writes it, the
adjustment; a search by words of their names. Two calls in all. No key.

```php
$codata = (new CodataSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$mp = $codata->entity('mp');                    // or 'proton mass', 'codata:mp', its NIST page
$mp->property('value');                         // 1.67262192595E-27 ± 0.00000000052E-27 kg
$mp->value('value_text');                       // 1.672 621 925 95 e-27 (as NIST writes it)
$codata->entity('speed of light in vacuum')->value('exact');   // true
$codata->search(new Query('proton mass', limit: 5));           // proton mass, proton mass energy equivalent...
$codata->adjustment();                          // 2022
```

```yaml
omnilab:
    sources:
        codata:
            factory: codata
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
```

A constant is of `Domain::OTHER`: the core has no physics domain (its domains are chemistry,
biology, taxonomy, astronomy, earth), and none was added for this package.

## Terms of use

NIST Standard Reference Database 121, "CODATA Internationally recommended 2022 values of the
Fundamental Physical Constants" (physics.nist.gov/constants): data of the US government (NIST),
with NIST's disclaimer. Cite the values as NIST asks, with the detailed articles on the 2022
adjustment it links.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by NIST and CODATA. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License (of this package's code): MIT.
