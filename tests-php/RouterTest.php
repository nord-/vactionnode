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
        // testDispatchThatSkipsRespondingLeavesTheSentinel nedan för det
        // verkliga regressionsskyddet, som går via request() och inte
        // förlitar sig på att någon handler råkar låta bli att svara.
        $second = $this->request('GET', '/api/bootstrap');
        $this->assertSame(401, $second['status']);
    }

    /**
     * Dokumenterar bara vad resetTestState() gör som isolerad metod — den
     * anropas direkt, aldrig via request(), så den bevisar inte att
     * request() faktiskt nollställer inför dispatch. Se testet nedan för
     * det verkliga regressionsskyddet.
     */
    public function testResetTestStateAssignsTheZeroSentinel(): void
    {
        ob_start();
        Http::error('some_error', 'test', 500);
        ob_end_clean();
        $this->assertSame(500, Http::lastStatus());

        Http::resetTestState();

        $this->assertSame(0, Http::lastStatus());
    }

    /**
     * Det egentliga regressionsskyddet: simulerar en handlerväg som
     * returnerar utan att svara genom att hoppa över dispatch() i
     * request() — routingtabellen bär ingen testkrok, seamen ligger i
     * testriggen. Statusen förgiftas först med ett riktigt svar (404) så
     * att testet kan skilja "kvarbliven kod" från "nollställd".
     */
    public function testDispatchThatSkipsRespondingLeavesTheSentinel(): void
    {
        $poisoned = $this->request('GET', '/api/nonsense');
        $this->assertSame(404, $poisoned['status']);

        $this->skipDispatch = true;
        try {
            $res = $this->request('GET', '/api/nonsense');
        } finally {
            $this->skipDispatch = false;
        }

        $this->assertSame(0, $res['status']);
    }
}
