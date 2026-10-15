<?php

namespace Omnilab\Codata\Tests;

use Omnilab\Codata\CodataSource;
use Omnilab\Codata\CodataSourceFactory;
use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from physics.nist.gov on 2026-10-16: the text table
 * of the 2022 CODATA values (355 constants) and NIST's list of every
 * constant with its short name.
 */
final class CodataSourceTest extends TestCase
{
    /** @var list<string> */
    private array $calls = [];

    private function source(?string $table = null): CodataSource
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($table): MockResponse {
            $this->calls[] = $path = substr($url, \strlen(CodataSource::BASE_URI));

            return new MockResponse(match ($path) {
                CodataSource::TABLE => $table ?? (string) file_get_contents(__DIR__.'/Fixtures/allascii.txt'),
                CodataSource::LIST => (string) file_get_contents(__DIR__.'/Fixtures/category-all.html'),
            });
        });
        $source = (new CodataSourceFactory($http))->create();
        self::assertInstanceOf(CodataSource::class, $source);

        return $source;
    }

    public function testTheProtonMassByItsShortName(): void
    {
        $source = $this->source();
        $mp = $source->entity('mp');

        self::assertSame([CodataSource::TABLE, CodataSource::LIST], $this->calls);
        self::assertSame('codata:mp', $mp->id->key());
        self::assertSame('proton mass', $mp->label);
        self::assertSame(Domain::OTHER, $mp->domain);
        self::assertSame('physical constant', $mp->kind);
        self::assertSame('https://physics.nist.gov/cgi-bin/cuu/Value?mp', $mp->url);
        self::assertEquals(new Quantity(1.67262192595E-27, 'kg', 0.00000000052E-27), $mp->value('value'));
        self::assertFalse($mp->value('exact'));
        self::assertSame('1.672 621 925 95 e-27', $mp->value('value_text'));
        self::assertSame('0.000 000 000 52 e-27', $mp->value('uncertainty_text'));
        self::assertSame('kg', $mp->value('unit'));
        self::assertSame('2022', $mp->value('adjustment'));
        self::assertSame('2022', $source->adjustment());

        $source->entity('c');
        self::assertCount(2, $this->calls, 'the table and the list, once for the source\'s life');
    }

    public function testExactConstantsAndNames(): void
    {
        $source = $this->source();
        $c = $source->entity('speed of light in vacuum');
        self::assertSame('codata:c', $c->id->key());
        self::assertEquals(new Quantity(299792458.0, 'm s^-1'), $c->value('value'));
        self::assertTrue($c->value('exact'));
        self::assertNull($c->property('uncertainty_text'));

        $h = $source->entity(Identifier::of(Scheme::CODATA, 'h'));
        self::assertSame('Planck constant', $h->label);
        self::assertSame('6.626 070 15 e-34', $h->value('value_text'));
        self::assertSame('J Hz^-1', $h->value('unit'));
        $hev = $source->entity('Planck constant in eV/Hz');
        self::assertSame('4.135 667 696... e-15', $hev->value('value_text'), 'an exact value that goes on');
        self::assertEqualsWithDelta(4.135667696E-15, $hev->property('value')->number(), 1E-24);

        $G = $source->entity('https://physics.nist.gov/cgi-bin/cuu/Value?bg');
        self::assertSame('Newtonian constant of gravitation', $G->label);
        self::assertEquals(new Quantity(6.67430E-11, 'm^3 kg^-1 s^-2', 0.00015E-11), $G->value('value'));

        $ratio = $source->entity('alpha particle-electron mass ratio');
        self::assertNull($ratio->property('unit'), 'a ratio has no unit');
        self::assertNull($ratio->property('value')->unit());

        // The table abbreviates, NIST's list does not: either name gives it.
        $gamma = $source->entity('proton gyromag. ratio');
        self::assertSame('proton gyromagnetic ratio', $gamma->label);
        self::assertSame(['proton gyromag. ratio'], $gamma->synonyms);
        self::assertSame($gamma->id->key(), $source->entity('Proton Gyromagnetic Ratio')->id->key());
        self::assertSame('atomic unit of magnetic dipole moment', $source->entity('atomic unit of mag. dipole mom.')->label);
    }

    public function testEveryConstantOfTheTableIsNamed(): void
    {
        $all = 0;
        foreach (preg_split('~\R~', (string) file_get_contents(__DIR__.'/Fixtures/allascii.txt')) as $i => $line) {
            if ($i > 10 && '' !== trim($line)) {
                ++$all;
                self::assertNotNull($this->source()->entity(trim(substr($line, 0, 60))), $line);
            }
        }
        self::assertSame(355, $all);
    }

    public function testSearchByWords(): void
    {
        $source = $this->source();
        $page = $source->search(new Query('proton mass', limit: 3));
        self::assertSame(['proton mass', 'proton mass energy equivalent', 'proton mass energy equivalent in MeV'], array_map(static fn ($e) => $e->label, $page->items));
        self::assertSame('3', $page->next);
        self::assertGreaterThan(3, $page->total);
        self::assertCount(0, $source->search(new Query('proton mass', Domain::CHEMISTRY)));
        self::assertCount(0, $source->search(new Query('zzqqxx')));
    }

    public function testUnknownNullOtherBasesRefusedAndCapabilities(): void
    {
        $source = $this->source();
        self::assertNull($source->entity('codata:zzz'));
        self::assertNull($source->entity('the mass of a unicorn'));
        try {
            $source->entity('CHEBI:27732');
            self::fail('chebi');
        } catch (NotSupportedException) {
        }
        self::assertSame([Capability::ENTITY, Capability::SEARCH], $source->capabilities());
        $this->expectException(ProviderException::class);
        $this->source('not NIST\'s table')->entity('mp');
    }
}
