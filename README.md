navix-php
=========

PHP classes for parsing Navi-X playlists and NIPL.

## PLX Parser

    <?php
    require_once('plx_parser.php');
    
    $p = new PlxParser("http://navix.turner3d.net/playlist/week.plx");

    $e = $p->next();
    while(isset($e)) {

    }
    ?>

## NIPL parser

    <?php
    require_once('nipl_parser.php');
    
    $n = new NiplParser("http://www.navixtreme.com/proc/vidxden", "http://www.vidxden.com/5n10nnryx6aw");
    
    $e = $n->parse();
    ?>