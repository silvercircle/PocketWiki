<?php
// Simple PHP code to generate a 5-character CAPTCHA image
// and to trap+validate the input code...
// http://MicroApache.amadis.sytes.net
// Requires PHP to have php_gd2.dll loaded as an extension=

// Generate the image ...
session_start();

// Use md5 to generate a random string
$md5 = md5(microtime() * mktime());

// Reduce to 5 characters
$string = substr($md5,0,5);

// Debug code next 2 lines
// echo "String=$string";
// die();

// Create a new image in memory of say 65px X 20px
$captcha = @imagecreate(65, 20)
  or die("Cannot Initialize new GD image stream - is GD module php_gd2.dll loaded?");

// Set the image background colo(u)r
$background_color = imagecolorallocate($captcha, 0, 0, 0);

// Create a mapped black colour value
$black = imagecolorallocate($captcha, 255, 255, 255);

// Write the string into the image using font #5 at X=9px and Y=2px in black
imagestring($captcha, 5, 9, 2, $string, $black);

// Store the key in a session
$_SESSION['key'] = md5($string);

// Send the image header
header("Content-type: image/png");

// Output the image as a PNG
imagepng($captcha);

/* To use this image - embed it in any HTML form as an image object
   Then POST the HTML form to PHP code which picks up the session var and compares
   it to the field called code.
   Example:

session_start();
if(isset($_SESSION['key']))
{
 if(md5($_POST['code']) != $_SESSION['key'])
 {
  echo "<br><br><center><h1>You must enter the security-code correctly</h1></center>";
  echo "<center><h2>Go back, reload the form and try again!</h2></center>";
  die();
 }
}
*/

?> 