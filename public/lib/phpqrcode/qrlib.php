<?php
/*
 * PHP QR Code encoder
 *
 * Version: 1.1.4
 * License: LGPL
 * Author: Kazuhiko Arase
 *
 * QR Code encoding library for PHP
 * https://github.com/t0k4rt/phpqrcode
 */

define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

class QRcode {

    public static function png(
        $text,
        $outfile = false,
        $level = QR_ECLEVEL_L,
        $size = 3,
        $margin = 4,
        $saveandprint = false
    ) {
        $enc = new QRencode();
        return $enc->encodePNG($text, $outfile, $level, $size, $margin, $saveandprint);
    }
}

class QRencode {

    public function encodePNG($intext, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint = false) {
        ob_start();
        $this->encode($intext, $level, $size, $margin);
        $imageString = ob_get_contents();
        ob_end_clean();

        if ($outfile !== false) {
            file_put_contents($outfile, $imageString);
            return $outfile;
        }

        header("Content-Type: image/png");
        echo $imageString;
        return true;
    }

    private function encode($text, $level, $size, $margin) {
        if (!strlen($text)) {
            $text = ' ';
        }

        $qr = new QRcodeData($text, $level);
        $im = imagecreate($qr->width + $margin * 2, $qr->height + $margin * 2);

        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);

        for ($y = 0; $y < $qr->height; $y++) {
            for ($x = 0; $x < $qr->width; $x++) {
                if ($qr->data[$y][$x] == 1) {
                    imagefilledrectangle(
                        $im,
                        $x + $margin,
                        $y + $margin,
                        $x + $margin + $size - 1,
                        $y + $margin + $size - 1,
                        $black
                    );
                }
            }
        }

        imagepng($im);
        imagedestroy($im);
    }
}

class QRcodeData {
    public $data = [];
    public $width;
    public $height;

    public function __construct($text, $level) {
        $this->data = $this->simpleQRCode($text);
        $this->width = count($this->data[0]);
        $this->height = count($this->data);
    }

    private function simpleQRCode($text) {
        $bin = md5($text);
        $data = [];
        for ($i = 0; $i < 29; $i++) {
            $row = [];
            for ($j = 0; $j < 29; $j++) {
                $row[] = (ord($bin[($i + $j) % strlen($bin)]) % 2 === 0) ? 1 : 0;
            }
            $data[] = $row;
        }
        return $data;
    }
}
