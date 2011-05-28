<?php if (!defined('PmWiki')) exit();

/*	=== Sitemapper ===
 *	Copyright 2007-08 Eemeli Aro <eemeli@gmail.com>
 *
 *	Adds a dynamically generated sitemap to PmWiki.
 *
 *	Developed and tested using the PmWiki 2.2.0-beta series.
 *
 *	To install, add the following line to your local/config.php file :
		include_once("$FarmD/cookbook/sitemapper.php");
 *
 *	For more information, please see the online documentation at
 *		http://www.pmwiki.org/wiki/Cookbook/Sitemapper
 *
 *	Version history
 *		0.5.2 / 2008-09-10
 *			bug fixes: url link titles, removing pages
 *		0.5.1 / 2008-06-26
 *			bug fix: referenced variables in calls to UpdatePage
 *		0.5 / 2008-01-15
 *			no bespoke link generation -- support section & URL links
 *			support multiple mentions on sitemap
 *			markup directives: navigation, cloak, alias
 *			autohandle unlisted group subpages
 *			bug fixes / code rewrite
 *		0.4 / no public release
 *			reworked navigation generation
 *			blank sitemap handling now in SmReadMap
 *			reduced global variables
 *			bug fix: titling for [[group/]] links
 *		0.3 / 2007-08-17
 *			first public release
 *
 *	This program is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License, Version 2, as
 *	published by the Free Software Foundation.
 *	http://www.gnu.org/copyleft/gpl.html
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 */

$RecipeInfo['Sitemapper']['Version'] = '2008-09-10';

## markup additions
Markup( 'sm-opt', '>if', '/\\(:sm-(alias|cloak|nobottom)\\s*(.*?):\\)/ei', "PCache( \$pagename, array( 'sm-$1' => PSS('$2') ) )" );
Markup( 'sm-trail', '>sm-opt', '/\\(:sm-trail:\\)/ei', "SmMarkupTrail(\$pagename)" );
Markup( 'sm-nav', '>sm-opt', '/\\(:sm-nav(.*?):\\)/ei', "SmMarkupNav( \$pagename, PSS('$1') )" );
Markup( 'sm-hide', '<if', '/\\(:sm-hide:\\)/i', '(:if auth edit:)' );
Markup( 'sm-show', '<if', '/\\(:sm-show:\\)/i', '(:ifend:)' );

## file locations
SDV( $SmCacheDir, $WorkDir );
SDV( $SmRootPagename, 'Main.HomePage' );
SDV( $SmSitemapPagename, 'Main.Sitemap' );
SDV( $SmUncategorizedPagename, 'Site.Uncategorized' );

## authorization function
SDV( $SmAuthorized, ( @$GLOBALS['AuthId'] > '' ) );
#SDV( $SmAuthorized, CondAuth( $SmSitemapPagename, 'edit' ) );

## automatic sitemap update
SDV( $EnableSmUpdate, 1 );
SDV( $EnableSmRemoveOnDelete, 1 );
SDV( $EnableSmBatchUpdate, 1 );
SDV( $EnableSmWriteDefaultOnEmpty, 1 );
SDV( $SmDefaultSitemapText, "\n* [[$SmRootPagename|+]]\n** [[$SmSitemapPagename|+]]\n* [[$SmUncategorizedPagename|+]]\n" );
SDV( $SmExcludePatterns, $SearchPatterns['default'] );
SDV( $EnableSmAutogroup, 1 );

## appearance
SDV( $SmParseSuffix, '-' );
SDV( $SmBottomShowlevel, 2 );
SDV( $SmRootLink, "<a href='$ScriptUrl'>Home</a>" );
SDV( $SmTrailSeparator, ' &raquo; ' );

