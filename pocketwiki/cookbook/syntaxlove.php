<?php if (!defined('PmWiki')) exit();
/*
   Copyright 2009-2010 Matthias Guenther (matthias.guenther@wikimatze.de)

   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License as
   published by the Free Software Foundation; either version 2 of
   the License, or (at your option) any later version.
   
   To use this recipe just follow the instruction can be found 
   under the recipe-site on pmwiki.org (syntaxlove)!
*/

$RecipeInfo['syntaxlove']['Version']='2010-10-07';

Markup('code',
    '<fulltext',
    '/\\(:codestart (\w*?):\\)(.*?)\\(:codeend:\\)/esi',
    "'<:block>'.Keep(str_replace(array(),
    array(), PSS('<pre class=\"brush: $1\">'.'$2'.'</pre>')))");
