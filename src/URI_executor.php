<?php
/**
 * @description URI executor for BACKUP-script project
 * This is a first part of ALL-IN-ONE-FILE build of this project
 * Main purpose - provide all possible parameters with URI
 * for all possible get parameters look at main file options
 */

/* <% point_start('execute'); %> */
/**
 * function to show a progress with plaint html style
 * @param $name
 * @param $cur
 * @param $total
 */
function progress($name,$cur,$total){
    static $xname='';
    if($xname!=$name){
        echo "\n";
        $xname=$name;
    }
    if($total==0)$total=1;
    echo '.'.$name.'['.(100*$cur/$total).'%] ';
}

/**
 * main execution loop
 */
try{
    $backup=new BACKUP($_GET);
    $backup->options('progress','progress');
    if(!empty($_GET['restore'])) {
        echo $backup->restore()?'ok':'Fail';
    } else {
        echo $backup->make_backup()?'ok':'Fail';
    }
} catch (BackupException $e) {
    var_dump($e->getMessage());
}
/*<% point_finish('execute'); %>*/