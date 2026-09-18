<?php
namespace Tests;

final class RouterTest extends ApiTestCase
{
    public function testUnknownRouteReturnsJsonNotFound(): void
    {
        $res = $this->request('GET', '/api/nonsense');

        $this->assertSame(404, $res['status']);
        $this->assertSame('not_found', $res['json']['error']['code']);
    }

    public function testProtectedRouteWithoutSessionReturnsUnauthorized(): void
    {
        $res = $this->request('GET', '/api/bootstrap');

        $this->assertSame(401, $res['status']);
        $this->assertSame('unauthenticated', $res['json']['error']['code']);
    }

    public function testErrorBodyNeverContainsStackTrace(): void
    {
        $res = $this->request('GET', '/api/nonsense');

        $this->assertArrayNotHasKey('trace', $res['json']);
        $this->assertSame(['code', 'message'], array_keys($res['json']['error']));
    }
}
