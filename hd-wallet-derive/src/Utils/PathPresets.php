<?php

namespace App\Utils;

use \Exception;

/**
 * Bip32 Path Presets for various classes.
 * Resources:
 *   https://bitcoin.stackexchange.com/questions/78993/default-derivation-paths
 */


class PathPresets {

    static function getPreset($preset_id) {
        $list = static::getAllPresetID();
        if( !in_array($preset_id, $list)) {
            throw new Exception("Invalid preset identifier");
        }

        $class = 'App\Utils\PathPreset_' . $preset_id;
        $c = new $class();
    return $c;
    }

    static function getAllPresetID() {

        static $id_list = null;

        if(!$id_list) {
            $id_list = [];
            $declared = get_declared_classes();
            foreach($declared as $d) {
                if(strpos($d, 'App\Utils\PathPreset_') === 0) {
                    $id = str_replace('App\Utils\PathPreset_', '', $d);
                    $id_list[] = $id;
                }
            }
        }

        return $id_list;
    }

    static function getAllPresets() {


        $all = self::getAllPresetID();
        $presets = [];

        foreach($all as $id) {
            $presets[] = self::getPreset($id);
        }
        return $presets;
    }
}

interface PathPreset {
    public function getID();
    public function getPath();
    public function getWalletSoftwareName();
    public function getWalletSoftwareVersionInfo();
}

class PathPreset_ledgerlive {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/x'/v/0";
    }

    public function getWalletSoftwareName() {
        return 'Ledger Live';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'All versions';
    }

    public function getNote() {
        return 'Non-standard Bip44';
    }
}

class PathPreset_bitcoincore {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/a'/v'/x'";
    }

    public function getWalletSoftwareName() {
        return 'Bitcoin Core';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'v0.13 and above.';
    }

    public function getNote() {
        return 'Bip32 fully hardened';
    }
}

class PathPreset_trezor {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Trezor';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'All versions';
    }

    public function getNote() {
        return 'Bip44';
    }
}

class PathPreset_bip32 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Bip32 Compat';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'n/a';
    }

    public function getNote() {
        return 'Bip32';
    }
}

class PathPreset_bip44 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Bip44 Compat';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'n/a';
    }

    public function getNote() {
        return 'Bip44';
    }
}

class PathPreset_bip49 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/49'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Bip49 Compat';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'n/a';
    }

    public function getNote() {
        return 'Bip49';
    }
}


class PathPreset_bip84 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/84'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Bip84 Compat';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'n/a';
    }

    public function getNote() {
        return 'Bip84';
    }
}


class PathPreset_bither {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Bither';
    }

    public function getWalletSoftwareVersionInfo() {
        return 'n/a';
    }

    public function getNote() {
        return 'Bip44';
    }
}



// See https://github.com/bitpay/copay/wiki

class PathPreset_copay_legacy {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/45'/2147483647/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Copay Legacy';
    }

    public function getWalletSoftwareVersionInfo() {
        return '< 1.2';
    }

    public function getNote() {
        return 'Bip45 special cosign idx';
    }
}

class PathPreset_copay {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Copay';
    }

    public function getWalletSoftwareVersionInfo() {
        return '>= 1.2';
    }

    public function getNote() {
        return 'Bip44';
    }
}

class PathPreset_copay_hardware_multisig {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/48'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Copay';
    }

    public function getWalletSoftwareVersionInfo() {
        return '>= 1.5';
    }

    public function getNote() {
        return 'Hardware multisig wallets';
    }
}


class PathPreset_mycelium {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Mycelium';
    }

    public function getWalletSoftwareVersionInfo() {
        return '>= 2.0';
    }

    public function getNote() {
        return 'Bip44';
    }
}

class PathPreset_jaxx {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Jaxx';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip44';
    }
}


class PathPreset_electrum {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Electrum';
    }

    public function getWalletSoftwareVersionInfo() {
        return '2.0+';
    }

    public function getNote() {
        return 'Single account wallet';
    }
}

class PathPreset_electrum_multi {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/a/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Electrum multi';
    }

    public function getWalletSoftwareVersionInfo() {
        return '2.0+';
    }

    public function getNote() {
        return 'Multi account wallet';
    }
}

class PathPreset_wasabi {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/84'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Wasabi';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip84';
    }
}

class PathPreset_samourai {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Samourai (p2pkh)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip44';
    }
}

class PathPreset_samourai_p2sh {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/49'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Samourai (p2sh)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip49';
    }
}


class PathPreset_samourai_bech32 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/84'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Samourai (bech32)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip84';
    }
}


class PathPreset_breadwallet {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'BreadWallet';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip32';
    }
}

class PathPreset_hive {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Hive';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip32';
    }
}

class PathPreset_multibit_hd {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Multibit HD';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip32';
    }
}

class PathPreset_multibit_hd_44 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Multibit HD (Bip44)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip44';
    }
}


class PathPreset_coinomi {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/44'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Coinomi (p2pkh)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip44';
    }
}

class PathPreset_coinomi_p2sh {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/49'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Coinomi (p2sh)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip49';
    }
}


class PathPreset_coinomi_bech32 {

    public function getID() {
        return str_replace('App\Utils\PathPreset_', '', get_class($this));
    }

    public function getPath() {
        return "m/84'/c'/a'/v/x";
    }

    public function getWalletSoftwareName() {
        return 'Coinomi (bech32)';
    }

    public function getWalletSoftwareVersionInfo() {
        return '?';
    }

    public function getNote() {
        return 'Bip84';
    }
}

