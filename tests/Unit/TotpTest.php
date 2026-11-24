<?php

namespace Tests\Unit;

use App\Services\Auth\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_rfc_6238_vector(): void
    {
        $totp = new Totp;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $totp->codeAt($secret, 1));
        $this->assertSame('081804', $totp->codeAt($secret, intdiv(1111111109, 30)));
    }
}
