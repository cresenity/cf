<?php

class CImage_QRCode {
    use CImage_QRCode_Trait_QRCodePropertyTrait;

    private $data;

    private $options;

    public function __construct($data, $options) {
        $this->data = $data;
        $this->options = $options;
    }

    public function outputImage() {
        $image = $this->renderImage();

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }

    public function renderImage() {
        list($code, $widths, $width, $height, $x, $y, $w, $h) = $this->encodeAndCalculateSize($this->data, $this->options);

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);

        $bgcolor = (isset($this->options['bc']) ? $this->options['bc'] : 'FFFFFF');
        $bgcolor = $this->allocateColor($image, $bgcolor);
        imagefill($image, 0, 0, $bgcolor);

        $fgcolor = (isset($this->options['fc']) ? $this->options['fc'] : '000000');
        $fgcolor = $this->allocateColor($image, $fgcolor);

        $colors = [$bgcolor, $fgcolor];

        $density = (isset($this->options['md']) ? (float) $this->options['md'] : 1);
        list($width, $height) = $this->calculateSize($code, $widths);
        if ($width && $height) {
            $scale = min($w / $width, $h / $height);
            $scale = (($scale > 1) ? floor($scale) : 1);
            $x = floor($x + ($w - $width * $scale) / 2);
            $y = floor($y + ($h - $height * $scale) / 2);
        } else {
            $scale = 1;
            $x = floor($x + $w / 2);
            $y = floor($y + $h / 2);
        }

        $x += $code['q'][3] * $widths[0] * $scale;
        $y += $code['q'][0] * $widths[0] * $scale;
        $wh = $widths[1] * $scale;
        foreach ($code['b'] as $by => $row) {
            $y1 = $y + $by * $wh;
            foreach ($row as $bx => $color) {
                $x1 = $x + $bx * $wh;
                $mc = $colors[$color ? 1 : 0];
                $rx = floor($x1 + (1 - $density) * $wh / 2);
                $ry = floor($y1 + (1 - $density) * $wh / 2);
                $rw = ceil($wh * $density);
                $rh = ceil($wh * $density);
                imagefilledrectangle($image, $rx, $ry, $rx + $rw - 1, $ry + $rh - 1, $mc);
            }
        }

