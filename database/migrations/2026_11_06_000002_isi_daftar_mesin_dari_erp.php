<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Daftar mesin dari ERP (Equipment1.xlsx, sheet "Equip"): semua equipment ACTIVE, tanpa yang INACTIVE/OBSOLETE dan tanpa Building.
 * Nama = "EquipID · Description" supaya mudah dicari & dicocokkan dengan ERP.
 * Mesin contoh lama (BUBUT, CAVALRY, SUNHOPE, WEBBER) dihapus hanya bila belum pernah dipakai di catatan mana pun.
 */
return new class extends Migration
{
    private const LAMA = ['BUBUT', 'CAVALRY', 'SUNHOPE', 'WEBBER'];

    private const MESIN = [
        '1103 · HYUNJIN_#1_HIMASTER_500',
        '1104 · HYUNJIN_#2_HIMASTER_500',
        '1105 · HYUNJIN_#3_HIMASTER_500',
        '1106 · HYUNJIN_#4_HIMASTER_1500',
        '1107 · HYUNJIN_#5_HIMAST_500 RB',
        '1113 · PMC_#5_G1000',
        '1128 · IBERICA_#1_JR-105F',
        '1129 · M&P_#13_CARTON FORMER',
        '1130 · M&P_#14_CARTON FORMER',
        '1138 · M&P_#10_CARTON FORMER',
        '1143 · PEERLESS_#1_PAPER PLATE',
        '1145 · PEERLESS_#3_PAPER PLATE',
        '1169 · MATADOR_#2',
        '1170 · F&K_MAXIMA 40_#4',
        '1190 · BEF_#11_WO TWIN',
        '1192 · BEF_#12_AMQ',
        '1231 · C&C_#3_1000',
        '1233 · CMF PRT_#1',
        '1260 · SHANG_SQZK1370ST_#1',
        '1262 · POLAR_115CE_#1',
        '1263 · WOHLENBERG GUILO MCS-2 TYPE 115-1 003069',
        '1264 · CAVALLERI_#1_SYNCRO CT15',
        '1274 · JAGENBERG_#1_SHEETER',
        '1286 · VALLEY_#1_SLITTER',
        '1290 · DPE_WAXER_#4_PHASE III',
        '1294 · W&H_BTTMR_AD2360/22_#3',
        '1297 · ICOMA_PINCH_191000002_#1',
        '1300 · W&H_TUBER_AMV2160_#3',
        '1308 · WEBER_#6_6_6141',
        '1310 · WEBER_#5_8_8147',
        '1316 · W&H_#20_T3D',
        '1321 · W&H_#5_T3F',
        '1338 · WEBER_#7_5XF',
        '1353 · SEW_#1',
        '1372 · W&H_MATADOR 4211_#3',
        '1373 · HUDSON SHARP_#1_10 TA',
        '1383 · F&K_PP_F-21_#6',
        '1386 · W&H_#8_T2B',
        '1389 · M&P_#11_CARTON FORMER',
        '1390 · M&P_#12_CARTON FORMER',
        '1395 · NILPITER_#1_FLEXO PRT',
        '1396 · HYUNJIN_#6_HIMASTER_1500',
        '1403 · PUNCHING MACHINE_#2',
        '1404 · PUNCHING MACHINE_#3',
        '1405 · QUINGHUA_#2_SHT_1400B',
        '1414 · EKOFA_#3_FLEXO PRINTER',
        '1417 · BEF_#24_TYPE 2',
        '1420 · WEBER_#4_3_804A',
        '1427 · BOBST_FG_#6_0356',
        '1455 · CUPOTECH_#1_KOVRA-6000',
        '1458 · SEE_#1_SCM-600',
        '1511 · GRALEX_#1_FMR',
        '1516 · CUPOTECH_#2_KOVRA-6000',
        '1532 · BOBST_#3_SP 130-ER II',
        '1533 · KBA_#2_RAPIDA 105',
        '1534 · BRAUSSE_FG_#10_1100-C3',
        '1599 · BOBST_#12_NOVACUT_106E',
        '1684 · SEE_#9_SCM-600',
        '1685 · SEE_#10_SCM-1101/601',
        '1698 · SEE_#13_SCM-600G',
        '1703 · ZONO_#05_ZD-F330S_DCH',
        '1704 · CUPOTECH_#03_KOVRA-7000',
        '1705 · CUPOTECH_#04_KOVRA-7000',
        '1712 · ZONO_#07_ZD330Q_PTH',
        '1717 · FANGBANG_#31_FJ11E',
        '1727 · SEE_#16_SCM-1101/601',
        '1741 · WEBER_#29_B-4_807A',
        '1745 · HYUNJIN_#7_CUP_12CC',
        '1746 · HYUNJIN_#8_16CC',
        '1747 · HYUNJIN_#9_22CC',
        '1765 · FANGBANG_#48_FJ13S',
        '1777 · PUNCH_#10_CHANG DAE',
        '1782 · HYUNJIN_#13_22CC',
        '1783 · EKOFA_#8_TF 1300-8',
        '1787 · PUNCH_#11_CHANG DAE',
        '1835 · PTH_FY10FD_OBS',
        'A0001 · SULLAIR AIR DRYER SRCG3890E-1 003347',
        'A0002 · KAESER AIR DRYER TI601-2 003348',
        'A0003 · KAESER VACUUM BSV100-1 003350',
        'A0004 · KAESER VACUUM BSV100-2 003351',
        'A0005 · KAESER VACUUM BSV100-3 003352',
        'A0006 · CONTROL AIR INTELLIGENT FLOW CONTROL 003353',
        'A0007 · AIR RECEIVER TANK-1 003354',
        'A0008 · AIR RECEIVER TANK-2 003355',
        'A0009 · AIR RECEIVER TANK-3 003356',
        'A0010 · PURE AIR REFRIGERATED AIR DRYER 003427',
        'A0011 · PURE AIR PRE FILTER 003428',
        'A0012 · AIR RECEIVER TANK 1000L 003429',
        'A0013 · LIANG CHI COOLING TOWER LBC30-1 003433',
        'A0014 · AUTO SLEEVE TCJ-QZB 750-2 003438',
        'A0015 · KAESER AIR COMPRESSOR-ES300-1 003343',
        'A0016 · KAESER AIR COMPRESSOR-DSD241-2 003344',
        'A0017 · KAESER AIR COMPRESSOR-DSD201-3 003345',
        'A0018 · KAESER AIR COMPRESSOR-DSDX302-4 003346',
        'A0019 · DEBAO L666 AUTO SLEEVER-3 003408',
        'A0020 · DEBAO L666 AUTO SLEEVER-4 003409',
        'A0021 · AUTO SLEEVE TCJ-QSJ750-4 003423',
        'A0022 · AUTO SLEEVE TCJ-QSJ750-4 003424',
        'A0023 · SULLAIR AIR COMPRESSOR AS02208 003426',
        'A0024 · SULLAIR VSD AIR COMPRESSOR LS200HAC 003430',
        'A0025 · SULLAIR AIR DRYER SRCG3890E 003431',
        'A0026 · PURE AIR PRE FILTER PAG-O-1950F 003432',
        'A0027 · CUP LEAK TEST-1 HYUNJIN 003443',
        'A0028 · AUTO SLEEVE TCJ-QZB750-4 005051',
        'A0029 · WESTCO CQ-G125 75CM LATHE 0003372',
        'A0030 · WESTCO ZX7032 DRILLING MILLING 0003373',
        'A0031 · KAESER AIR COMPRESSOR SCD125-1 003076',
        'A0032 · AIR RECEIVER TANK-1 003077',
        'A0033 · KAESER AIR DRYER TE121-1 003078',
        'A0034 · KAESER AIR COMPRESSOR BS61-2 003079',
        'A0035 · AIR RECEIVER TANK-1 003080',
        'A0036 · HERLES CF-1000H 1 LATHE 003102',
        'A0037 · HAFCO HM50 1 MILLING 003103',
        'A0038 · WINHO M660X2200 LATHE',
        'A0039 · PINNACLE PK-GRSM-V TURRET MILLING',
        'A0040 · SULLAIR AIR COMPRESSOR LS16 75H AC-1 003193',
        'A0041 · ZANDER AIR DRYER AS0540-1 003195',
        'A0042 · FLYING TECH PTH 8 FY10D 002663',
        'A0043 · WOHLENBERG GUILO A107E5-2 003198',
        'A0044 · METAL DETECTOR CAIRNHILL 233MD02',
        'A0045 · DPE BAG PRESS-4 003196',
    ];

    private function dipakai(string $nama): bool
    {
        return DB::table('inventory_transactions')->where('mesin', $nama)->exists()
            || DB::table('job_mesins')->where('mesin', $nama)->exists()
            || DB::table('permintaan_ambil_items')->where('mesin', $nama)->exists();
    }

    public function up(): void
    {
        $sekarang = now();
        DB::table('mesins')->insertOrIgnore(array_map(fn ($n) => ['nama' => $n, 'created_at' => $sekarang, 'updated_at' => $sekarang], self::MESIN));

        foreach (self::LAMA as $nama) {
            if (!$this->dipakai($nama)) {
                DB::table('mesins')->where('nama', $nama)->delete();
            }
        }
    }

    public function down(): void
    {
        foreach (self::MESIN as $nama) {
            if (!$this->dipakai($nama)) {
                DB::table('mesins')->where('nama', $nama)->delete();
            }
        }
    }
};
