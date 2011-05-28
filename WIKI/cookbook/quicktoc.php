<?php

SDV($QuicktocPubDirUrl, '$FarmPubDirUrl/quicktoc');

Markup("quicktoc", "fulltext", 
  "/\\(:quicktoc:\\)/", 
  "<div id='quicktoc'><div id='quicktocframe' onclick='processquicktoc()'><div id='quicktocmsg'></div><h2 id='quicktocheading' class='closed'>Quick Page Table of Contents</h2><div id='quicktoccontents'><p><i>Scanning...</i></p></div></div></div>");

?>