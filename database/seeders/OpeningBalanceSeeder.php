<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

class OpeningBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Membungkus script dalam transaksi. Jika di tengah jalan error, semua dibatalkan otomatis.
        DB::transaction(function () {
            $inventories = Inventory::all();
            $count = 0;

            foreach ($inventories as $item) {
                // Hanya buat catatan transaksi awal jika stoknya ada (lebih dari 0)
                if ($item->stock > 0) {
                    InventoryTransaction::create([
                        'inventory_id' => $item->id,
                        'type'         => 'ADJ', // ADJ = Adjustment (Penyesuaian Saldo Awal)
                        'qty'          => $item->stock,
                        'harga_satuan' => 0, // Admin akan mengisinya nanti via Excel/Dashboard
                        'total_harga'  => 0,
                        'keterangan'   => 'Saldo Awal Migrasi Sistem Ledger (Otomatis)',
                    ]);
                    
                    $count++;
                }
            }

            // Pesan sukses di terminal
            $this->command->info("✅ Sistem PRO Berhasil: Saldo awal untuk {$count} item sparepart telah dipindahkan ke Buku Besar (Transactions).");
        });
    }
}