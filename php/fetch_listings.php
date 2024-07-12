<?PHP

$xml = file_get_contents("https://www.xmltvlistings.com/xmltv/get/[APIKEY]/8080/2"); 
file_put_contents("downloads/8080.xml", $xml); 

$xml = file_get_contents("https://www.xmltvlistings.com/xmltv/get/[APIKEY]/8098/2"); 
file_put_contents("downloads/8098.xml", $xml); 

?>
