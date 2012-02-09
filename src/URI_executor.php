<?php
/** @description URI executor for BACKUP-script project */
/*<% point_start('execute'); %>/**/

/**
 * This is a first part of ALL-IN-ONE-FILE build of project
 * Main purpose - provide all possible parameters with URI
 * for list of all parameters look at main file options
 */

/**
 * function to show a progress with plain html style.
 * Just send 4096 commented spaces for shure it been displayed
 * @param $name
 * @param $cur
 * @param $total
 */
function progress(&$val){
    if($val['total']==0)$val['total']=1;
    echo '.'.$val['name'].'['.(100*$val['val']/$val['total']).'%]<!--'.str_pad(' ',4096).'--><br>';
}

/**
 * main execution loop
 */
try{
    // to show faster progress, :)
  // $_GET['progressdelay']=0.3;

    $backup=new BACKUP($_GET);
   // $backup->options('progress','progress');
  // $backup->options('onthefly',true);
    if(!empty($_GET['restore'])) {
        echo $backup->restore()?'':'Fail';
    } else {
        echo $backup->make_backup()?'':'Fail';
    }
} catch (BackupException $e) {
    var_dump($e->getMessage());
}
/*<% point_finish('execute'); %>*/