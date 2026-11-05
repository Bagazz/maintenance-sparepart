<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Registrasi karyawan maintenance (khusus admin / kepala sparepart).
 * Teknisi login dengan NAMA terdaftar + password yang diatur di sini; nama wajib unik.
 */
class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status', 'semua');

        $karyawan = Karyawan::query()
            ->withCount(['historyLogs as jumlah_transaksi'])
            ->when($q !== '', fn ($qr) => $qr->where(fn ($s) => $s
                ->where('nama', 'like', "%$q%")
                ->orWhere('nomor_kartu', 'like', "%$q%")
                ->orWhere('bagian', 'like', "%$q%")))
            ->when($status === 'aktif', fn ($qr) => $this->siap($qr))
            ->when($status === 'tanpa_pin', fn ($qr) => $qr->where('aktif', true)->whereNull('pin_hash'))
            ->when($status === 'terkunci', fn ($qr) => $qr->whereNotNull('pin_terkunci_at'))
            ->when($status === 'nonaktif', fn ($qr) => $qr->where('aktif', false))
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        $statistik = [
            'total'       => Karyawan::count(),
            'siap'        => $this->siap(Karyawan::query())->count(),
            'tanpa_pin'   => Karyawan::where('aktif', true)->whereNull('pin_hash')->count(),
            'terkunci'    => Karyawan::whereNotNull('pin_terkunci_at')->count(),
            'nonaktif'    => Karyawan::where('aktif', false)->count(),
        ];

        return view('karyawan.index', compact('karyawan', 'q', 'status', 'statistik'));
    }

    /** Siap login: aktif, punya password, tidak terkunci. */
    private function siap($query)
    {
        return $query->where('aktif', true)->whereNotNull('pin_hash')->whereNull('pin_terkunci_at');
    }

    /**
     * Atur / ganti password satu karyawan. Admin bisa mengetik password sendiri, atau kosongkan untuk password acak 6 angka.
     * Password acak ditampilkan SEKALI ke admin untuk diserahkan ke karyawan (disimpan hanya sebagai hash).
     */
    public function aturPin(Request $request, Karyawan $karyawan)
    {
        $pin = trim((string) $request->input('pin', ''));
        if ($pin !== '' && !Karyawan::passwordValid($pin)) {
            return back()->with('error', __('The password must be 4–50 characters.'));
        }
        $acak = $pin === '';
        $pin = $acak ? Karyawan::pinAcak() : $pin;
        $karyawan->aturPin($pin);

        return back()
            ->with('success', __('Password for :nama saved. The account is unlocked.', ['nama' => $karyawan->nama]))
            ->with('pin_baru', $acak ? [['nama' => $karyawan->nama, 'nomor' => $karyawan->nomor_kartu, 'pin' => $pin]] : null);
    }

    /** Buat password acak untuk semua karyawan aktif yang belum punya; slip login ditampilkan sekali untuk dicetak. */
    public function pinMassal()
    {
        $daftar = [];
        foreach (Karyawan::where('aktif', true)->whereNull('pin_hash')->orderBy('nama')->get() as $k) {
            $pin = Karyawan::pinAcak();
            $k->aturPin($pin);
            $daftar[] = ['nama' => $k->nama, 'nomor' => $k->nomor_kartu, 'pin' => $pin];
        }

        return back()
            ->with($daftar ? 'success' : 'error', $daftar ? __('Passwords created for :n employees. Print the login slips below now — they will not be shown again.', ['n' => count($daftar)]) : __('All active employees already have a password.'))
            ->with('pin_baru', $daftar ?: null);
    }

    public function bukaKunci(Karyawan $karyawan)
    {
        $karyawan->bukaKunci();

        return back()->with('success', __('The account of :nama is unlocked.', ['nama' => $karyawan->nama]));
    }

    private function aturan(?Karyawan $karyawan = null): array
    {
        return [
            'nomor_kartu' => ['nullable', 'string', 'max:50', Rule::unique('karyawans', 'nomor_kartu')->ignore($karyawan?->id)],
            // Nama = nama login, jadi wajib unik (dibandingkan setelah dirapikan ke HURUF BESAR).
            'nama'        => ['required', 'string', 'max:255', Rule::unique('karyawans', 'nama')->ignore($karyawan?->id)],
            'bagian'      => ['nullable', 'string', 'max:100'],
            'shift'       => ['nullable', Rule::in(['Shift 1', 'Shift 2', 'Shift 3', 'Non-shift'])],
            'aktif'       => ['nullable', 'boolean'],
        ];
    }

    private function rapikan(array $data, Request $request): array
    {
        $data['nomor_kartu'] = isset($data['nomor_kartu']) && $data['nomor_kartu'] !== ''
            ? Karyawan::normalNomor($data['nomor_kartu']) : null;
        $data['nama'] = Karyawan::normalNama($data['nama']);
        $data['aktif'] = $request->boolean('aktif', true);

        return $data;
    }

    public function store(Request $request)
    {
        $request->merge(['nomor_kartu' => Karyawan::normalNomor($request->nomor_kartu) ?: null, 'nama' => Karyawan::normalNama($request->nama)]);
        $data = $this->rapikan($request->validate($this->aturan()), $request);
        $k = Karyawan::create($data);

        return back()->with('success', __('Employee :nama registered.', ['nama' => $k->nama]));
    }

    public function update(Request $request, Karyawan $karyawan)
    {
        $request->merge(['nomor_kartu' => Karyawan::normalNomor($request->nomor_kartu) ?: null, 'nama' => Karyawan::normalNama($request->nama)]);
        $data = $this->rapikan($request->validate($this->aturan($karyawan)), $request);
        unset($data['aktif']); // status aktif hanya diubah lewat tombol Aktifkan/Nonaktifkan
        $karyawan->update($data);

        return back()->with('success', __('Data for :nama updated.', ['nama' => $karyawan->nama]));
    }

    /** Tidak dihapus permanen (riwayat transaksi tetap utuh) — cukup dinonaktifkan. */
    public function toggle(Karyawan $karyawan)
    {
        $karyawan->update(['aktif' => !$karyawan->aktif]);

        return back()->with('success', $karyawan->aktif ? __(':nama reactivated.', ['nama' => $karyawan->nama]) : __(':nama deactivated; can no longer take items.', ['nama' => $karyawan->nama]));
    }

    /**
     * Import massal dari Excel/CSV. Baris pertama = header; kolom dikenali dari namanya:
     * nama (wajib), nomor/nik (opsional), bagian/divisi, shift. Nama / nomor yang sudah ada -> data diperbarui.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);

        $sheet = IOFactory::load($request->file('file')->getPathname())->getActiveSheet();
        $baris = $sheet->toArray(null, true, true, false);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($baris) ?? []);

        $cari = function (array $kandidat) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($kandidat as $k) {
                    if ($h === $k || str_contains($h, $k)) {
                        return $i;
                    }
                }
            }
            return null;
        };

        $kol = [
            'nomor'  => $cari(['nomor kartu', 'no kartu', 'nik', 'nomor', 'no. karyawan', 'id karyawan', 'id']),
            'nama'   => $cari(['nama']),
            'bagian' => $cari(['bagian', 'divisi', 'departemen', 'jabatan']),
            'shift'  => $cari(['shift']),
        ];

        if ($kol['nama'] === null) {
            return back()->with('error', __('Column "Nama" was not found in the first row of the file. Example header: Nama | Nomor | Bagian | Shift'));
        }

        $hasil = ['baru' => 0, 'diperbarui' => 0, 'dilewati' => 0];

        DB::transaction(function () use ($baris, $kol, &$hasil) {
            foreach ($baris as $r) {
                $nomor = $kol['nomor'] !== null ? Karyawan::normalNomor($r[$kol['nomor']] ?? '') : '';
                $nama = Karyawan::normalNama($r[$kol['nama']] ?? '');

                if ($nama === '') {
                    $hasil['dilewati']++;
                    continue;
                }

                $shift = $kol['shift'] !== null ? trim((string) ($r[$kol['shift']] ?? '')) : null;
                if ($shift !== null && preg_match('/^[123]$/', $shift)) {
                    $shift = 'Shift ' . $shift;
                }

                $k = ($nomor !== '' ? Karyawan::where('nomor_kartu', $nomor)->first() : null)
                    ?? Karyawan::firstOrNew(['nama' => $nama]);
                $hasil[$k->exists ? 'diperbarui' : 'baru']++;
                $k->fill([
                    'nama'        => $nama,
                    'nomor_kartu' => $nomor !== '' ? $nomor : $k->nomor_kartu,
                    'bagian' => $kol['bagian'] !== null ? (trim((string) ($r[$kol['bagian']] ?? '')) ?: null) : $k->bagian,
                    'shift'  => in_array($shift, ['Shift 1', 'Shift 2', 'Shift 3', 'Non-shift'], true) ? $shift : $k->shift,
                    'aktif'  => true,
                ])->save();
            }
        });

        return back()->with('success', __('Import finished: :baru new, :ubah updated, :lewat rows skipped.', ['baru' => $hasil['baru'], 'ubah' => $hasil['diperbarui'], 'lewat' => $hasil['dilewati']]));
    }
}
