<?php

namespace studioespresso\navigate\tests\unit;

use Craft;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use studioespresso\navigate\models\NodeModel;
use yii\web\Request;

class NodeActiveStateTest extends TestCase
{
    private mixed $originalApp;

    protected function setUp(): void
    {
        $this->originalApp = Craft::$app;
    }

    protected function tearDown(): void
    {
        Craft::$app = $this->originalApp;
    }

    public static function cases(): array
    {
        // node url, request url, expected active(), expected current()
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
            'fragment only' => ['#top', 'https://domain.com/', false, false],
            'mailto' => ['mailto:hello@domain.com', 'https://domain.com/', false, false],
            'empty url' => ['', 'https://domain.com/', false, false],
        ];
    }

    #[DataProvider('cases')]
    public function testUrlNode(string $nodeUrl, string $requestUrl, bool $active, bool $current): void
    {
        $this->mockRequest($requestUrl);
        $node = new NodeModel(['type' => 'url', 'url' => $nodeUrl]);

        $this->assertSame($active, $node->active(), 'active()');
        $this->assertSame($current, $node->current(), 'current()');
    }

    public function testHeadingIsNeverActive(): void
    {
        $this->mockRequest('https://domain.com/');
        $node = new NodeModel(['type' => 'heading', 'url' => 'https://domain.com/']);

        $this->assertFalse($node->active());
        $this->assertFalse($node->current());
    }

    private function mockRequest(string $url): void
    {
        $parts = parse_url($url);
        $request = new Request();
        $request->setHostInfo($parts['scheme'] . '://' . $parts['host']);
        $request->setUrl(($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''));

        Craft::$app = new class($request) {
            public function __construct(private Request $request)
            {
            }

            public function getRequest(): Request
            {
                return $this->request;
            }
        };
    }
}
