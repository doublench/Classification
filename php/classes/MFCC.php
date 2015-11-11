<?php

class MFCC
{
    public static $framesize = 220;
    public static $filterCount = 12;
    public static $flStart = 300;
    public static $flEnd = 8000;
    public static $coefCount = 10;
    public $fileSize;
    public $sampleRate;
    public $file = [];
    public $Re = [];
    public $Im = [];
    public $Pn = [];
    public $scaleCenter = [];
    public $Fsmp = [];
    public $Xn = [];
    public $Cn = [];
    public $_Hik = [];

    public function __construct($fileArray, $fileSampleRate)
    {
        $this->file = $fileArray;
        $this->fileSize = count($fileArray);
        $this->sampleRate = $fileSampleRate;
    }

    public function getMfcc()
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        $frameiD = 0;

        $this->setCenters();
        $this->setSmp();
        $this->setHik();
        $this->Cn = [];
        for ($i = 0; $i < $this->fileSize;) {
            if ($i + self::$framesize > $this->fileSize) {
                break;
            }
            $frame = [];
            for ($j = 0; $j < self::$framesize; $j++) {
                $frame[$j] = $this->file[$i + $j];
            }
            $this->Pn = [];
            for ($k = 0; $k < self::$framesize; $k++) {

                $this->Pn[] = $this->Fourier($frame, $k);
            }
            $this->setXn($this->Pn);


            $this->getCepstral($this->Xn, $frameiD);

            $i = $i + (int)(self::$framesize / 2);

            $frameiD++;
        }
        // Clearing memory
        $this->file = [];
        $this->Pn = [];
        $this->scaleCenter = [];
        $this->Fsmp = [];
        $this->Xn = [];
        $this->_Hik = [];
    }

    public function Fourier($input, $k)
    {
        $re = 0;
        $im = 0;
        for ($i = 0; $i < self::$framesize; $i++) {
            $re += $input[$i] * cos(2 * M_PI * $k * ($i) / self::$framesize);
            $im += $input[$i] * sin(2 * M_PI * $k * ($i) / self::$framesize);
        }
        $re = (2 / self::$framesize) * $re;
        $im = -(2 / self::$framesize) * $im;

        return $re * $re + $im * $im;
    }

    public function setCenters()
    {
        $this->scaleCenter = [];
        $fl_l = $this->herz2mel(self::$flStart);
        $fl_h = $this->herz2mel(self::$flEnd);
        $len = ($fl_h - $fl_l) / (self::$filterCount - 1);
        for ($i = 0; $i < self::$filterCount; $i++) {
            $this->scaleCenter[] = $this->mel2herz($fl_l + $len * $i);
        }
    }

    public function setSmp()
    {
        $this->Fsmp = [];
        for ($i = 0; $i < self::$filterCount; $i++) {
            $this->Fsmp[] = floor(((self::$framesize + 1) * $this->scaleCenter[$i]) / $this->sampleRate);
        }
    }

    public function setXn($Pn)
    {
        $this->Xn = [];
        for ($i = 1; $i <= self::$coefCount; $i++) {
            $summ = 0.0;
            for ($k = 0; $k < self::$framesize; $k++) {
                $summ += $Pn[$k] * $this->_Hik[$i - 1][$k];
            }
            $this->Xn[] = log($summ);
        }
    }

    public function setHik()
    {
        $this->_Hik = [];
        $val = 0;
        for ($i = 1; $i <= self::$coefCount; $i++) {
            for ($k = 0; $k < self::$framesize; $k++) {
                if ($k < $this->Fsmp[$i - 1]) {
                    $val = 0;
                }
                if ($k >= $this->Fsmp[$i - 1] && $k <= $this->Fsmp[$i]) {
                    $val = ($k - $this->Fsmp[$i - 1]) / ($this->Fsmp[$i] - $this->Fsmp[$i - 1]);
                }
                if ($k <= $this->Fsmp[$i + 1] && $k >= $this->Fsmp[$i]) {
                    $val = ($this->Fsmp[$i + 1] - $k) / ($this->Fsmp[$i + 1] - $this->Fsmp[$i]);
                }
                if ($k > $this->Fsmp[$i + 1]) {
                    $val = 0;
                }
                $this->_Hik[$i - 1][] = $val;
            }
        }
    }

    public function getCepstral($Xn, $frameiD)
    {
        $this->Cn[$frameiD] = [];
        for ($j = 0; $j < self::$coefCount; $j++) {
            $summ = 0;
            for ($k = 0; $k < self::$coefCount; $k++) {
                $summ += $Xn[$k] * cos($j * (2 * $k + 1) * M_PI / (self::$coefCount * 2));
            }
            $this->Cn[$frameiD][] = $summ;
        }
    }

    public function herz2mel($Fhz)
    {
        return 1125 * log(1 + $Fhz / 700);
    }

    public function mel2herz($Fmel)
    {
        return 700 * (exp($Fmel / 1125) - 1);
    }

    public function getVector()
    {
        $vector = [];
        foreach ($this->Cn as $vec) {
            foreach ($vec as $elem) {
                $vector[] = $elem;
            }

        }

        return $vector;
    }

    public function chisqr($subtractArr = [])
    {
        $vec = $this->getVector();

        if (count($vec) < 1 || count($subtractArr) < 1) {
            throw new Exception("Array can not be empty");
        }


        // dynamic programming algorithm
        $dim1 = count($vec) / self::$coefCount;
        $dim2 = count($subtractArr) / self::$coefCount;
        $dp = [];
        for ($j = 0; $j < $dim2; $j++) {
            $dp[0][$j] = $this->get_dis($vec, 0, $subtractArr, $j * self::$coefCount, self::$coefCount);
        }
        for ($i = 1; $i < $dim1; $i++) {
            for ($j = 0; $j < $dim2; $j++) {
                $dis = $this->get_dis($vec, $i * self::$coefCount, $subtractArr, $j * self::$coefCount,
                    self::$coefCount);
                $dp[1][$j] = $dp[0][$j];
                if ($j > 0) {
                    if ($dp[0][$j - 1] < $dp[1][$j]) {
                        $dp[1][$j] = $dp[0][$j - 1];
                    }
                    if ($dp[1][$j - 1] < $dp[1][$j]) {
                        $dp[1][$j] = $dp[1][$j - 1];
                    }
                }
                $dp[1][$j] += $dis;
            }
            for ($j = 0; $j < $dim2; $j++) {
                $dp[0][$j] = $dp[1][$j];
            }
        }
        $dis = $dp[0][$dim2 - 1];

        return $dis;
    }

    public function get_dis($vec1, $offset1, $vec2, $offset2, $len)
    {
        $dis = 0;
        for ($i = 0; $i < $len; $i++) {
            $dis += ($vec1[$offset1 + $i] - $vec2[$offset2 + $i]) * ($vec1[$offset1 + $i] - $vec2[$offset2 + $i]);
        }

        return sqrt($dis);
    }
}
