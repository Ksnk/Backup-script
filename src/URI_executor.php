<?php
/** @description URI executor for BACKUP-script project */
/*<% point_start('execute'); %>/**/

/**
 * This is a first part of ALL-IN-ONE-FILE build of project
 * Main purpose - provide all possible parameters with URI
 * for list of all parameters look at main file options
 */

$progress= <<<HTML
<!DOCTYPE html><html><head><title>Backup utility</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<script type="text/javascript">
function progress(o){
console.log(o,o.name+' '+o.val);
var progress='';
if(o.val+''===o.val){
var x=document.getElementById('log');
x.insertBefore(document.createElement('br'),x.firstChild);
x.insertBefore(document.createTextNode(o.name+' '+o.val),x.firstChild);
} else {
progress=o.name+' '+(100*o.val/o.total)+'%';
}
document.getElementById('progress').innerHTML=progress;
}
</script>
</head><body>
<div id="progress"></div>
<div id="log">.</div>
</body>
</html>
HTML;
/**
 * function to show a progress with plain html style.
 * Just send 4096 commented spaces for shure it been displayed
 * @param $name
 * @param $cur
 * @param $total
 */
function progress(&$val){
    static $progress= <<<HTML
<!DOCTYPE html><html><head><title>Backup utility</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<script type="text/javascript">
function progress(o){
console.log(o,o.name+' '+o.val);
var progress='';
if(o.val+''===o.val){
var x=document.getElementById('log');
x.insertBefore(document.createElement('br'),x.firstChild);
x.insertBefore(document.createTextNode(o.name+' '+o.val),x.firstChild);
} else {
progress=o.name+' '+(100*o.val/o.total)+'%';
}
document.getElementById('progress').innerHTML=progress;
}
</script>
</head><body>
<div id="progress"></div>
<div id="log">.</div>
</body>
</html>
HTML;
    if(!empty($progress)) {
        header('Content-type: text/html ; charset=utf-8');
        echo($progress);$progress='';
    }
    if($val['total']==0)$val['total']=1;
    printf('<script type="text/javascript">progress(%s);</script><!--'.str_pad(' ',4096).'-->',
        json_encode($val)
    );
}

function select_files($s=''){
    if(empty($_GET['file']))
        $file='';
    else if(is_dir($_GET['file']))
        $file=rtrim($_GET['file'],' \/');
    else
        $file=trim(dirname($_GET['file']));
    if(!empty($file))
        $file.='/';
    if(!empty($s)) return $file.$s;
    $a=array();
    foreach(glob($file."{*.sql,*.sql.gz,*.sql.bz2}",GLOB_BRACE) as $v){
        $a[]=basename($v);
    }
    if(empty($a))
        return '';
    else
        return '<select size="6" name="files"><option>'.implode('</option><option>',$a).'</option></select>';
}

/**
 * main execution loop
 */
try{
    // to show faster progress, :)
    $backup=new BACKUP($_GET);
    $backup->options('progress','progress');
    // $backup->options('onthefly',true);
    if(!empty($_GET['restore'])) {
        echo $backup->restore()?'':'Fail';
    } else if(!empty($_GET['backup'])) {
        echo $backup->make_backup()?'':'Fail';
    } else {
        if('POST'==$_SERVER['REQUEST_METHOD']){
            //var_dump($_POST);var_dump($_FILES);
            if('restore'==$_POST['type']){
                // check if file uploaded
                $uploadedfile='';$file='';
                if(!empty($_FILES))
                foreach($_FILES as $f){
                    if(!is_readable($f['tmp_name'])){
                        $uploadedfile=$f['tmp_name'];
                        if(preg_match('/\.(sql|sql\.bz2|sql\.gz)$/i', $f['name'], $m))
                            $backup->options('method',strtolower($m[1]));
                        break;
                    }
                }
                if(!empty($uploadedfile)){
                    $backup->options('file',$uploadedfile);
                    $backup->restore();
                } else if (!empty($_POST['sql'])) {
                    $backup->options('method','sql');
                    $backup->options('sql',&$_POST['sql']);
                    $backup->restore();
                } else if (!empty($_POST['files'])) {
                    $backup->options('file',select_files($_POST['files']));
                    $backup->restore();
                }
            } else if('backup'==$_POST['type']){
                //var_dump($_POST);var_dump($_FILES);
                if(!empty($_POST['onthefly'])){
                    $backup->options('onthefly',true);
                }
                $backup->make_backup();
            }
            echo '<a href="http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI'].'"> Press to return back </a>';
           // header('location:http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI']);
            exit;
        }
        header('Content-type: text/html ; charset=utf-8');
        /*if(empty($_GET['file'])) $file='';
        else $file=trim(dirname($_GET['file']));
        if(!empty($file)) $file.='/';*/
        $filenames=select_files();
        echo <<<HTML
<!DOCTYPE html><html><head><title>Mysql Backup utility</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<style type="text/css"> table td {vertical-align: top;} textarea, select {min-width:100px;}</style>
</head><body>
    <form method='post' action='' enctype="multipart/form-data"><center>
    <fieldset><legend><input type='radio' name='type' value='restore' checked='checked'>restore</legend>
    <table><tr><td>
        <input type="file" name="filename"></td><td>
        <textarea name="sql" rows="6"></textarea></td><td>$filenames
    </td></tr></table>
    </fieldset>
    <fieldset><legend><input type='radio' name='type' value='backup'>backup</legend>
        <input type="checkbox" name="onthefly"> - не сохранять дамп на сервере
    </fieldset>
    <input type="submit" value="do it">
    </center></form>
</body>
</html>
HTML;
    }
} catch (BackupException $e) {
    var_dump($e->getMessage());
}
/*<% point_finish('execute'); %>*/