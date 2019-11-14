<?php

namespace Nimiq;

use StephenHill\Base58;
use Elliptic\EC;
use BN\BN;
use kornrunner\Keccak;
use BitWasp\Bech32;

class XPub {
    public const HEX_VERSION = [
        'xpub' => '0488b21e',
        'tpub' => '043587cf',
        'zpub' => '04b24746',
        'vpub' => '045f1cf6',
    ];

    // https://en.bitcoin.it/wiki/List_of_address_prefixes
    public const NETWORK_ID = [
        'xpub' => '00',
        'tpub' => '6f',
    ];

    public const SEGWIT_HRP = [
        'zpub' => 'bc',
        'vpub' => 'tc',
    ];

    public const SEGWIT_VERSION = 0;

    public static function fromString(string $xpub_base58): XPub {
        $xpub_bin = (new Base58())->decode($xpub_base58);

        $version = substr($xpub_base58, 0, 4);
        $depth = self::bin2dec(substr($xpub_bin, 4, 1));
        $fpr_par = bin2hex(substr($xpub_bin, 5, 4));
        $i = self::bin2dec(substr($xpub_bin, 9, 4));
        $c = bin2hex(substr($xpub_bin, 13, 32));
        $K = bin2hex(substr($xpub_bin, 45, 33));

        return new self(
            $version,
            $depth,
            $fpr_par,
            $i,
            $c,
            $K
        );
    }

    public static function bin2dec(string $bin): int {
        return unpack('C', $bin)[1];
    }

    public static function hash160(string $hex): string {
        return hash('ripemd160', hash('sha256', hex2bin($hex), TRUE));
    }

    public static function doubleSha256(string $hex): string {
        return hash('sha256', hash('sha256', hex2bin($hex), TRUE));
    }

    public function __construct(
        string $version,
        int $depth,
        string $parent_fingerprint,
        int $index,
        string $c,
        string $K
    ) {
        $this->version = $version;
        $this->depth = $depth;
        $this->parent_fingerprint = $parent_fingerprint;
        $this->index = $index;
        $this->c = $c;
        $this->K = $K;
    }

    public function derive($indices): XPub {
        if (!is_array($indices)) $indices = [$indices];
        $i = array_shift($indices);

        $ec = new EC('secp256k1'); // BTC Elliptic Curve

        $I_key  = hex2bin($this->c);
        $I_data = hex2bin($this->K) . pack('N', $i);
        $I      = hash_hmac('sha512', $I_data, $I_key);
        $I_L    = substr($I, 0, 64);
        $I_R    = substr($I, 64, 64);
        $c_i    = $I_R; // Child Chain Code

        $K_par_point = $ec->curve->decodePoint($this->K, 'hex');
        $I_L_point = $ec->g->mul(new BN($I_L, 16));
        $K_i = $K_par_point->add($I_L_point);
        $K_i = $K_i->encodeCompressed('hex'); // Child Public Key

        $fpr_par = substr(self::hash160($this->K), 0, 8); // Parent Fingerprint

        $child = new self(
            $this->version,
            $this->depth + 1,
            $fpr_par,
            $i,
            $c_i,
            $K_i
        );

        // Recursive derivation
        if (count($indices) > 0) return $child->derive($indices);

        return $child;
    }

    public function toString(bool $asHex = false): string {
        $xpub_hex  = self::HEX_VERSION[$this->version];
        $xpub_hex .= str_pad(dechex($this->depth), 2, '0', STR_PAD_LEFT);
        $xpub_hex .= $this->parent_fingerprint;
        $xpub_hex .= str_pad(dechex($this->index), 8, '0', STR_PAD_LEFT);
        $xpub_hex .= $this->c;
        $xpub_hex .= $this->K;

        // Checksum
        $xpub_hex .= substr(self::doubleSha256($xpub_hex), 0, 8);

        if ($asHex) return $xpub_hex;

        return (new Base58())->encode(hex2bin($xpub_hex));
    }

    public function toAddress(string $coin = 'btc') {
        switch ($coin) {
            case 'btc': return $this->toBTCAddress();
            case 'eth': return $this->toETHAddress();
            default: throw new Exception('Coin type "' . $coin . '" not supported!');
        }
    }

    private function toBTCAddress(): string {
        switch ($this->version) {
            case 'xpub': case 'tpub': return $this->toBTCP2PKHAddress();
            case 'zpub': case 'vpub': return $this->toBTCP2WPKHAddress();
            default: throw new Exception('Version "' . $this->version . '" not supported!');
        }
    }

    private function toBTCP2PKHAddress(): string {
        $base_address = self::NETWORK_ID[$this->version] . self::hash160($this->K);
        $checksum = substr(self::doubleSha256($base_address), 0, 8);

        $address_hex = $base_address . $checksum;

        return (new Base58())->encode(hex2bin($address_hex));
    }

    private function toBTCP2WPKHAddress(): string {
        $programm = self::hash160($this->K);
        $version = self::SEGWIT_VERSION;
        $hrp = self::SEGWIT_HRP[$this->version];
        return Bech32\encodeSegwit($hrp, $version, hex2bin($programm));
    }

    private function toETHAddress(): string {
        $ec = new EC('secp256k1'); // ETH Elliptic Curve
        $K_full = $ec->keyFromPublic($this->K, 'hex')->getPublic('hex');

        $K_bin = hex2bin(substr($K_full, 2));
        $hash_hex = Keccak::hash($K_bin, 256);
        $base_address = substr($hash_hex, 24, 40);

        return '0x' . $this->encodeETHChecksum($base_address);
    }

    private function encodeETHChecksum(string $base_address) {
        $binary = $this->hex2binary(Keccak::hash($base_address, 256));

        $encoded = '';
        foreach (str_split($base_address) as $i => $char) {
            if (strpos('abcdef', $char) !== false) {
                $encoded .= $binary[$i * 4] === '1' ? strtoupper($char) : strtolower($char);
            } else {
                $encoded .= $char;
            }
        }
        return $encoded;
    }

    private function hex2binary($hex) {
        $binary = '';
        foreach (str_split($hex, 2) as $hexit) {
            $binary .= str_pad(decbin(hexdec($hexit)), 8, '0', STR_PAD_LEFT);
        }
        return $binary;
    }
}
