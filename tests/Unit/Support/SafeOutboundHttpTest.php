<?php

namespace Tests\Unit\Support;

use App\Support\SafeOutboundHttp;
use Tests\TestCase;

class SafeOutboundHttpTest extends TestCase
{
    public function test_public_https_urls_are_allowed(): void
    {
        $this->assertTrue(SafeOutboundHttp::isAllowed('https://example.com/feed.xml'));
    }

    public function test_loopback_and_private_urls_are_blocked(): void
    {
        $this->assertFalse(SafeOutboundHttp::isAllowed('http://127.0.0.1/rss'));
        $this->assertFalse(SafeOutboundHttp::isAllowed('http://localhost/rss'));
        $this->assertFalse(SafeOutboundHttp::isAllowed('http://10.0.0.8/api.json'));
        $this->assertFalse(SafeOutboundHttp::isAllowed('http://192.168.1.10/feed'));
        $this->assertFalse(SafeOutboundHttp::isAllowed('file:///etc/passwd'));
    }
}
