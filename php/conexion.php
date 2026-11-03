<?php
/* 
   CONEXIÓN A LA BASE DE DATOS  */

date_default_timezone_set('America/Monterrey'); // hora 

$servidor = 'localhost';
$usuario  = 'root';       // usuario de XAMPP
$clave    = '';           // en XAMPP no tiene contraseña
$base     = 'gordiflona'; // nombre de la base de datos

// Que MySQL avise de errores para poder atraparlos
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli($servidor, $usuario, $clave, $base);
    $conexion->set_charset('utf8mb4'); // acentos y ñ
} catch (mysqli_sql_exception $e) {
    // No mostramos el error real para no enseñar datos privados
    die('No se pudo conectar a la base de datos.');
}
