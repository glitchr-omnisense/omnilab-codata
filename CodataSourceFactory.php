<?php

namespace Omnilab\Codata;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * CODATA's fundamental physical constants, as NIST publishes them: each
 * constant by its short name or its name, its value, uncertainty and
 * unit; a search - no key needed, two calls in all.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     base_uri: 'https://physics.nist.gov/'
 */
final class CodataSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'codata',
            'omnilab.required_options' => [],
            'throttle' => 0.0,
            'base_uri' => CodataSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new CodataSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', CodataSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
