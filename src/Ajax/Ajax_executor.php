<?php
/** @description Ajax executor for BACKUP-script project */
/*<% // just a magic
 POINT::file('progress_html','ajax.progress.html');
 POINT::file('main_html','Ajax_main.html');
 POINT::start('execute'); %>/**/
/**
 * This is a first part of ALL-IN-ONE-FILE build of project
 * Main purpose - provide all possible parameters with URI
 * for list of all parameters look at main file options
 */

define('BACKUP_CONFIG',"backup.config.php");

/* <%=POINT::get('support_functions')%> */
if(!function_exists('json_encode')){
    function json_encode($a)
    {
        if (is_null($a)) return 'null';
        if ($a === false) return 'false';
        if ($a === true) return 'true';
        if (is_scalar($a)) {
            $a = addslashes($a);
            $a = str_replace("\n", '\n', $a);
            $a = str_replace("\r", '\r', $a);
            $a = preg_replace('{(</)(script)}i', "$1\"+\"$2", $a);
            return "\"$a\"";
        }
        $isList = true;
        for ($i=0, reset($a); $i<count($a); $i++, next($a))
            if (key($a) !== $i) { $isList = false; break; }
        $result = array();
        if ($isList) {
            foreach ($a as $v) $result[] = php2js($v);
            return '[ ' . join(', ', $result) . ' ]';
        } else {
            foreach ($a as $k=>$v)
                $result[] = php2js($k) . ': ' . php2js($v);
            return '{ ' . join(', ', $result) . ' }';
        }
    }

}
/**
 * function to show a progress with plain html style.
 * Just send 4096 commented spaces for shure it been displayed
 * @param array $val
 * @internal param $name
 * @internal param $val
 * @internal param $total
 */
function progress($val){
    if($val['total']==0)$val['total']=1;
    show($val,"top.log")  ;
}
function show($val='',$p="top.log"){
    static $progress="<%=point('progress_html','html2js');%>", $store=array();
    if(!empty($progress)) {
        header('Content-type: text/html; charset=UTF-8');
        echo($progress);$progress='';
    }
    if(!empty($val))
        $store[]= json_encode($val);
    if (!empty($p)) {
        $res=$p.'('.implode(');'.$p.'(',$store).');'; $store=array();
         printf('<script type="text/javascript">'.$res.'</script><!--'.str_pad(' ',4096).'-->',
            $res
        );
    }
}

/*
<%
if(!function_exists('rgb')){
function rgb($r,$g,$b){
    $res='#';
    foreach(array($r,$g,$b) as $v) $res.=str_pad(dechex($v),'0',STR_PAD_LEFT);
    return $res;
}
}
 %> */
/* color cheme */
$gray = '<%=rgb(99,100,102)%>';
$lgray ='<%=rgb(217,220,227)%>';
$red = '<%=rgb(152,27,30)%>' ;
$textlink = '<%=rgb(97,134,186)%>';

/**
 * main execution loop
 */