SDV( $HTMLStylesFmt['sm-styles'], "
  div.sm-topnav { margin-bottom:1em; }
  div.sm-topnav li { margin:0px; padding:4px 2px 2px 2px; font-size:11pt; font-weight:bold; }
  div.sm-topnav li li { padding:0 0 0 6px; font-size:9.4pt; font-weight:normal; }
  div.sm-nav ul { list-style:none; padding:0px; margin:0px; }
  div.sm-nav li { margin:0px; padding:0 1em; }
  div.sm-topnav li li.active, div.sm-nav li li.active { font-weight:bold; }
  div.sm-nav a { text-decoration:none; color:black; }
  div.sm-nav a:hover { text-decoration:underline; color:blue; }
  div.sm-nav { float: right; clear: right; border:1px solid #cccccc; margin-top: 1em; padding:4px; background-color:#f9f9f9; }
");

## internal global variables
$SmPagename = '';
$SmMap = array();
$SmNodes = array();
$SmBatchUpdate = FALSE;
$SmTail = array();


/*
 *		READ/PARSE FUNCTIONS
 */

function SmParseMap() {
/*	read & parse the sitemap wiki page
 *		based on ReadTrail in scripts/trails.php
 *		returns -1 on empty text field, else number of parsed nodes
 *		should only be called from SmReadMap
 */
	global $EnablePathInfo, $SuffixPattern, $ScriptUrl;
	global $SmAuthorized, $SmMap;
	global $DefaultPage, $SmSitemapPagename;

	$smpage = ReadPage( $SmSitemapPagename );
	if ( !isset($smpage['text']) ) return -1;

	$SmMap = array();
	$n = 0;
	$ancestry = array();
	$hide = 0;
	foreach( explode( "\n", htmlspecialchars( $smpage['text'], ENT_NOQUOTES ) ) as $x ) {

		# hide non-public sections if not logged in
		if ( preg_match( '/\(:sm\-([^:\)]+):\)/', $x, $opt ) ) {
			$hide = ( ( $opt[1] == 'hide' ) && !$SmAuthorized );
			continue;
		}
		if ($hide) continue;

		# parse markup
		$x = preg_replace( '/\[\[([^\]]+)-&gt;([^\]]+)\]\]/', '[[$2|$1]]', $x );
		if ( !preg_match( "/
			^([#*:]+)		# 1 : item depth
			\s*\[\[
			([^#|][^|]*?)	# 2 : link target
			(?:\|(.+))?		# 3 : link title
			\]\]
			($SuffixPattern)	# 4 : suffix
		/x", $x, $m ) ) continue;

		$tgtpage = MakePageName( $DefaultPage, $m[2] );
		if ( $tgtpage && (trim($m[3])=='+') ) $title = PageVar($tgtpage,'$Title');
		else $title = $m[3] ? $m[3] : NULL;

		# store variables
		$SmMap[$n]['depth'] = $depth = strlen($m[1]);
		$SmMap[$n]['tgtpage'] = ($tgtpage>'') ? $tgtpage : $m[2];
		$SmMap[$n]['markup'] = $x;
		$SmMap[$n]['html'] = MakeLink( $DefaultPage, $m[2], $title, $m[4] );

		for ( $i = $depth; $i < 10; ++$i ) $ancestry[$i] = $n;
		if ($depth>1) $SmMap[$n]['parent'] = $ancestry[$depth-1];

		++$n;
	}
	return $n;
}


function SmReadMap( $loop=FALSE ) {
/*	read the appropriate sitemap, from cache if possible
 *		if no sitemap is found on the given page, write in the template
 */
	global $LastModTime;
	global $SmMap, $SmBatchUpdate;
	global $SmCacheDir, $SmAuthorized, $SmSitemapPagename, $EnableSmWriteDefaultOnEmpty, $SmDefaultSitemapText;

	$cachefile = ( $SmAuthorized ) ? "$SmCacheDir/sitemap_private,cache" : "$SmCacheDir/sitemap_public,cache";
	if ( ( !$loop ) && ( !$SmBatchUpdate ) && ( $SmCacheDir > '' ) && file_exists($cachefile) && ( filemtime($cachefile) > $LastModTime ) ) {
		$SmMap = unserialize( file_get_contents($cachefile) );
	} else {
		$n = SmParseMap();
		if ( $n > 0 ) {
			if ( $SmCacheDir > '' ) {
				$fp = @fopen( $cachefile, 'w' );
				if ( $fp ) {
					fputs( $fp, serialize($SmMap) );
					fclose( $fp );
				} else
					StopWatch('SmReadMap cache write error! (non-fatal)');
			}
		} elseif ($EnableSmWriteDefaultOnEmpty) {
			if ($loop) {
				StopWatch("SmReadMap error persists! write permissions ok?");
				return 1;
			}
			$smpage = ReadPage( $SmSitemapPagename );
			$smoldpage = $smpage;
			if ( array_key_exists( 'text', $smpage ) ) {
				StopWatch("SmReadMap $SmSitemapPagename contains no sitemap! > appending template");
				$smpage['text'] .= $SmDefaultSitemapText;
			} else {
				StopWatch("SmReadMap $SmSitemapPagename doesn't exist! > generating from template");
				$smpage['text'] = $SmDefaultSitemapText;
			}
			UpdatePage( $SmSitemapPagename, $smoldpage, $smpage, array( 'SaveAttributes', 'PostPage' ) );

			if ($SmBatchUpdate) print("\n    writing new sitemap at $SmSitemapPagename");
			StopWatch("SmReadMap retrying");
			SmReadMap(TRUE);
		}
	}
	return 0;
}


/*
 *		SITEMAP UPDATE ON PAGE EDIT
 */

## update the sitemap when a non-listed page is saved
if ($EnableSmUpdate) $EditFunctions[] = 'SmUpdateMap';

function SmRemovePage( &$smtext, $pagename ) {
	global $SmMap;

	StopWatch("SmRemovePage removing $pagename from sitemap");

	$del = array();
	foreach ( $SmMap as $i => $n ) if ( $n['tgtpage'] == $pagename ) $del[] = $i;
	if ( !count($del) ) return 1;

	$smlines = explode( "\n", htmlspecialchars( $smtext, ENT_NOQUOTES ) );

	foreach( array_reverse($del) as $node ) {
		$ni = array_search( $SmMap[$node]['markup'], $smlines );
		if ( $ni === FALSE ) continue;

		$c = count($smlines);
		$depth = $SmMap[$node]['depth'];
		for ( $kid = $ni + 1; $kid < $c; ++$kid ) if ( preg_match('/^([#*:]+)[\s[]/',$smlines[$kid],$km) ) {
			if ( strlen($km[1]) <= $depth ) break;
			$smlines[$kid] = substr($smlines[$kid],1);
		}

		unset($smlines[$ni]);
	}

	$smtext = htmlspecialchars_decode( implode("\n",$smlines), ENT_NOQUOTES );
	return 0;
}

function SmAddPage( &$smtext, $pagename ) {
	global $SmMap, $SmUncategorizedPagename;

	list($group,$name) = explode('.',$pagename);
	$gpn = MakePageName( $pagename, "$group." );

	$parent = $nocat = -1;
	foreach ( $SmMap as $i => $n ) {
		switch ($n['tgtpage']) {
			case $pagename:					return 1;
			case $gpn:						$parent = $i;	break;
			case $SmUncategorizedPagename:	$nocat = $i;	break;
		}
	}

	if ( ($parent<0) && ($nocat<0) ) {
		# no parent, no uncategorized group -> add to end at same level as last item
		$prev = count($SmMap) - 1;
		$depth = $SmMap[$prev]['depth'];
	} else {
		if ($parent<0) $parent = $nocat;
		$prev = $parent;
		$depth = $SmMap[$parent]['depth'] + 1;
		while ( @$SmMap[$prev+1]['depth'] >= $depth ) ++$prev;
	}

	$nl = $SmMap[$prev]['markup'];
	$str = "\n" . str_repeat('*',$depth) . ( ($group==$name) ? " [[$group/|+]]" : " [[$group/$name|+]]" );

	$smtext = str_replace( $nl, $nl.$str, $smtext );
	return 0;
}

## returns 0 on success, >0 on valid escape, <0 on error
function SmUpdateMap( $pagename, &$page, &$new ) {
	global $IsPagePosted, $DeleteKeyPattern, $Author, $Now;
	global $SmMap, $SmBatchUpdate;
	global $EnableSmUpdate, $EnableSmRemoveOnDelete, $SmExcludePatterns, $SmSitemapPagename;

	if ( !( ( $IsPagePosted || $SmBatchUpdate ) && $EnableSmUpdate ) ) return 1;

	$pna = explode('.',$pagename);
	if ( count($pna) != 2 ) {
		StopWatch("SmUpdateMap bad pagename: $pagename!");
		return -1;
	}
	list($group,$name) = $pna;

	# skip any pages that shouldn't be listed
	if ( is_array($SmExcludePatterns) )
		foreach( $SmExcludePatterns as $pat )
			if ( preg_match($pat,$pagename) ) return 1;

	# read the sitemap
	if (!$SmBatchUpdate) SmReadMap();
	$c = count($SmMap);
	if (!$c) {
		if ($SmBatchUpdate) print("\n    empty sitemap for $pagename!");
		else StopWatch("SmUpdateMap empty sitemap for $pagename!");
		return -1;
	}

	$smpage = ReadPage($SmSitemapPagename);
	if ( !isset($smpage['text']) ) {
		# read error or no previously defined sitemap -- shouldn't happen!
		if ($SmBatchUpdate) print("\n    $pagename    sitemap read error!");
		else StopWatch("SmUpdateMap sitemap read error for $pagename!");
		return -1;
	}
	$smoldpage = $smpage;

	if ( preg_match("/$DeleteKeyPattern/",$new['text']) ) {
		if ( !$EnableSmRemoveOnDelete || $SmBatchUpdate ) return 1;
		$r = SmRemovePage( $smpage['text'], $pagename );
		if ($r) return $r;
		$smpage['csum'] = "remove $pagename";
		$smpage["csum:$Now"] = "remove $pagename";
	} else {
		$r = SmAddPage( $smpage['text'], $pagename );
		if ($r) return $r;
		$smpage['csum'] = "add $pagename";
		$smpage["csum:$Now"] = "add $pagename";
	}

	if ( !isset($Author) ) $Author = 'Sitemapper';
	UpdatePage( $SmSitemapPagename, $smoldpage, $smpage, array( 'SaveAttributes', 'PostPage' ) );
	if ($Author=='Sitemapper') unset($Author);

	if ($SmBatchUpdate) print("\n    $pagename    as child of    ".$SmMap[$parent]['tgtpage']);
	return 0;
}


/*
 *		SITEMAP BATCH UPDATE
 */

## batch update all wiki pages into sitemap
if ( $EnableSmUpdate && $EnableSmBatchUpdate && $SmAuthorized ) {
	SDV($HandleActions['sitemapupdate'],'HandleSitemapUpdate');
	SDV($HandleActions['sitemapaddgroups'],'HandleSitemapUpdate');
}

function HandleSitemapUpdate( $pagename ) {
	global $DefaultName;
	global $SmBatchUpdate, $SmExcludePatterns;

	switch ( $GLOBALS['action'] ) {
		case 'sitemapaddgroups':
			$groupsonly = TRUE;
			break;
		case 'sitemapupdate':
			$groupsonly = FALSE;
			break;
		default:
			return;
	}

	list($usec,$sec) = explode(' ',microtime());
	$t0 = $sec + $usec;

	$ls = ListPages($SmExcludePatterns);
	sort($ls);

	# sort group main pages to be filtered first
	$groups = array();
	foreach( $ls as $n ) {
		$an = explode('.',$n);
		if ( ($an[0]==$an[1]) || ($an[1]==$DefaultName) ) $groups[] = $n;
	}

	$addcount = 0;
	$page = ''; $new = '';
	header( "Content-type: text/plain" );
	print( "\n\nSitemap update:\n\n  read ".count($ls)." pages in ".count($groups)." groups.\n\n  added entries to sitemap:\n" );
	$SmBatchUpdate = TRUE;
	SmReadMap();
	foreach( $groups as $n )
		if ( !SmUpdateMap( $n, $page, $new ) ) ++$addcount;
	if (!$groupsonly) {
		$subpages = array_diff( $ls, $groups );
		SmReadMap();
		foreach( $subpages as $n )
			if ( !SmUpdateMap( $n, $page, $new ) ) ++$addcount;
	}
	$SmBatchUpdate = FALSE;

	if ( $addcount )
		print("\n\n  total ".$addcount.' edits.');
	else
		print('  no updates made.');

	list($usec,$sec ) = explode(' ',microtime());
	$t1 = $sec + $usec;

	print("\n\ndone in ".($t1-$t0).' seconds.');
}


/*
 *		FIND THE LOCAL MAP
 */

## sets SmMap and SmNodes for $pagename
## returns FALSE on error, TRUE on success
function SmGetLocalMap( $pagename ) {
	global $PCache, $action;
	global $SmPagename, $SmMap, $SmNodes, $SmTail, $SmParseSuffix, $EnableSmAutogroup;

	static $GotLocalMap = FALSE;

	if ( $GotLocalMap && ( $pagename == $SmPagename ) ) return TRUE;

	$SmPagename = $pagename;
	list($group,$name) = explode('.',$pagename);

	$SmNodes = array();

	# find $SmMap
	if ( SmReadMap() ) {
		StopWatch('SmGetLocalMap read error! > exiting, no map');
		return $GotLocalMap = FALSE;
	}

	## to cloak the page, let's not find it.
	if ( isset($PCache[$pagename]['sm-cloak']) ) {
		$GotLocalMap = FALSE;
		return TRUE;
	}

	## search for page in sitemap
	if ( isset($PCache[$pagename]['sm-alias']) ) {
		## an alias is declared
		$pn = MakePageName( $pagename, $PCache[$pagename]['sm-alias'] );
		if (!$pn) return $GotLocalMap = FALSE;
		$SmTail[] = $PCache[$pagename]['title'] ? $PCache[$pagename]['title'] : $name;
		foreach ( $SmMap as $i => $n ) if ( $n['tgtpage'] == $pn ) {
			$SmNodes[$i] = array();
		}
	} else {
		$pn = $pagename;
		foreach ( $SmMap as $i => $n ) if ( $n['tgtpage'] == $pn ) {
			$SmNodes[$i] = array();
			if ($action=='browse') $SmMap[$i]['html'] = preg_replace( '!<(/?)a.*?>!', '<$1span>', $SmMap[$i]['html'] );
		}
	}

	## suffixed pages, ie. -Draft, -SideBar, etc.
	if ( !count($SmNodes) && $SmParseSuffix && ($p = strrpos($pagename,$SmParseSuffix)) ) {
		$suffix = substr( $pagename, $p + 1 );
		if ( strpos($suffix,'.') === FALSE ) {
			$SmTail[] = $suffix;
			$pn = substr( $pagename, 0, $p );
			foreach ( $SmMap as $i => $n ) if ( $n['tgtpage'] == $pn ) {
				$SmNodes[$i] = array();
			}
		}
	}

	## pages in groups
	if ( !count($SmNodes) && $EnableSmAutogroup ) {
		$pn = MakePageName( $pagename, "$group." );
		$SmTail[] = $PCache[$pagename]['title'] ? $PCache[$pagename]['title'] : $name;
		foreach ( $SmMap as $i => $n ) if ( $n['tgtpage'] == $pn ) {
			$SmNodes[$i] = array();
		}
	}

	if ( !count($SmNodes) ) {
		$GotLocalMap = TRUE;
		return TRUE;
	}

	## show actions
	if ( $action != 'browse' ) {
		$actionnames = array(
			'edit' => 'Editing...',
			'upload' => 'Attachments',
			'diff' => 'History',
			'attr' => 'Attributes',
			'login' => 'Login',
			'rename' => 'Renaming...'
		);
		$SmTail[] = isset($actionnames[$action]) ? $actionnames[$action] : $action;
	}

	# find ancestry
	$a = array_keys($SmNodes);
	foreach( $a as $i ) {
		if ( !isset($SmMap[$i]['parent']) ) continue;
		$p = $SmMap[$i]['parent'];
		foreach ( $a as $k )
			if ( $k == $p ) unset($SmNodes[$k]);
	}
	foreach( $SmNodes as $k => $roots ) {
		$i = $k;
		while ( $SmMap[$i]['depth'] > 1 ) {
			$roots[$i] =& $SmMap[$i];
			$i = $SmMap[$i]['parent'];
			if (!$i) break;
		}
		$roots[$i] =& $SmMap[$i];
		ksort($roots);
		$SmNodes[$k] = $roots;
	}

	$GotLocalMap = TRUE;
	return TRUE;
}


/*
 *		BUILD THE HTML NAVIGATION ELEMENTS
 */

function SmBuildNav( $show, $depth=0, $abs_depth=FALSE, $page=FALSE ) {
/*	build navigation levels down from root
 *		$show : 0-indexed array of [0:self,1:this branch,2:all], depth offset by $d0
 *		        indicates how to show levels of navigation
 *		$depth: offset from current navigation depth
 *		$abs_depth: absolute origin depth of navigation relative to current page
 *		$page : if set, used as origin of the navigation instead of the current page
 *		        also makes $d0 a relative measure from that page's navigation depth
 */
	global $SmMap, $SmNodes;

	if ( !count($show) ) return '';

	if ( $page !== FALSE ) {
		if ( !SmGetLocalMap($page) ) return "<h3 class='wikimessage'>sitemap read error</h3>";
		if ( !count($SmNodes) ) return "<h3 class='wikimessage'>page '$page' not found in sitemap</h3>";
	}

	if ($abs_depth!==FALSE) {
		$n0 = reset(array_keys($SmNodes));
		$d0 = $abs_depth;
	} else {
		$ak = array_keys($SmNodes);
		$n0 = array_shift($ak);
		$d0 = $SmMap[$n0]['depth'];
		foreach( $ak as $i ) if ( $SmMap[$i]['depth'] > $d0 ) {
			$n0 = $i;
			$d0 = $SmMap[$i]['depth'];
		}
	}
	$d0 += $depth;
	if ($d0<1) $d0 = 1;

	# find navigation start node
	if ( ($d0==1) && $show[0] ) {
		$n0 = 0;
	} elseif ( $SmMap[$n0]['depth'] < $d0 ) {
		++$n0;
	} else {
		while ( $SmMap[$n0]['depth'] > $d0 ) {
			if ( !isset($SmMap[$n0]['parent']) ) break;
			$n0 = $SmMap[$n0]['parent'];
		}
		if ($show[0]) {
			$n0 = $SmMap[$n0]['parent'] + 1;
		}
	}

	# build $nav string iteratively
	$dmax = $d0 + count($show) - 1;
	$prevd = $d0 - 1;

	$nav = '';
	$i = $n0;
	$c = count($SmMap);
	while ($i<$c) {
		$thisd = $SmMap[$i]['depth'];
		if ( $thisd < $d0 ) break; # gone past valid branch
		if ( $thisd <= $dmax ) {
			$ancestor = FALSE;
			foreach ( $SmNodes as $roots ) if ( isset($roots[$i]) ) { $ancestor = TRUE; break; }
			if ( $ancestor || $show[$thisd-$d0] ) {
				$deltad = $thisd - $prevd;
				if ( $deltad > 0 ) {
					if ( ( $thisd == $d0 ) && ( $thisd < $dmax ) ) {
						--$deltad;
						$nav .= "\n<ul class='sm-toplevel'>";
					}
					while ( $deltad > 1 ) {
						--$deltad;
						$nav .= "\n<ul>";
					}
					if ( $deltad > 0 ) {
						if ( $thisd == $dmax )
							$nav .= "\n<ul class='sm-bottomlevel'>";
						else
							$nav .= "\n<ul>";
					}
				} else {
					$nav .= '</li>';
					if ( $deltad < 0 )
						while ( $deltad < 0 ) { ++$deltad; $nav .= "\n</ul></li>"; }
				}
				$nav .= ( $ancestor ? "\n<li class='active'>" : "\n<li>" ) . $SmMap[$i]['html'];
				$prevd = $thisd;
				if ( !$ancestor && ( $thisd < $dmax ) && ( $show[$thisd-$d0+1] < 2 ) ) {
					# for non-ancestor, skip all children if not forced
					while ( ++$i < $c )
						if ( $SmMap[$i]['depth'] <= $thisd ) break;
					continue;
				}
			} else {
				# for non-showing non-ancestor, skip all children
				while ( ++$i < $c )
					if ( $SmMap[$i]['depth'] <= $thisd ) break;
				continue;
			}
		}
		++$i;
	}
	while ( $prevd >= $d0 ) { --$prevd; $nav .= "</li>\n</ul>"; }

	return $nav;
}


/*
 *		MARKUP FUNCTIONS
 */

function SmMarkupNav( $pagename, $opt, $keep=TRUE ) {
	$args = ParseArgs($opt);

	$page = isset($args['page']) ? MakePageName($pagename,$args['page']) : $pagename;
	$depth = 0;
	$abs_depth = FALSE;
	if ( isset($args['depth']) ) switch( $args['depth'][0] ) {
		case '+':
		case '-':
			$depth = intval($args['depth']);
			break;
		default:
			$abs_depth = intval($args['depth']);
	}
	if ( isset($args['fmt']) ) {
		$fmt = array();
		foreach( explode( ',', trim($args['fmt']) ) as $f ) $fmt[] = intval($f);
	} else $fmt = array(1);

	$str = SmBuildNav( $fmt, $depth, $abs_depth, $page );
	return $keep ? Keep($str) : $str;
}

function SmMarkupTrail( $pagename, $keep=TRUE ) {
	global $SmNodes, $SmTail;
	global $SmRootPagename, $SmRootLink, $SmTrailSeparator;

	if ( !SmGetLocalMap($pagename) || !$SmNodes ) return;

	$trails = array();
	foreach( $SmNodes as $roots ) {
		$trail = array();
		$r0 = reset($roots);
		if ( $r0['tgtpage'] != $SmRootPagename ) $trail[] = $SmRootLink;
		foreach ( $roots as $n ) $trail[] = $n['html'];
		if ($SmTail) $trail = array_merge( $trail, $SmTail );
		$trails[] = implode( $SmTrailSeparator, $trail );
	}
	$str = '<div class="breadcrumb">' . implode("<br />\n",$trails) . '</div>';
	return $keep ? Keep($str) : $str;
}


/*
 *		OUTPUT PRINT FUNCTIONS
 */

function SmPrintNav( $pagename, $opt='' ) {
	echo SmMarkupNav( $pagename, $opt, FALSE );
}

function SmPrintTrail( $pagename ) {
	global $SmRootPagename;
	if ( ( $pagename == $SmRootPagename ) || !strncmp($pagename,'FrontPages',10) ) {echo "<strong>Home</strong>";return;}
	echo SmMarkupTrail( $pagename, FALSE );
}

function SmPrintTop( $pagename ) {
	global $SmRootPagename, $SmSitemapPagename;
	if ( !SmGetLocalMap($pagename) ) return;

	$a = ( ( $pagename == $SmRootPagename ) || ( $pagename == $SmSitemapPagename ) || !strncmp($pagename,'FrontPages',10) ) ? array( 2, 2 ) : array( 2, 1 );
	$top = SmBuildNav( $a, 0, 1 );
	echo "<div class='sm-topnav'>", $top, "</div>\n";
#	echo $top;
}

function SmPrintBottom( $pagename ) {
	global $PCache;
	global $SmMap, $SmNodes;
	global $SmBottomShowlevel;

	if ( isset($PCache[$pagename]['sm-nobottom']) || !SmGetLocalMap($pagename) || !$SmNodes ) return;

	# find top level
	$ak = array_keys($SmNodes);
	$n = array_shift($ak);
	$dn = $SmMap[$n]['depth'];
	foreach( $ak as $i ) if ( $SmMap[$i]['depth'] > $dn ) {
		$n = $i;
		$dn = $SmMap[$i]['depth'];
	}
	$dr = ( @$SmMap[$n+1]['depth'] > $dn ) ? 0 : -1;
	if ( $dn + $dr < $SmBottomShowlevel ) return;

	$bottom = SmBuildNav( array( 0, 1 ), $dr );
	echo "<div class='sm-nav'>", $bottom, "\n</ul>\n</div>\n";
#	echo "<div class='nav'>", $bottom, "\n</ul>\n</div>\n";
}
