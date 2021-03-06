<?php

class Converter {

    public static function iconv($content, $line_no) {
        return preg_replace_callback('/[\x81-\xfe]([\x40-\x7e]|[\xa1-\xfe])/', function($matches) use ($content, $line_no) {
            $ret = self::toUTF8($matches[0], $line_no);
            return $ret;
        }, $content);
    }

    protected static $_big5_to_cns11643_maps = null;
    protected static $_cns11643_to_utf8_maps = null;

    public function toUTF8($word, $line_no) {
        global $big5Errors;
        if (is_null(self::$_big5_to_cns11643_maps)) {
            self::$_big5_to_cns11643_maps = array();

            // Big5
            $fp = fopen(__DIR__ . '/../CNSCodeConverter/tw/gov/cns11643/transfer/cns_big5.txt', 'r');
            while (false !== ($line = fgets($fp))) {
                if (0 === strpos($line, '#')) {
                    continue;
                }

                list($cns11643, $big5) = preg_split('/\s+/', trim($line), 2);
                self::$_big5_to_cns11643_maps[hexdec($big5) / 256][hexdec($big5) % 256] = $cns11643;
            }
            fclose($fp);

            // Big5e
            $fp = fopen(__DIR__ . '/../CNSCodeConverter/tw/gov/cns11643/transfer/cns_big5e.txt', 'r');
            while (false !== ($line = fgets($fp))) {
                if (0 === strpos($line, '#')) {
                    continue;
                }

                list($cns11643, $big5) = preg_split('/\s+/', trim($line), 2);
                self::$_big5_to_cns11643_maps[hexdec($big5) / 256][hexdec($big5) % 256] = $cns11643;
            }
            fclose($fp);

            // custom map
            foreach (self::$_custom_map_files as $file) {
                $fp = fopen($file, 'r');
                while (false !== ($line = fgets($fp))) {
                    if (0 === strpos($line, '#')) {
                        continue;
                    }

                    list($cns11643, $big5) = preg_split('/\s+/', trim($line), 2);
                    self::$_big5_to_cns11643_maps[hexdec($big5) / 256][hexdec($big5) % 256] = $cns11643;
                }
                fclose($fp);
            }

            // CNS11643 to UTF-8
            self::$_cns11643_to_utf8_maps = array();
            foreach (glob(__DIR__ . '/../CNSCodeConverter/tw/gov/cns11643/transfer/cns_unicode*') as $file) {
                $fp = fopen($file, 'r');
                while (false !== ($line = fgets($fp))) {
                    if (0 === strpos($line, '#')) {
                        continue;
                    }

                    list($cns11643, $utf8) = preg_split('/\s+/', trim($line), 2);
                    $utf8_word = html_entity_decode("&#" . hexdec($utf8) . ";");
                    self::$_cns11643_to_utf8_maps[$cns11643] = $utf8_word;
                }
                fclose($fp);
            }
        }
        $chars = unpack('C2', $word);
        if (!array_key_exists($chars[1], self::$_big5_to_cns11643_maps) or ! array_key_exists($chars[2], self::$_big5_to_cns11643_maps[$chars[1]])) {
            $big5Errors['big5e'][dechex($chars[1] * 256 + $chars[2])] = true;
            return null;
        }
        $cns_word = self::$_big5_to_cns11643_maps[$chars[1]][$chars[2]];
        if (!array_key_exists($cns_word, self::$_cns11643_to_utf8_maps)) {
            $big5Errors['cns11643'][$cns_word] = true;
            return null;
        }
        return self::$_cns11643_to_utf8_maps[$cns_word];
    }

    protected static $_custom_map_files = array();

    public static function addCustomMapFile($file) {
        self::$_custom_map_files[] = $file;
    }

}
