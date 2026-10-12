<?php

namespace studioespresso\navigate\tests\Unit;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use studioespresso\navigate\models\NodeModel;

class NodeActiveStateTest extends TestCase
{
    /** @return array<string, array{string, string, bool, bool}> node url, request url, expected active(), expected current() */
    public static function cases(): array
    {
        return [
            'subdomain link on main homepage' => ['https://jobs.domain.com', 'https://domain.com/', false, false],
            'subdomain link with path' => ['https://jobs.domain.com/news', 'https://domain.com/news', false, false],
            'main homepage link on subdomain' => ['https://domain.com/', 'https://jobs.domain.com/', false, false],
            'host is case-insensitive' => ['https://Domain.com/news', 'https://domain.com/news', true, true],
            'homepage on homepage' => ['https://domain.com/', 'https://domain.com/', true, true],
            'homepage on other page' => ['https://domain.com/', 'https://domain.com/news', false, false],
            'exact page' => ['https://domain.com/news', 'https://domain.com/news', true, true],
            'trailing slash' => ['https://domain.com/news/', 'https://domain.com/news', true, true],
            'query string ignored' => ['https://domain.com/news', 'https://domain.com/news?page=2', true, true],
            'child page' => ['https://domain.com/news', 'https://domain.com/news/article', true, false],
            'not a path segment' => ['https://domain.com/news', 'https://domain.com/newsletter', false, false],
            'relative url' => ['/news', 'https://domain.com/news/article', true, false],
            'site in a subfolder' => ['https://domain.com/nl/nieuws', 'https://domain.com/nl/nieuws/artikel', true, false],
            'fragment only' => ['#top', 'https://domain.com/', false, false],
            'mailto' => ['mailto:hello@domain.com', 'https://domain.com/', false, false],
            'empty url' => ['', 'https://domain.com/', false, false],
        ];
    }

    #[DataProvider('cases')]
    public function test_url_node(string $nodeUrl, string $requestUrl, bool $active, bool $current): void
    {
        $this->app->instance('request', Request::create($requestUrl));
        $node = NodeModel::fromArray(['type' => 'url', 'url' => $nodeUrl]);

        $this->assertSame($active, $node->active(), 'active()');
        $this->assertSame($current, $node->current(), 'current()');
    }

    public function test_heading_is_never_active(): void
    {
        $this->app->instance('request', Request::create('https://domain.com/'));
        $node = NodeModel::fromArray(['type' => 'heading', 'url' => 'https://domain.com/']);

        $this->assertFalse($node->active());
        $this->assertFalse($node->current());
    }
}
