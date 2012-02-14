<?php
/** @description URI executor for BACKUP-script project */
/*<% // just a magic
 point_start('progress_html');include('progress.html');point_finish('progress_html');
 point_start('main_html');include('main.html');point_finish('main_html');
 point_start('execute'); %>/**/
/**
 * This is a first part of ALL-IN-ONE-FILE build of project
 * Main purpose - provide all possible parameters with URI
 * for list of all parameters look at main file options
 */

/**
 * function to show a progress with plain html style.
 * Just send 4096 commented spaces for shure it been displayed
 * @param $val
 * @internal param $name
 * @internal param $val
 * @internal param $total
 */
function progress(&$val){
    static $progress="<%=point('progress_html','html2js');%>";
    if(!empty($progress)) {
        header('Content-type: text/html ; charset=utf-8');
        echo($progress);$progress='';
    }
    if($val['total']==0)$val['total']=1;
    printf('<script type="text/javascript">progress(%s);</script><!--'.str_pad(' ',4096).'-->',
        json_encode($val)
    );
}

/**
 * main execution loop
 */
try{
    // filter input arrays a little to avoid bruteforce attack by using this script.
    $backup=new BACKUP(array_diff_key(
        $_GET
        ,array('user'=>1,'pass'=>1)
    ));
    $backup->options('progress','progress');
    // $backup->options('onthefly',true);
    if(isset($_GET['restore'])) {
        echo $backup->restore()?'':'Fail';
    } else if(isset($_GET['backup'])) {
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
                    $backup->options(array(
                        'method'=>'sql','sql'=>&$_POST['sql'],'code'=>'utf8'));
                    $backup->restore();
                } else if (!empty($_POST['files'])) {
                    $backup->options('file',$backup->directory($_POST['files']));
                    $backup->restore();
                }
            } else if('backup'==$_POST['type']){
                //var_dump($_POST);var_dump($_FILES);
                if(!empty($_POST['onthefly'])){
                    $backup->options('onthefly',true);
                }
                $backup->make_backup();
            } else
                echo 'Nothing to do!<br>';
           // echo '<a href="http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI'].'"> Press to return back </a>';
           // header('location:http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI']);
            exit;
        }
        header('Content-type: text/html ; charset=utf-8');
        $a=array();
        foreach(glob($backup->directory."{*.sql,*.sql.gz,*.sql.bz2}",GLOB_BRACE) as $v){
            $a[]=basename($v);
        }
        if(empty($a))
            $filenames= '';
        else
            $filenames= '<select size="6" name="files"><option>'.implode('</option><option>',$a).'</option></select>';

        echo "<%=point('main_html','html2js');%>";
    }
} catch (BackupException $e) {
    // todo: заменить var_dump на что-нибудь разумное
    var_dump($e->getMessage());
}
/*<% point_finish('execute'); %>*/