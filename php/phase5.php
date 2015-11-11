<?php
/**
 * Created by PhpStorm.
 * User: doublench
 * Date: 01.11.15
 * Time: 20:04
 */
set_time_limit(0);

require('lib/PHPWavUtils.php');
require('classes/MFCC.php');

$allowed = array('wav');

if (isset($_FILES['upl']) && $_FILES['upl']['error'] == 0) {
    $extension = pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION);

    if (!in_array(strtolower($extension), $allowed)) {
        die('Error!');
    }

    if (move_uploaded_file($_FILES['upl']['tmp_name'], '../uploads/' . $_FILES['upl']['name'])) {
        $wav = new WavFile(realpath('../uploads/' . $_FILES['upl']['name']));
        $data = array();
        for ($i = 0; $i <= $wav->getNumBlocks(); $i++) {
            $data[] = WavFile::normalizeSample(abs(256 * $wav->getSampleValue($i, 1)), 2);
        }

        $mfcc = new MFCC($data, 8000);
        $mfcc->getMfcc();


        $pdo = new PDO('mysql:host=localhost;port=8889;dbname=classification', 'root', 'root');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->query('SET NAMES utf8');
        $query = $pdo->prepare("SELECT name, vector FROM samples");
        $query->execute();
        $value = array();

        $res = PHP_INT_MAX;
        $class = '';
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $value = unserialize($row['vector']);
            $vector_ = array();
            foreach ($value as $vec) {
                foreach ($vec as $elem) {
                    $vector_[] = $elem;
                }
            }

            $dis = $mfcc->chisqr($vector_);
            if ($dis < $res) {
                $res = $dis;
                $class = $row['name'];
            }
        }

        $files = array(
            'files' =>
                array(
                    0 =>
                        array(
                            'name' => $class,
                        ),
                ),
        );


        $pdo = null;
        die(json_encode($files));
    }
} else {
    die('Error!');
}
