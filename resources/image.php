<?php
header("Content-type: image/png");

$offsetGauche = 25;
$offsetDroit = 15;

$width = (isset($_GET['width']) ? $_GET['width'] : 240);
$height = (isset($_GET['height']) ? $_GET['height'] : 35);

$start = (isset($_GET['start']) ? $_GET['start'] : '05:00');
$end = (isset($_GET['end']) ? $_GET['end'] : '19:00');

$title = (isset($_GET['title']) ? $_GET['title'] : ' ');

$n = intval(( $width - $offsetGauche - $offsetDroit ) / 24);
$taille = $n * 24;

$nombres = explode(":", $start);
$debut = $nombres[0] * 60 + $nombres[1];
$debut = $offsetGauche + $taille * $debut / 1440;
$nombres = explode(":", $end);
$fin = $nombres[0] * 60 + $nombres[1];
$fin = $offsetGauche + $taille * $fin / 1440;

$image = imagecreate($width, $height);

$blanc = imagecolorallocate($image, 255, 255, 255);

$bleu = imagecolorallocate($image, 0, 0, 255);
$bleuclair = imagecolorallocate($image, 156, 227, 254);

$gris = imagecolorallocate($image, 128, 128, 128);
$grisClair = imagecolorallocate($image, 192, 192, 192);
$noir = imagecolorallocate($image, 0, 0, 0);

for ($i=0; $i<=24; $i++) {
    $x = $i*$n+$offsetGauche;
    if (($i % 6) == 0) {
        ImageLine($image, $x, 15, $x, 25, $gris);
        if ( $i < 12 )
          $str = '0' . $i . ':00';
        else
          $str = $i . ':00';
        imagestring($image, 2, $x-15, 0, $str, $gris);
    } else {
        ImageLine($image, $x, 20, $x, 25, $grisClair);
    }
}
imagestring($image, 2, 0, 20, $title, $noir);

imagefilledrectangle($image, $offsetGauche, 26, $taille + $offsetGauche, 35, $bleuclair);
imagefilledrectangle($image, $debut, 26, $fin, 35, $bleu);

imagepng($image);
