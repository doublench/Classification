<?php
/**
 * Created by PhpStorm.
 * User: doublench
 * Date: 02.10.15
 * Time: 22:09
 */
require('lib/PHPWavUtils.php');

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
            $data[] = abs(256 * $wav->getSampleValue($i, 1));
        }

        $files = array(
            'files' =>
                array(
                    0 =>
                        array(
                            'name' => $_FILES['upl']['name'],
                        ),
                ),
            'data' => $data
        );

        die(json_encode($files));
    }
} else {
    die('Error!');
}