        return $image;
    }

    private function encodeAndCalculateSize($data, $options) {
        $code = $this->dispatchEncode($data, $options);
        $widths = [
            (isset($options['wq']) ? (int) $options['wq'] : 1),
            (isset($options['wm']) ? (int) $options['wm'] : 1),
        ];

        $size = $this->calculateSize($code, $widths);
        $dscale = 4;
        $scale = (isset($options['sf']) ? (float) $options['sf'] : $dscale);
        $scalex = (isset($options['sx']) ? (float) $options['sx'] : $scale);
        $scaley = (isset($options['sy']) ? (float) $options['sy'] : $scale);
        $dpadding = 0;
        $padding = (isset($options['p']) ? (int) $options['p'] : $dpadding);
        $vert = (isset($options['pv']) ? (int) $options['pv'] : $padding);
        $horiz = (isset($options['ph']) ? (int) $options['ph'] : $padding);
        $top = (isset($options['pt']) ? (int) $options['pt'] : $vert);
        $left = (isset($options['pl']) ? (int) $options['pl'] : $horiz);
        $right = (isset($options['pr']) ? (int) $options['pr'] : $horiz);
        $bottom = (isset($options['pb']) ? (int) $options['pb'] : $vert);
        $dwidth = ceil($size[0] * $scalex) + $left + $right;
        $dheight = ceil($size[1] * $scaley) + $top + $bottom;
        $iwidth = (isset($options['w']) ? (int) $options['w'] : $dwidth);
        $iheight = (isset($options['h']) ? (int) $options['h'] : $dheight);
        $swidth = $iwidth - $left - $right;
        $sheight = $iheight - $top - $bottom;

        return [$code, $widths, $iwidth, $iheight, $left, $top, $swidth, $sheight];
    }

    private function allocateColor($image, $color) {
        $color = preg_replace('/[^0-9A-Fa-f]/', '', $color);
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function dispatchEncode($data, $options) {
        switch (strtolower(preg_replace('/[^A-Za-z0-9]/', '', carr::get($options, 's', 'qr')))) {
            case 'qrl':
                return $this->qrEncode($data, 0);
            case 'qrm':
                return $this->qrEncode($data, 1);
            case 'qrq':
                return $this->qrEncode($data, 2);
            case 'qrh':
                return $this->qrEncode($data, 3);
            default:
                return $this->qrEncode($data, 0);
        }

        return null;
    }

    private function calculateSize($code, $widths) {
        $width = (
            $code['q'][3] * $widths[0]
                + $code['s'][0] * $widths[1]
                + $code['q'][1] * $widths[0]
        );
        $height = (
            $code['q'][0] * $widths[0]
                + $code['s'][1] * $widths[1]
                + $code['q'][2] * $widths[0]
        );

        return [$width, $height];
    }

    private function qrEncode($data, $ecl) {
        list($mode, $vers, $ec, $data) = $this->qrEncodeData($data, $ecl);
        $data = $this->qrEncodeEc($data, $ec, $vers);
        list($size, $mtx) = $this->qrCreateMatrix($vers, $data);
        list($mask, $mtx) = $this->qrApplyBestMask($mtx, $size);
        $mtx = $this->qrFinalizeMatrix($mtx, $size, $ecl, $mask, $vers);

        return [
            'q' => [4, 4, 4, 4],
            's' => [$size, $size],
            'b' => $mtx
        ];
    }

    private function qrEncodeData($data, $ecl) {
        $mode = $this->qrDetectMode($data);
        $version = $this->qrDetectVersion($data, $mode, $ecl);
        $version_group = (($version < 10) ? 0 : (($version < 27) ? 1 : 2));
        $ec_params = $this->qr_ec_params[($version - 1) * 4 + $ecl];

        /* Don't cut off mid-character if exceeding capacity. */
        $max_chars = $this->qr_capacity[$version - 1][$ecl][$mode];
        if ($mode == 3) {
            $max_chars <<= 1;
        }
        $data = substr($data, 0, $max_chars);

        /* Convert from character level to bit level. */
        switch ($mode) {
            case 0:
                $code = $this->qrEncodeNumeric($data, $version_group);

                break;
            case 1:
                $code = $this->qrEncodeAlphaNumeric($data, $version_group);

                break;
            case 2:
                $code = $this->qrEncodeBinary($data, $version_group);

                break;
            case 3:
                $code = $this->qrEncodeKanji($data, $version_group);

                break;
        }

        for ($i = 0; $i < 4; $i++) {
            $code[] = 0;
        }
        while (count($code) % 8) {
            $code[] = 0;
        }

        /* Convert from bit level to byte level. */
        $data = [];
        for ($i = 0, $n = count($code); $i < $n; $i += 8) {
            $byte = 0;
            if ($code[$i + 0]) {
                $byte |= 0x80;
            }
            if ($code[$i + 1]) {
                $byte |= 0x40;
            }
            if ($code[$i + 2]) {
                $byte |= 0x20;
            }
            if ($code[$i + 3]) {
                $byte |= 0x10;
            }
            if ($code[$i + 4]) {
                $byte |= 0x08;
            }
            if ($code[$i + 5]) {
                $byte |= 0x04;
            }
            if ($code[$i + 6]) {
                $byte |= 0x02;
            }
            if ($code[$i + 7]) {
                $byte |= 0x01;
            }
            $data[] = $byte;
        }

        for ($i = count($data), $a = 1, $n = $ec_params[0]; $i < $n; $i++, $a ^= 1) {
            $data[] = $a ? 236 : 17;
        }

        /* Return. */
        return [$mode, $version, $ec_params, $data];
    }

    private function qrDetectMode($data) {
        $numeric = '/^[0-9]*$/';
        $alphanumeric = '/^[0-9A-Z .\/:$%*+-]*$/';
        $kanji = '/^([\x81-\x9F\xE0-\xEA][\x40-\xFC]|[\xEB][\x40-\xBF])*$/';
        if (preg_match($numeric, $data)) {
            return 0;
        }
        if (preg_match($alphanumeric, $data)) {
            return 1;
        }
        if (preg_match($kanji, $data)) {
            return 3;
        }

        return 2;
    }

    private function qrDetectVersion($data, $mode, $ecl) {
        $length = strlen($data);
        if ($mode == 3) {
            $length >>= 1;
        }
        for ($v = 0; $v < 40; $v++) {
            if ($length <= $this->qr_capacity[$v][$ecl][$mode]) {
                return $v + 1;
            }
        }

        return 40;
    }

    private function qrEncodeNumeric($data, $version_group) {
        $code = [0, 0, 0, 1];
        $length = strlen($data);
        switch ($version_group) {
            case 2: /* 27 - 40 */
                $code[] = $length & 0x2000;
                $code[] = $length & 0x1000;
                // no break
            case 1: /* 10 - 26 */
                $code[] = $length & 0x0800;
                $code[] = $length & 0x0400;
                // no break
            case 0: /* 1 - 9 */
                $code[] = $length & 0x0200;
                $code[] = $length & 0x0100;
                $code[] = $length & 0x0080;
                $code[] = $length & 0x0040;
                $code[] = $length & 0x0020;
                $code[] = $length & 0x0010;
                $code[] = $length & 0x0008;
                $code[] = $length & 0x0004;
                $code[] = $length & 0x0002;
                $code[] = $length & 0x0001;
        }
        for ($i = 0; $i < $length; $i += 3) {
            $group = substr($data, $i, 3);
            switch (strlen($group)) {
                case 3:
                    $code[] = $group & 0x200;
                    $code[] = $group & 0x100;
                    $code[] = $group & 0x080;
                    // no break
                case 2:
                    $code[] = $group & 0x040;
                    $code[] = $group & 0x020;
                    $code[] = $group & 0x010;
                    // no break
                case 1:
                    $code[] = $group & 0x008;
                    $code[] = $group & 0x004;
                    $code[] = $group & 0x002;
                    $code[] = $group & 0x001;
            }
        }

        return $code;
    }

    private function qrEncodeAlphaNumeric($data, $version_group) {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        $code = [0, 0, 1, 0];
        $length = strlen($data);
        switch ($version_group) {
            case 2: /* 27 - 40 */
                $code[] = $length & 0x1000;
                $code[] = $length & 0x0800;
                // no break
            case 1: /* 10 - 26 */
                $code[] = $length & 0x0400;
                $code[] = $length & 0x0200;
                // no break
            case 0: /* 1 - 9 */
                $code[] = $length & 0x0100;
                $code[] = $length & 0x0080;
                $code[] = $length & 0x0040;
                $code[] = $length & 0x0020;
                $code[] = $length & 0x0010;
                $code[] = $length & 0x0008;
                $code[] = $length & 0x0004;
                $code[] = $length & 0x0002;
                $code[] = $length & 0x0001;
        }
        for ($i = 0; $i < $length; $i += 2) {
            $group = substr($data, $i, 2);
            if (strlen($group) > 1) {
                $c1 = strpos($alphabet, substr($group, 0, 1));
                $c2 = strpos($alphabet, substr($group, 1, 1));
                $ch = $c1 * 45 + $c2;
                $code[] = $ch & 0x400;
                $code[] = $ch & 0x200;
                $code[] = $ch & 0x100;
                $code[] = $ch & 0x080;
                $code[] = $ch & 0x040;
                $code[] = $ch & 0x020;
                $code[] = $ch & 0x010;
                $code[] = $ch & 0x008;
                $code[] = $ch & 0x004;
                $code[] = $ch & 0x002;
                $code[] = $ch & 0x001;
            } else {
                $ch = strpos($alphabet, $group);
                $code[] = $ch & 0x020;
                $code[] = $ch & 0x010;
                $code[] = $ch & 0x008;
                $code[] = $ch & 0x004;
                $code[] = $ch & 0x002;
                $code[] = $ch & 0x001;
            }
        }

        return $code;
    }

    private function qrEncodeBinary($data, $version_group) {
        $code = [0, 1, 0, 0];
        $length = strlen($data);
        switch ($version_group) {
            case 2: /* 27 - 40 */
            case 1: /* 10 - 26 */
                $code[] = $length & 0x8000;
                $code[] = $length & 0x4000;
                $code[] = $length & 0x2000;
                $code[] = $length & 0x1000;
                $code[] = $length & 0x0800;
                $code[] = $length & 0x0400;
                $code[] = $length & 0x0200;
                $code[] = $length & 0x0100;
                // no break
            case 0: /* 1 - 9 */
                $code[] = $length & 0x0080;
                $code[] = $length & 0x0040;
                $code[] = $length & 0x0020;
                $code[] = $length & 0x0010;
                $code[] = $length & 0x0008;
                $code[] = $length & 0x0004;
                $code[] = $length & 0x0002;
                $code[] = $length & 0x0001;
        }
        for ($i = 0; $i < $length; $i++) {
            $ch = ord(substr($data, $i, 1));
            $code[] = $ch & 0x80;
            $code[] = $ch & 0x40;
            $code[] = $ch & 0x20;
            $code[] = $ch & 0x10;
            $code[] = $ch & 0x08;
            $code[] = $ch & 0x04;
            $code[] = $ch & 0x02;
            $code[] = $ch & 0x01;
        }

        return $code;
    }

    private function qrEncodeKanji($data, $version_group) {
        $code = [1, 0, 0, 0];
        $length = strlen($data);
        switch ($version_group) {
            case 2: /* 27 - 40 */
                $code[] = $length & 0x1000;
                $code[] = $length & 0x0800;
                // no break
            case 1: /* 10 - 26 */
                $code[] = $length & 0x0400;
                $code[] = $length & 0x0200;
                // no break
            case 0: /* 1 - 9 */
                $code[] = $length & 0x0100;
                $code[] = $length & 0x0080;
                $code[] = $length & 0x0040;
                $code[] = $length & 0x0020;
                $code[] = $length & 0x0010;
                $code[] = $length & 0x0008;
                $code[] = $length & 0x0004;
                $code[] = $length & 0x0002;
        }
        for ($i = 0; $i < $length; $i += 2) {
            $group = substr($data, $i, 2);
            $c1 = ord(substr($group, 0, 1));
            $c2 = ord(substr($group, 1, 1));
            if ($c1 >= 0x81 && $c1 <= 0x9F && $c2 >= 0x40 && $c2 <= 0xFC) {
                $ch = ($c1 - 0x81) * 0xC0 + ($c2 - 0x40);
            } elseif (($c1 >= 0xE0 && $c1 <= 0xEA && $c2 >= 0x40 && $c2 <= 0xFC)
                || ($c1 == 0xEB && $c2 >= 0x40 && $c2 <= 0xBF)
            ) {
                $ch = ($c1 - 0xC1) * 0xC0 + ($c2 - 0x40);
            } else {
                $ch = 0;
            }
            $code[] = $ch & 0x1000;
            $code[] = $ch & 0x0800;
            $code[] = $ch & 0x0400;
            $code[] = $ch & 0x0200;
            $code[] = $ch & 0x0100;
            $code[] = $ch & 0x0080;
            $code[] = $ch & 0x0040;
            $code[] = $ch & 0x0020;
            $code[] = $ch & 0x0010;
            $code[] = $ch & 0x0008;
            $code[] = $ch & 0x0004;
            $code[] = $ch & 0x0002;
            $code[] = $ch & 0x0001;
        }

        return $code;
    }

    private function qrEncodeEc($data, $ec_params, $version) {
        $blocks = $this->qrEcSplit($data, $ec_params);
        $ec_blocks = [];
        for ($i = 0, $n = count($blocks); $i < $n; $i++) {
            $ec_blocks[] = $this->qrEcDivide($blocks[$i], $ec_params);
        }
        $data = $this->qrEcInterleave($blocks);
        $ec_data = $this->qrEcInterleave($ec_blocks);
        $code = [];
        foreach ($data as $ch) {
            $code[] = $ch & 0x80;
            $code[] = $ch & 0x40;
            $code[] = $ch & 0x20;
            $code[] = $ch & 0x10;
            $code[] = $ch & 0x08;
            $code[] = $ch & 0x04;
            $code[] = $ch & 0x02;
            $code[] = $ch & 0x01;
        }
        foreach ($ec_data as $ch) {
            $code[] = $ch & 0x80;
            $code[] = $ch & 0x40;
            $code[] = $ch & 0x20;
            $code[] = $ch & 0x10;
            $code[] = $ch & 0x08;
            $code[] = $ch & 0x04;
            $code[] = $ch & 0x02;
            $code[] = $ch & 0x01;
        }
        for ($n = $this->qr_remainder_bits[$version - 1]; $n > 0; $n--) {
            $code[] = 0;
        }

        return $code;
    }

    private function qrEcSplit($data, $ec_params) {
        $blocks = [];
        $offset = 0;
        for ($i = $ec_params[2], $length = $ec_params[3]; $i > 0; $i--) {
            $blocks[] = array_slice($data, $offset, $length);
            $offset += $length;
        }
        for ($i = $ec_params[4], $length = $ec_params[5]; $i > 0; $i--) {
            $blocks[] = array_slice($data, $offset, $length);
            $offset += $length;
        }

        return $blocks;
    }

    private function qrEcDivide($data, $ec_params) {
        $num_data = count($data);
        $num_error = $ec_params[1];
        $generator = $this->qr_ec_polynomials[$num_error];
        $message = $data;
        for ($i = 0; $i < $num_error; $i++) {
            $message[] = 0;
        }
        for ($i = 0; $i < $num_data; $i++) {
            if ($message[$i]) {
                $leadterm = $this->qr_log[$message[$i]];
                for ($j = 0; $j <= $num_error; $j++) {
                    $term = ($generator[$j] + $leadterm) % 255;
                    $message[$i + $j] ^= $this->qr_exp[$term];
                }
            }
        }

        return array_slice($message, $num_data, $num_error);
    }

    private function qrEcInterleave($blocks) {
        $data = [];
        $num_blocks = count($blocks);
        for ($offset = 0; true; $offset++) {
            $break = true;
            for ($i = 0; $i < $num_blocks; $i++) {
                if (isset($blocks[$i][$offset])) {
                    $data[] = $blocks[$i][$offset];
                    $break = false;
                }
            }
            if ($break) {
                break;
            }
        }

        return $data;
    }

    private function qrCreateMatrix($version, $data) {
        $size = $version * 4 + 17;
        $matrix = [];
        for ($i = 0; $i < $size; $i++) {
            $row = [];
            for ($j = 0; $j < $size; $j++) {
                $row[] = 0;
            }
            $matrix[] = $row;
        }

        /* Finder patterns. */
        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                $m = (($i == 7 || $j == 7) ? 2
                        : (($i == 0 || $j == 0 || $i == 6 || $j == 6) ? 3
                        : (($i == 1 || $j == 1 || $i == 5 || $j == 5) ? 2 : 3)));
                $matrix[$i][$j] = $m;
                $matrix[$size - $i - 1][$j] = $m;
                $matrix[$i][$size - $j - 1] = $m;
            }
        }

        /* Alignment patterns. */
        if ($version >= 2) {
            $alignment = $this->qr_alignment_patterns[$version - 2];
            foreach ($alignment as $i) {
                foreach ($alignment as $j) {
                    if (!$matrix[$i][$j]) {
                        for ($ii = -2; $ii <= 2; $ii++) {
                            for ($jj = -2; $jj <= 2; $jj++) {
                                $m = (max(abs($ii), abs($jj)) & 1) ^ 3;
                                $matrix[$i + $ii][$j + $jj] = $m;
                            }
                        }
                    }
                }
            }
        }

        /* Timing patterns. */
        for ($i = $size - 9; $i >= 8; $i--) {
            $matrix[$i][6] = ($i & 1) ^ 3;
            $matrix[6][$i] = ($i & 1) ^ 3;
        }

        /* Dark module. Such an ominous name for such an innocuous thing. */
        $matrix[$size - 8][8] = 3;

        /* Format information area. */
        for ($i = 0; $i <= 8; $i++) {
            if (!$matrix[$i][8]) {
                $matrix[$i][8] = 1;
            }
            if (!$matrix[8][$i]) {
                $matrix[8][$i] = 1;
            }
            if ($i && !$matrix[$size - $i][8]) {
                $matrix[$size - $i][8] = 1;
            }
            if ($i && !$matrix[8][$size - $i]) {
                $matrix[8][$size - $i] = 1;
            }
        }

        /* Version information area. */
        if ($version >= 7) {
            for ($i = 9; $i < 12; $i++) {
                for ($j = 0; $j < 6; $j++) {
                    $matrix[$size - $i][$j] = 1;
                    $matrix[$j][$size - $i] = 1;
                }
            }
        }

        /* Data. */
        $col = $size - 1;
        $row = $size - 1;
        $dir = -1;
        $offset = 0;
        $length = count($data);
        while ($col > 0 && $offset < $length) {
            if (!$matrix[$row][$col]) {
                $matrix[$row][$col] = $data[$offset] ? 5 : 4;
                $offset++;
            }
            if (!$matrix[$row][$col - 1]) {
                $matrix[$row][$col - 1] = $data[$offset] ? 5 : 4;
                $offset++;
            }
            $row += $dir;
            if ($row < 0 || $row >= $size) {
                $dir = -$dir;
                $row += $dir;
                $col -= 2;
                if ($col == 6) {
                    $col--;
                }
            }
        }

        return [$size, $matrix];
    }

    private function qrApplyBestMask($matrix, $size) {
        $best_mask = 0;
        $best_matrix = $this->qrApplyMask($matrix, $size, $best_mask);
        $best_penalty = $this->qrPenalty($best_matrix, $size);
        for ($test_mask = 1; $test_mask < 8; $test_mask++) {
            $test_matrix = $this->qrApplyMask($matrix, $size, $test_mask);
            $test_penalty = $this->qrPenalty($test_matrix, $size);
            if ($test_penalty < $best_penalty) {
                $best_mask = $test_mask;
                $best_matrix = $test_matrix;
                $best_penalty = $test_penalty;
            }
        }

        return [$best_mask, $best_matrix];
    }

    private function qrApplyMask($matrix, $size, $mask) {
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($matrix[$i][$j] >= 4) {
                    if ($this->qrMask($mask, $i, $j)) {
                        $matrix[$i][$j] ^= 1;
                    }
                }
            }
        }

        return $matrix;
    }

    private function qrMask($mask, $r, $c) {
        switch ($mask) {
            case 0:
                return !(($r + $c) % 2);
            case 1:
                return !(($r) % 2);
            case 2:
                return !(($c) % 3);
            case 3:
                return !(($r + $c) % 3);
            case 4:
                return !((floor(($r) / 2) + floor(($c) / 3)) % 2);
            case 5:
                return !(((($r * $c) % 2) + (($r * $c) % 3)));
            case 6:
                return !(((($r * $c) % 2) + (($r * $c) % 3)) % 2);
            case 7:
                return !(((($r + $c) % 2) + (($r * $c) % 3)) % 2);
        }
    }

    private function qrPenalty(&$matrix, $size) {
        $score = $this->qrPenalty1($matrix, $size);
        $score += $this->qrPenalty2($matrix, $size);
        $score += $this->qrPenalty3($matrix, $size);
        $score += $this->qrPenalty4($matrix, $size);

        return $score;
    }

    private function qrPenalty1(&$matrix, $size) {
        $score = 0;
        for ($i = 0; $i < $size; $i++) {
            $rowvalue = 0;
            $rowcount = 0;
            $colvalue = 0;
            $colcount = 0;
            for ($j = 0; $j < $size; $j++) {
                $rv = ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) ? 1 : 0;
                $cv = ($matrix[$j][$i] == 5 || $matrix[$j][$i] == 3) ? 1 : 0;
                if ($rv == $rowvalue) {
                    $rowcount++;
                } else {
                    if ($rowcount >= 5) {
                        $score += $rowcount - 2;
                    }
                    $rowvalue = $rv;
                    $rowcount = 1;
                }
                if ($cv == $colvalue) {
                    $colcount++;
                } else {
                    if ($colcount >= 5) {
                        $score += $colcount - 2;
                    }
                    $colvalue = $cv;
                    $colcount = 1;
                }
            }
            if ($rowcount >= 5) {
                $score += $rowcount - 2;
            }
            if ($colcount >= 5) {
                $score += $colcount - 2;
            }
        }

        return $score;
    }

    private function qrPenalty2(&$matrix, $size) {
        $score = 0;
        for ($i = 1; $i < $size; $i++) {
            for ($j = 1; $j < $size; $j++) {
                $v1 = $matrix[$i - 1][$j - 1];
                $v2 = $matrix[$i - 1][$j];
                $v3 = $matrix[$i][$j - 1];
                $v4 = $matrix[$i][$j];
                $v1 = ($v1 == 5 || $v1 == 3) ? 1 : 0;
                $v2 = ($v2 == 5 || $v2 == 3) ? 1 : 0;
                $v3 = ($v3 == 5 || $v3 == 3) ? 1 : 0;
                $v4 = ($v4 == 5 || $v4 == 3) ? 1 : 0;
                if ($v1 == $v2 && $v2 == $v3 && $v3 == $v4) {
                    $score += 3;
                }
            }
        }

        return $score;
    }

    private function qrPenalty3(&$matrix, $size) {
        $score = 0;
        for ($i = 0; $i < $size; $i++) {
            $rowvalue = 0;
            $colvalue = 0;
            for ($j = 0; $j < 11; $j++) {
                $rv = ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) ? 1 : 0;
                $cv = ($matrix[$j][$i] == 5 || $matrix[$j][$i] == 3) ? 1 : 0;
                $rowvalue = (($rowvalue << 1) & 0x7FF) | $rv;
                $colvalue = (($colvalue << 1) & 0x7FF) | $cv;
            }
            if ($rowvalue == 0x5D0 || $rowvalue == 0x5D) {
                $score += 40;
            }
            if ($colvalue == 0x5D0 || $colvalue == 0x5D) {
                $score += 40;
            }
            for ($j = 11; $j < $size; $j++) {
                $rv = ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) ? 1 : 0;
                $cv = ($matrix[$j][$i] == 5 || $matrix[$j][$i] == 3) ? 1 : 0;
                $rowvalue = (($rowvalue << 1) & 0x7FF) | $rv;
                $colvalue = (($colvalue << 1) & 0x7FF) | $cv;
                if ($rowvalue == 0x5D0 || $rowvalue == 0x5D) {
                    $score += 40;
                }
                if ($colvalue == 0x5D0 || $colvalue == 0x5D) {
                    $score += 40;
                }
            }
        }

        return $score;
    }

    private function qrPenalty4(&$matrix, $size) {
        $dark = 0;
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) {
                    $dark++;
                }
            }
        }
        $dark *= 20;
        $dark /= $size * $size;
        $a = abs(floor($dark) - 10);
        $b = abs(ceil($dark) - 10);

        return min($a, $b) * 10;
    }

    private function qrFinalizeMatrix($matrix, $size, $ecl, $mask, $version) {
        /* Format Info */
        $format = $this->qr_format_info[$ecl * 8 + $mask];
        $matrix[8][0] = $format[0];
        $matrix[8][1] = $format[1];
        $matrix[8][2] = $format[2];
        $matrix[8][3] = $format[3];
        $matrix[8][4] = $format[4];
        $matrix[8][5] = $format[5];
        $matrix[8][7] = $format[6];
        $matrix[8][8] = $format[7];
        $matrix[7][8] = $format[8];
        $matrix[5][8] = $format[9];
        $matrix[4][8] = $format[10];
        $matrix[3][8] = $format[11];
        $matrix[2][8] = $format[12];
        $matrix[1][8] = $format[13];
        $matrix[0][8] = $format[14];
        $matrix[$size - 1][8] = $format[0];
        $matrix[$size - 2][8] = $format[1];
        $matrix[$size - 3][8] = $format[2];
        $matrix[$size - 4][8] = $format[3];
        $matrix[$size - 5][8] = $format[4];
        $matrix[$size - 6][8] = $format[5];
        $matrix[$size - 7][8] = $format[6];
        $matrix[8][$size - 8] = $format[7];
        $matrix[8][$size - 7] = $format[8];
        $matrix[8][$size - 6] = $format[9];
        $matrix[8][$size - 5] = $format[10];
        $matrix[8][$size - 4] = $format[11];
        $matrix[8][$size - 3] = $format[12];
        $matrix[8][$size - 2] = $format[13];
        $matrix[8][$size - 1] = $format[14];

        /* Version Info */
        if ($version >= 7) {
            $version = $this->qr_version_info[$version - 7];
            for ($i = 0; $i < 18; $i++) {
                $r = $size - 9 - ($i % 3);
                $c = 5 - floor($i / 3);
                $matrix[$r][$c] = $version[$i];
                $matrix[$c][$r] = $version[$i];
            }
        }

        /* Patterns & Data */
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                $matrix[$i][$j] &= 1;
            }
        }

        return $matrix;
    }
}
