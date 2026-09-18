<?php
namespace Tests;

use App\Http;

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

    public function testLastStatusReflectsCurrentRequestNotThePrevious(): void
    {
        $first = $this->request('GET', '/api/nonsense');
        $this->assertSame(404, $first['status']);

        // Utan nollställning i request() hade den här statusen av misstag
        // fortfarande kunnat vara kvar från föregående anrop. Idag svarar
        // alla vägar alltid, så det här larmar inte ensamt — se
        // testResetTestStateUsesZeroAsSentinelNotSuccess nedan för det
        // verkliga regressionsskyddet, som inte förlitar sig på att någon
        // handler råkar låta bli att svara.
        $second = $this->request('GET', '/api/bootstrap');
        $this->assertSame(401, $second['status']);
    }

    /**
     * Det egentliga beviset för nollställningen: sentinelvärdet 0 kan aldrig
     * förväxlas med en lyckad 2xx-status, och testet failar om
     * Http::resetTestState() saknas eller om den låter $lastStatus vara kvar
     * på ett tidigare anrops kod.
     */
    public function testResetTestStateUsesZeroAsSentinelNotSuccess(): void
    {
        ob_start();
        Http::error('some_error', 'test', 500);
        ob_end_clean();
        $this->assertSame(500, Http::lastStatus());

        Http::resetTestState();

        $this->assertSame(0, Http::lastStatus());
    }
}
