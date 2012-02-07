<?php
/*<% point_start('execute'); %>/**/

/**
 * @description URI executor for BACKUP-script project
 * This is a first part of ALL-IN-ONE-FILE build of this project
 * Main purpose - provide all possible parameters with URI
 * for all possible get parameters look at main file options
 */

/**
 * function to show a progress with plain html style
 * @param $name
 * @param $cur
 * @param $total
 */
function progress(&$val){
    static $xname='';
    if($xname!=$val['name']){
        echo "\n";
        $xname=$name;
    }
    if($val['total']==0)$val['total']=1;
    echo '.'.$val['name'].'['.(100*$val['val']/$val['total']).'%] ';
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