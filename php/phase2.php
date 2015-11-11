<?php
/**
 * Created by PhpStorm.
 * User: doublench
 * Date: 02.10.15
 * Time: 22:09
 */
require('lib/PHPWavUtils.php');

$allowed = array('wav');

if (isset($_POST['val'])) {
    $pdo = new PDO('mysql:host=localhost;port=8889;dbname=classification', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->query('SET NAMES utf8');

    $query = $pdo->prepare("INSERT INTO value (value) VALUES (?)");
    $query->execute(array(
            $_POST['val']
        )
    );

    $pdo = null;
    die();
}

if (isset($_FILES['upl']) && $_FILES['upl']['error'] == 0) {
    $extension = pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION);

    if (!in_array(strtolower($extension), $allowed)) {
        die('Error!');
    }

    if (move_uploaded_file($_FILES['upl']['tmp_name'], '../uploads/' . $_FILES['upl']['name'])) {
        $pdo = new PDO('mysql:host=localhost;port=8889;dbname=classification', 'root', 'root');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->query('SET NAMES utf8');
        $query = $pdo->prepare("SELECT * FROM value");
        $query->execute();
        $value = 0;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
        }
        $pdo = null;

        $wav = new WavFile(realpath('../uploads/' . $_FILES['upl']['name']));
        $data = array();
        for ($i = 0; $i <= $wav->getNumBlocks(); $i++) {
            $data[] = WavFile::normalizeSample(abs(256 * $wav->getSampleValue($i, 1)), $value);
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
