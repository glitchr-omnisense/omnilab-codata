<?php

namespace Omnilab\Codata;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\Query;

/**
 * The fundamental physical constants of CODATA, as NIST publishes them
 * (physics.nist.gov/cuu/Constants): each constant an entity - the proton
 * mass, the speed of light in vacuum, the Newtonian constant of
 * gravitation... -, by NIST's short name ("mp", "c", "bg") or its name,
 * with its value, standard uncertainty and unit (a Quantity), whether it
 * is exact, the value as NIST writes it, and the adjustment it comes from
 * (2022); a search by words of their names.
 *
 * Two calls in all, whatever is asked after: NIST's text table of every
 * value (allascii.txt) and its list of every constant, which gives each
 * its short name; both kept for the source's life. No key; US government
 * work.
 */
final class CodataSource extends HttpSource
{
    public const BASE_URI = 'https://physics.nist.gov/';

    /** The text table of every value, and the list that names each constant. */
    public const TABLE = 'cuu/Constants/Table/allascii.txt';
    public const LIST = 'cgi-bin/cuu/Category?view=html&All+values.x=87&All+values.y=21';

    /** @var array<string, array{id: string, name: string, line: string, value: string, uncertainty: string, unit: string}>|null by short name */
    private ?array $constants = null;

    private ?string $adjustment = null;

