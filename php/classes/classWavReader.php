<?php

/**
 * Created by PhpStorm.
 * User: doublench
 * Date: 02.10.15
 * Time: 22:02
 */
class WavReader
{
    private static $HEADER_LENGTH = 44;

    public static function ReadWav($filename)
    {
        if (is_readable($filename)) {
            $filesize = filesize($filename);

            if ($filesize < self::$HEADER_LENGTH) {
                return false;
            }

            $handle = fopen($filename, 'rb');
            $wav = array(
                'header' => array(
                    'chunkid' => self::readString($handle, 4),
                    'chunksize' => self::readLong($handle),
                    'format' => self::readString($handle, 4)
                ),
                'subchunk1' => array(
                    'id' => self::readString($handle, 4),
                    'size' => self::readLong($handle),
                    'audioformat' => self::readWord($handle),
                    'numchannels' => self::readWord($handle),
                    'samplerate' => self::readLong($handle),
                    'byterate' => self::readLong($handle),
                    'blockalign' => self::readWord($handle),
                    'bitspersample' => self::readWord($handle)
                ),
                'subchunk2' => array(
                    'id' => self::readString($handle, 4),
                    'size' => self::readLong($handle),
                    'data' => null
                )
            );

            $data = [];
            $peek = $wav["subchunk1"]["bitspersample"];
            $byte = $peek / 8;

            // Number pf bytes for skiping - depends on bitspersample and numchannels.
            // We try to use 0.
            $skeepingBytesCount = $byte * $wav["subchunk1"]["numchannels"];

            $cnt = 0;
            while (!feof($handle)) {
                $cnt++;
                //get number of bytes depending on bitrate
                $val = 0;
                for ($j = 0; $j < $wav["subchunk1"]["numchannels"]; $j++) {
                    $bytes = array();
                    for ($i = 0; $i < $byte; $i++) {
                        $bytes[$i] = fgetc($handle);
                    }
                    if (!isset($bytes[1])) {
                        $bytes[1] = null;
                    }
                    switch ($byte) {
                        //get value for 8-bit wav
                        case 1:

                            $val += self::findValue($bytes[0], $bytes[1]);
                            break;
                        //get value for 16-bit wav
                        case 2:
                            if (ord($bytes[1]) & 128) {
                                $temp = 0;
                            } else {
                                $temp = 128;
                            }
                            $temp = chr((ord($bytes[1]) & 127) + $temp);

                            $val += self::findValue($bytes[0], $temp) / 256;
                            break;
                    }
                }
                $data[] = floor($val / $wav["subchunk1"]["numchannels"]);

                fread($handle, $skeepingBytesCount);
            }

            fclose($handle);
            $wav["subchunk2"]["data"] = $data;

            return $data;
        }
    }


    private static function readString($handle, $length)
    {
        return self::readUnpacked($handle, 'a*', $length);
    }

    private static function readLong($handle)
    {
        return self::readUnpacked($handle, 'V', 4);
    }

    private static function readWord($handle)
    {
        return self::readUnpacked($handle, 'v', 2);
    }

    private static function readUnpacked($handle, $type, $length)
    {
        $r = unpack($type, fread($handle, $length));

        return array_pop($r);
    }

    public static function findValue($byte1, $byte2)
    {
        $byte1 = hexdec(bin2hex($byte1));
        $byte2 = hexdec(bin2hex($byte2));

        return ($byte1 + ($byte2 * 256));
    }
}