try{
    // filter input arrays a little to avoid bruteforce attack by using this script.
    $backup=new BACKUP(array_diff_key(
        $_GET
        ,array('user'=>1,'pass'=>1)
    ));
    // check if there is an options
    $backup->options('progress','progress');
    /** @var $opt additional options to save form data */
    $opt=array(
 //        'saveincookie' =>'',
        'method' =>'sql.gz',
        'include'=>$backup->getOption('include'),
        'exclude'=>$backup->getOption('exclude'),
    );
    if(is_readable(BACKUP_CONFIG)) {
        $opt=@array_merge($opt,include (BACKUP_CONFIG));
    }
    // $backup->options('onthefly',true);
    if('POST'==$_SERVER['REQUEST_METHOD']){
        $_POST=array_merge( array(
            'files'=>'',
            'code'=>'',
            'type'=>0,
            ),$_POST);
        if (isset($_POST['code_1'])){
            if($_POST['code_1']=='none'){
                $opt['code']='';
            } else {
                $opt['code']=$_POST['code_1'];
            }
        } else if(""!=trim($_POST['code'])){
            $opt['code']=trim($_POST['code']);
        }
        foreach(array('include','exclude','datestr','frompath') as $x)    {
            if(isset($_POST[$x]) && (""!=trim($_POST[$x])))
                $backup->options($x,$_POST[$x]);
        }

        foreach(array('user','pass','host','base','method') as $x)
            if($_POST[$x]{0}!='*') {
                $opt[$x]=$_POST[$x];
            }
        if (isset($_POST['saveatserver'])){

            $result=@file_put_contents(BACKUP_CONFIG,'<'.'?php return '.var_export($opt,true).';') ;
            if($result===false) {
                show('Can\'t store configuration!','');
            } else {
                show('configuration stored!','');
            }
        }
        $backup->options($opt);
        try {
            if(isset($_POST['testnames'])){  // so test names with tar-gzip
                $total=$backup->getFiles();
                foreach($total as $k=>$v)
                    show(sprintf('found `%s` -> `%s` ',$k,$v),'');
                show("Found ".count($total)." files:",'');
            } else if(isset($_POST['zipit'])){
                //$total=$backup->getFiles();
                $phar = new PharData('project.tar');
                $phar->buildFromIterator(
                    new ArrayIterator(
                        $backup->getFiles()
                    ));
                @unlink ('project.tar.gz');
                $phar->compress(Phar::GZ,'.tar.gz');
                unset($phar);
                @unlink ('project.tar');
                show("OK!",'');
            } else if(isset($_POST['unzip'])){
                $phar = new PharData('project.tar.gz');
                $path='';
                if (!empty($_POST['frompath'])) {
                    $path=$_POST['frompath'];
                }
                $phar->extractTo($path);
                unset($phar);
                show("OK!",'');
            } else if(isset($_POST['testinclude'])){
                $total=$backup->getTables();
                foreach($total as $k=>$v)
                    show(sprintf('  `%s` - %d rows',$k,$v),'');
                show("Found ".count($total)." tables:",'');

            } else if('restore'==$_POST['type']){
                // check if file uploaded
                $uploadedfile='';$file='';
                if(!empty($_FILES))
                foreach($_FILES as $f){
                    if(is_readable($f['tmp_name'])){
                        $uploadedfile=$f['tmp_name'];
                        if(preg_match('/\.(sql|sql\.bz2|sql\.gz)$/i', $f['name'], $m))
                            $backup->options('method',strtolower($m[1]));
                        else
                            throw new BackupException(sprintf('File "%s" has unsupported format.',$f['name']));
                        break;
                    } else {
                        throw new BackupException(sprintf('File "%s" unsupported, sorry.',$f['name']),'');
                    }
                }
                if(!empty($uploadedfile)){
                    $backup->options('file',$uploadedfile);
                    show('File uploaded "'.basename($f['name']).'" ','')  ;
                    $backup->restore();
                } else if (!empty($_POST['sql'])) {
                    $backup->options(array(
                        'method'=>'sql','sql'=>&$_POST['sql'],'code'=>'utf8'));
                    $backup->restore();
                } else if (!empty($_POST['files'])) {
                    show('Restoring database from "'.$backup->directory($_POST['files']).'"','');

                    $backup->options('file',$backup->directory($_POST['files']));
                    $backup->restore();
                }
               // show(print_r($_POST,true)."\n".print_r($_FILES,true));
                show('Restoring complete','');
            } else if('backup'==$_POST['type']){
                //var_dump($_POST);var_dump($_FILES);
                if(!empty($_POST['onthefly'])){
                    $backup->options('onthefly',true);
                }
                $backup->make_backup();
            } else {
                //show(print_r($_POST,true));

            }
        } catch (BackupException $e) {
                if($e->getCode()==1045 ) {
                    show('Access denied. Check setting!'
                        .$backup->getOption('pass').'|'
                            .$backup->getOption('user').'|'
                            .$backup->getOption('base').'|'
                            .$backup->getOption('host').'|'
                        ,'')  ;
                } else
                    show($e->getMessage(),'');
        }
        show();
        exit;
    }
    header('Content-type: text/html; charset=UTF-8');
    $a=array();
    if(!isset($_POST['files']))$_POST['files']='';
    $files=glob($backup->directory($_POST['files'])."{*.sql,*.sql.gz,*.sql.bz2}",GLOB_BRACE);
    if(is_array($files) && count($files)>0)
    foreach($files as $v){
        $a[]=basename($v);
    }
    if(empty($a))
        $filenames= '';
    else
        $filenames= '<select size="5" name="files"><option>'.implode('</option><option>',$a).'</option></select>';
   // if(!empty($opt['pass'])) $opt['pass']="********";
    echo form_helper("<%=point('main_html','html2js');%>"
        ,$opt);

} catch (BackupException $e) {
    show($e->getMessage());
}
/*<% point_finish('execute'); %>*/