    public function getName(): string
    {
        return 'codata';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH];
    }

    /**
     * A constant by NIST's short name ("mp", "codata:mp", its NIST page) or
     * by its name, NIST's or the table's ("proton mass", "proton mag.
     * mom.", case aside); another base's identifier is refused before any
     * call; null when no constant has that name.
     */
    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = $id instanceof Identifier || null !== Identifier::parse($id) ? self::identify($id, Scheme::CODATA) : null;
        if (null === $identifier && ($id instanceof Identifier || null !== Identifier::parse($id) || '' === trim($id))) {
            throw NotSupportedException::identifier('codata', (string) $id);
        }
        $constants = $this->constants();
        if (null !== $identifier) {
            return isset($constants[$identifier->value]) ? $this->toEntity($constants[$identifier->value]) : null;
        }
        $name = self::normalize((string) $id);
        foreach ($constants as $constant) {
            if ($name === self::normalize($constant['name']) || $name === self::normalize($constant['line'])) {
                return $this->toEntity($constant);
            }
        }
        // A short name given bare ("mp"): NIST's are case-sensitive, tried as given.
        return isset($constants[trim($id)]) ? $this->toEntity($constants[trim($id)]) : null;
    }

    /**
     * The constants whose name holds every word of the text (case aside):
     * the name itself first, then the names that begin with the text, then
     * the others, each in NIST's order (alphabetical); a domain other than "other" answers
     * nothing (the core has no physics: a constant is of Domain::OTHER).
     */
    public function search(Query $query): Page
    {
        if (null !== $query->domain && Domain::OTHER !== $query->domain) {
            return new Page();
        }
        $words = array_filter(explode(' ', self::normalize($query->text)));
        if (!$words) {
            return new Page();
        }
        $text = implode(' ', $words);
        $ranked = [[], [], []];
        foreach ($this->constants() as $constant) {
            $name = self::normalize($constant['name'].' '.$constant['line']);
            if (array_reduce($words, static fn (bool $all, string $word) => $all && str_contains($name, $word), true)) {
                $own = self::normalize($constant['name']);
                $ranked[$own === $text ? 0 : (str_starts_with($own, $text) ? 1 : 2)][] = $constant;
            }
        }
        $found = array_merge(...$ranked);
        $start = max(0, (int) $query->cursor);
        $limit = max(1, $query->limit);

        return new Page(array_map(fn (array $c) => $this->toEntity($c), \array_slice($found, $start, $limit)), $start + $limit < \count($found) ? (string) ($start + $limit) : null, \count($found));
    }

    /** The adjustment the values come from: "2022" (read from the table). */
    public function adjustment(): ?string
    {
        $this->constants();

        return $this->adjustment;
    }

    /** @return array<string, array{id: string, name: string, line: string, value: string, uncertainty: string, unit: string}> */
    private function constants(): array
    {
        if (null !== $this->constants) {
            return $this->constants;
        }
        $table = (string) $this->get(self::TABLE);
        $list = (string) $this->get(self::LIST);
        $this->adjustment = preg_match('~(\d{4}) CODATA adjustment~', $table, $m) ? $m[1] : null;
        // The list: <a href="Value?mp|search_for=all!">    proton mass </a>.
        preg_match_all('~Value\?([A-Za-z0-9]+)\|search_for=all!">\s*([^<]+?)\s*<~', $list, $links, \PREG_SET_ORDER);
        $ids = [];
        foreach ($links as [, $short, $name]) {
            $ids[self::normalize(html_entity_decode($name, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'))] = [$short, trim((string) preg_replace('~\s+~', ' ', html_entity_decode($name, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')))];
        }
        // The table: the name in 60 columns, the value in 25, the uncertainty in 25, the unit; after a line of dashes.
        $lines = preg_split('~\R~', $table) ?: [];
        $start = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '-----')) {
                $start = $i + 1;
                break;
            }
        }
        if (null === $start || !$ids) {
            throw new ProviderException('codata', 'NIST\'s table or list is not in the form expected (a line of dashes, then 60/25/25 columns; links to Value?...)');
        }
        $constants = [];
        foreach (\array_slice($lines, $start) as $line) {
            if ('' === trim($line)) {
                continue;
            }
            $abbreviated = trim((string) preg_replace('~\s+~', ' ', substr($line, 0, 60)));
            $known = $ids[self::normalize(self::expand($abbreviated))] ?? $ids[self::normalize($abbreviated)] ?? null;
            if (null === $known) {
                continue;
            }
            $constants[$known[0]] = [
                'id' => $known[0],
                'name' => $known[1],
                'line' => $abbreviated,
                'value' => trim(substr($line, 60, 25)),
                'uncertainty' => trim(substr($line, 85, 25)),
                'unit' => trim(substr($line, 110)),
            ];
        }

        return $this->constants = $constants;
    }

    /** @param array{id: string, name: string, line: string, value: string, uncertainty: string, unit: string} $c */
    private function toEntity(array $c): Entity
    {
        $id = Identifier::of(Scheme::CODATA, $c['id']);
        $exact = '(exact)' === $c['uncertainty'];
        $value = self::number($c['value']);
        $uncertainty = $exact ? null : self::number($c['uncertainty']);
        $unit = '' === $c['unit'] ? null : $c['unit'];
        $properties = [];
        if (null !== $value) {
            $properties[] = new Property('value', 'Value', new Quantity($value, $unit, $uncertainty), 'codata');
        }
        $properties[] = new Property('exact', 'Exact', $exact, 'codata');
        // As NIST writes it: its digits in groups of three, "..." when an exact value goes on.
        $properties[] = new Property('value_text', 'Value as published', $c['value'], 'codata');
        if (!$exact) {
            $properties[] = new Property('uncertainty_text', 'Standard uncertainty as published', $c['uncertainty'], 'codata');
        }
        if (null !== $unit) {
            $properties[] = new Property('unit', 'Unit', $unit, 'codata');
        }
        if (null !== $this->adjustment) {
            $properties[] = new Property('adjustment', 'CODATA adjustment', $this->adjustment, 'codata');
        }

        return new Entity(
            id: $id,
            label: $c['name'],
            domain: Domain::OTHER,
            kind: 'physical constant',
            synonyms: $c['line'] !== $c['name'] ? [$c['line']] : [],
            properties: $properties,
            url: $id->url(),
            source: 'codata',
        );
    }

    /** "6.644 657 3450 e-27" -> 6.6446573450E-27; "4.135 667 696... e-15" -> 4.135667696E-15; null when not a number. */
    private static function number(string $text): ?float
    {
        $digits = str_replace([' ', '...'], '', $text);

        return is_numeric($digits) ? (float) $digits : null;
    }

    /** The table's abbreviations, as NIST's list writes them in full. */
    private static function expand(string $name): string
    {
        return (string) preg_replace(['~\bmag\. mom\.~', '~\bmom\.~', '~\bgyromag\.~', '~\bmag\.~'], ['magnetic moment', 'moment', 'gyromagnetic', 'magnetic'], $name);
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('~\s+~u', ' ', $text)));
    }
}
