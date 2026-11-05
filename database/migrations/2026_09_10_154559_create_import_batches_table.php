<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_type');           // contoh: 'master_receipt_issue'
            $table->string('file_name');            // nama asli file yang diupload
            $table->string('file_hash')->nullable(); // sha256, buat deteksi file duplikat
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('processing'); // processing / success / failed
            $table->text('error_message')->nullable(); // pesan error kalau gagal, boleh kosong
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};