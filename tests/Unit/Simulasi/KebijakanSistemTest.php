<?php

namespace Tests\Unit\Simulasi;

use App\Services\Simulasi\KebijakanSistem;
use Tests\TestCase;

/**
 * Uji rumus rekomendasi sistem (docs/01 §7.2, direvisi 2026-09-21):
 * SUM(forecast sepanjang waktu tunggu) + SS - stok_awal - WIP.
 */
class KebijakanSistemTest extends TestCase
{
    private KebijakanSistem $kebijakan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kebijakan = new KebijakanSistem();
    }

    public function test_rencana_produksi_sesuai_hitungan_manual(): void
    {
        // (40+30+30) + 20 - 30 - 10 = 80
        $hasil = $this->kebijakan->rencanaProduksi([40, 30, 30], 20, 30, 10);

        $this->assertEqualsWithDelta(80.0, $hasil, 1e-9);
    }

    public function test_rencana_produksi_tidak_pernah_negatif(): void
    {
        // 10 + 5 - 50 - 0 = -35 -> dibulatkan ke 0
        $hasil = $this->kebijakan->rencanaProduksi([10], 5, 50, 0);

        $this->assertSame(0.0, $hasil);
    }

    public function test_delay_nol_sama_dengan_rumus_forecast_tunggal(): void
    {
        // delay=0 -> jendela forecast cuma 1 nilai, harus sama seperti rumus awal (docs/01 §7.5 poin 4).
        $hasil = $this->kebijakan->rencanaProduksi([100.0], 20, 30, 10);

        $this->assertEqualsWithDelta(100.0 + 20 - 30 - 10, $hasil, 1e-9);
    }
}
