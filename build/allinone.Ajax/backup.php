<?php
/**
 * ----------------------------------------------------------------------------
 * $Id: Backup-script. All about sql-dump for MySql databases,
 * ver: v_1.1-14-g7331f9e, Last build: 
 * status : draft build.
 * GIT: origin	https://github.com/Ksnk/Backup-script (push)$
 * ----------------------------------------------------------------------------
 * License GNU/LGPL - Serge Koriakin - Jule 2010-2012, sergekoriakin@gmail.com
 * ----------------------------------------------------------------------------
 */


/**
 * This is a first part of ALL-IN-ONE-FILE build of project
 * Main purpose - provide all possible parameters with URI
 * for list of all parameters look at main file options
 */

define('BACKUP_CONFIG',"backup.config.php");
    			/*
/**
 * простой заполнитель форм
 * элементы формы не заполнены по умолчанию, кроме полей text   //todo - ликвидировать со временем
 * пара name[ value]- последние атрибуты в элементе формы
 * //todo: обрабатывается только одна форма. Ну и ладно...
 * @param $html
 * @param $opt
 * @return mixed
 */
function form_helper($html,$opt){
    foreach($opt as $k=>$v){
        if (preg_match('/<(\w+)[^>]*name=([\'"]?)'.preg_quote($k).'\2[^>]*>/i',$html,$m,PREG_OFFSET_CAPTURE)){
            $type= strtolower($m[1][0]);
            if ($type=='input')   {
                if(!preg_match('/type=([\'"]?)(select|button|submit|radio|checkbox)\1/i',$m[0][0],$mm))
                    $type='text';
                else
                    $type=strtolower($mm[2]);
            }
            switch($type){
                case 'select':
                    if(!is_array($v)) $v=array($v);
                    foreach($v as $xx)
                        $html=preg_replace('#(value=([\'"]?)'.preg_quote($xx).'\2)\s*>#','\1 selected>',$html);
                    break;
                case 'checkbox':
                case 'radio':
                    $html=preg_replace('#(name=([\'"]?)'.preg_quote($k).'\2\s+value=([\'"]?)'.preg_quote($v).'\3)>#','\1 checked>',$html);
                    break;
                case 'text':
                    $html=substr($html,0,$m[0][1])
                        .preg_replace('#(name=([\'"]?)'.preg_quote($k).'\2)(?:[^>]*value=([\'"]?).*?\3)?#','\1 value="'.htmlspecialchars($v).'"',$m[0][0])
                        .substr($html,$m[0][1]+strlen($m[0][0]));
                    break;
            }
        }
    }
    return $html;
}

/*
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
    static $progress="<!DOCTYPE html> <html><body>", $store=array();
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
/* color cheme */
$gray = '#636466';
$lgray ='#d9dce3';
$red = '#981b1e' ;
$textlink = '#6186ba';

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
        if (isset($_POST['code_1'])){
            if($_POST['code_1']=='none'){
                $opt['code']='';
            } else {
                $opt['code']=$_POST['code_1'];
            }
        } else if(""!=trim($_POST['code'])){
            $opt['code']=trim($_POST['code']);
        }
        if(isset($_POST['include']))
            $backup->options('include',$_POST['include']);
        if(isset($_POST['exclude']))
            $backup->options('exclude',$_POST['exclude']);

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
            if(isset($_POST['testinclude'])){
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
    foreach(glob($backup->directory."{*.sql,*.sql.gz,*.sql.bz2}",GLOB_BRACE) as $v){
        $a[]=basename($v);
    }
    if(empty($a))
        $filenames= '';
    else
        $filenames= '<select size="5" name="files"><option>'.implode('</option><option>',$a).'</option></select>';
   // if(!empty($opt['pass'])) $opt['pass']="********";
    echo form_helper("<!DOCTYPE html> <html> <head><title>Mysql Backup utility</title> <meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\"><script src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js\" type=\"text/javascript\"></script><script type=\"text/javascript\">\nfunction log(o) {\nif(!o) return;\nif(typeof(o)=='object'){\nif (o.val + '' === o.val)\no=o.name + ' ' + o.val;\nelse {\ndocument.getElementById('progress').innerHTML= o.name + ' ' + (100 * o.val / o.total) + '%';\nreturn;\n}\n}\nvar x = document.getElementById('log');\ndocument.getElementById('progress').innerHTML='';\nx.insertBefore(document.createElement('br'), x.firstChild);\nx.insertBefore(document.createTextNode(o), x.firstChild);\n}\nfunction show_log(idx){\nvar log=$('#log_place');\nif(idx==1 || (idx==0 && log.css('z-index')==1)) log.css('z-index',3);\nelse if(idx==2 || (idx==0 && log.css('z-index')==3)) log.css('z-index',1);\n}\nfunction _submit(){\nvar a= $('dt.active'),form=$('form','dt.active+dd')[0];\nvar x=false;\nif($('dt.active').attr('id')!='setup'){\nx=$('input',$('#setup+dd')).clone().css('display','none').appendTo(form);\n}\nform.submit();\nshow_log(1);\nif(x) setTimeout(function(){x.remove()},10);\nreturn false;\n}\n$(function(){\njQuery('label.replace').each(function(){ $(this).after($($(this).text()).clone(true).removeAttr('id')).remove()});\nfunction LookAtHash(){\nvar x=$(document.location.hash || '#setup');\nif(x.length>0) setActive(x);\n}\nfunction setActive(x){\nshow_log(2);\n$('dt.active').removeClass('active').next('dd:eq(0)').hide();\nx.addClass('active').next('dd:eq(0)').show();\n}\nLookAtHash();\n$('dt').click(function () {\ndocument.location.hash = this.id;\nsetActive($(this));\n});\n$('form select[name=files]').dblclick(_submit);\n})\n</script><style type=\"text/css\"> html { height: 100%; overflow: auto; margin: 0; } body { height: 100%; position: relative; overflow: hidden; background-color: #fcfcfc; margin: 0; } body, input,textarea, button { font-family: tahoma, arial,serif; font-size:14px; line-height: 1.2em; color: $gray; } dl, dt, fieldset, .round { border-radius: 6px; -webkit-border-radius: 6px; -moz-border-radius: 5px; -khtml-border-radius: 10px; } .shaddow { box-shadow: 1px 2px 4px rgba(0,0,0,0.5); } #main { position: absolute; z-index:2; left: 50%; top: 50%; width: 280px; text-align: left; cursor: default; margin: -161px 0 0 -150px; padding: 1px; } #log_place { position: absolute; padding:5px 10px; z-index:1; left: 50%; top: 50%; width: 600px; height: 300px; overflow: auto; text-align: left; cursor: default; margin: -181px 0 0 -300px; background:white; opacity: 0.90; filter:alpha(opacity=90); } dl {position:relative; height:235px; width:300px; background: white; border: 1px solid $lgray; } dd { position:absolute; display:none; margin: 0;padding:5px 10px; } dt { padding:5px 10px; text-align:center; vertical-align:middle; background: $gray; width:160px; height:60px; border: 1px solid transparent; color: white; } dt.active { background:$red; color: white; } dt.one {top:0px;} dt.two {top:80px;} dt.three {top:160px;} dt.left { position:absolute; left:-190px; } dt.right { position:absolute; right:-190px; } fieldset { width:90%;} fieldset.twicerow label {display:block; float:left; width:50%;} input.half { width:50%} .button, button, #filebutton { position:relative; display:block; margin:5px auto; padding:5px 10px; text-align:center; vertical-align:middle; background: $lgray; width:200px; border: 1px solid transparent; color: $red; } #filebutton input{ width:100%; height:30px; position:absolute; left:0; top:0; opacity: 0; filter:alpha(opacity=0); } select { width:270px; } </style></head><body><div id=\"main\"><dl class=\"shaddow\"><dt id=\"restoreupl\" class=\"shaddow left one\">Restore.<br>Upload dump and execute</dt><dd><form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\" onsubmit=\"return _submit();\"> <fieldset id=\"code\" class=\"twicerow\"><legend>code</legend> <input type=\"hidden\" name=\"type\" value=\"restore\"> <label> <input type=\"radio\" name=\"code_1\" value=\"auto\"> auto </label> <label> <input type=\"radio\" name=\"code_1\" value=\"none\"> none </label> <label> <input type=\"radio\" name=\"code_1\" value=\"utf-8\"> utf-8 </label> <label> <input type=\"radio\" name=\"code_1\" value=\"cp1251\"> cp1251 </label> <label style=\"width:90%\"> <input class=\"half\" type=\"text\" name=\"code\" onfocus=\"$('input[name=code_1]:checked').removeAttr('checked');\" > other </label> </fieldset><div id=\"filebutton\" class=\"shaddow round\"><input type=\"file\" value=\"dump\" name=\"filename\" onchange=\"return _submit();\"> Upload file</div>Be carefull. Uploading and execution will start automaticatlly after file been selected.<br><label style=\"width:90%\"> <input type=\"checkbox\" name=\"save\"> save file at server </label> </form></dd><dt id=\"restoreclip\" class=\"shaddow left two\">Restore.<br>Paste sql-dump from clipboard</dt><dd><form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\" onsubmit=\"return _submit();\"> <textarea name=\"sql\" rows=\"12\" style=\"width:270px; height:180px;\"></textarea><br><input type=\"hidden\" name=\"type\" value=\"restore\"> <button id=\"process\" class=\"round shaddow\" onclick=\"return _submit();\">Process</button> </form></dd><dt id=\"restore\" class=\"shaddow left three\">Restore. Select a file from server.</dt><dd><form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\" onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> $filenames<br><label class=\"replace\">#process</label> <input type=\"hidden\" name=\"type\" value=\"restore\"> </form></dd><dt id=\"backupld\" class=\"shaddow right one\">Backup.<br>Download file.</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> <label class=\"replace\">#process</label><div id=\"inexclude\"><label> <input type=\"text\" name=\"include\"> include tables<br></label> <label> <input type=\"text\" name=\"exclude\"> exclude tables<br></label> <input type=\"submit\" name=\"testinclude\" class=\"button round shaddow\" value=\"Test names\"></div><input type=\"hidden\" name=\"onthefly\" value=\"1\"> <input type=\"hidden\" name=\"type\" value=\"backup\"> </form></dd><dt id=\"backup\" class=\"shaddow right two\">Backup.<br>Save file at server.</dt><dd><form target=\"myframe\" method='post' action=''onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> <label class=\"replace\">#process</label> <label class=\"replace\">#inexclude</label> <input type=\"hidden\" name=\"type\" value=\"backup\"></form></dd><dt id=\"setup\" class=\"shaddow right three\"><br>Setting</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label> <input type=\"text\" name=\"user\"> - name </label><br><label> <input type=\"password\" name=\"pass\"> - password </label><br><label> <input type=\"text\" name=\"base\"> - base name </label><br><label> <input type=\"text\" name=\"host\"> - host </label><br><fieldset><legend>method</legend> <label> <input type=\"radio\" name=\"method\" value=\"sql.gz\"> gzip </label> <label> <input type=\"radio\" name=\"method\" value=\"sql\"> sql </label> <label> <input type=\"radio\" name=\"method\" value=\"sql.bz2\"> bz2 </label> </fieldset> <label style=\"display:block;\"> <input class=\"button round shaddow\" type=\"submit\" name=\"saveatserver\" value=\"Save at server\"><br></label> </form></dd></dl></div><iframe name=\"myframe\" style=\"display:none;\" src=\"javascript:void(0)\" id=\"myframe\"></iframe><div id=\"log_place\" class=\"round shaddow\" onclick=\"show_log(0)\"><div id=\"progress\" style=\"position:absolute;top:0;left:0; \"></div><div id=\"log\" style=\"margin-top:20px;\"></div></div></body></html>"
        ,$opt);

} catch (BackupException $e) {
    show($e->getMessage());
}


/**
 * Exception для определенности - будет тикать в случае ошибки
 */
class BackupException extends Exception { }

/**
 * собственно класс бякапа
 * - умеет читать писать GZ
 * - умеет читать большие и страшные дампы SypexDumper'а и большие и страшные дампы phpMyAdmin'а
 * - При создании дампа проверяет время изменения таблиц и в случае изменения - переделывает все заново.
 *   Так что можно не лочить базу - что есть неимоверная польза.
 */
class BACKUP {
    /**
     * Параметры класса - можно и нужно допилить по месту напильником
     */
    private $opt=array(
// настройка на базу
        'host'=>'localhost', // хост
        'user'=>'root', // имя-пароль
        'pass'=>'',
        'base'=>'tmp',  // имя базы данных
//  backup-only параметры
        'include'=>'*', // маска в DOS стиле со * и ? . backup-only
        'exclude'=>'',  // маска в DOS стиле со * и ? . backup-only
        'compress'=>9, // уровень компрессии для gz  . backup-only
        'method'=>'sql.gz', // 'sql.gz'|'sql.bz2'|'sql' - использовать gz или нет
        'onthefly'=>false , // вывод гзипа прямо в броузер. Ошибки, правда, теряются напрочь...
//  both-way параметры
        'file'=>'',  // имя файла SQL-дампа для чтения или каталог (с / на конце) для создания базы
        'code'=>'utf8', // set NAMES 'code'
        'progress'=>'', // функция для calback'а progress bar'a
        'progressdelay'=>1, // время между тиками callback прогресс бара [0.5== полсекунды]
//  restore-only параметры
        'sql'=>'', // plain sql to execute with backup class.
    );

    /**
     * @var int - ограничение на длину одного запроса (нежесткое, как получится, при первой возможности :))
     * Еще и размер буфера для чтения sql файла
     */
    static private $MAXBUF=50000;//32768    ;

    /**
     * @var int - ограничение на количество попыток сделать бякап
     */
    static private $MAX_REPEAT_BACKUP = 5 ;

    /** on-the-fly support*/
    private $fsize,$fltr,$hctx ;

    /** make backup support*/
    private
        /** @var array - hold tables name as result of INCLUDE-EXCLUDE calculations */
        $tables=array()
        /** @var array - hold tables modfication time */
        ,$times=array()
    ;

    /** @var bool|\resource */
    private $link = false;

    /** @var string - sql|sql.gz - метод работы с файлами */
    private $method = 'file';

    /**
     * Внутренняя отладочная функция. Имеет содержимое только для специального варианта
     * сборки или для тестовых прогонов
     * @param $message
     */
    private function log($message){

    }

    /**
     * внутренняя функция вывод прогресса операции.
     * @param $name
     * @param bool $call
     * @return mixed
     */
    private function progress($name,$call=false){
        static $starttime,$param=array();
        if(!is_callable($this->opt['progress'])) return;
        if(is_array($name))
            $param=array_merge($param,$name);
        else
            $param['val']=$name;

        if (!isset($starttime) || $call ||(microtime(true)-$starttime)>$this->opt['progressdelay']){
            call_user_func($this->opt['progress'],&$param);
            $starttime=microtime(true);
        }
    }

    /**
     * построить имя файла с помощью каталога из параметра opt['files']
     * @param $name
     * @return string
     */
    public function directory($name=''){
        if(empty($this->opt['file']))
            $file='';
        else if(is_dir($this->opt['file']))
            $file=rtrim($this->opt['file'],' \/');
        else
            $file=trim(dirname($this->opt['file']));
        if(!empty($file))
            $file.='/';
        return $file.$name;
    }

    /**
     * @param string $options
     * @param string $val
     * @return array
     */
    public function options($options='',$val=''){
        if(is_array($options))
            $this->opt=array_merge($this->opt,array_intersect_key($options,$this->opt));
        else
            $this->opt[$options]=$val;
    }
    /**
     * @param string $options
     * @param string $val
     * @return array
     */
    public function getOption($option){
        return isset($this->opt[$option])?$this->opt[$option]:null;
    }
    /**
     * просто конструктор
     * @param array $options - те параметры, которые отличаются от дефолтных
     */
    public function __construct($options=array()){
        /* вот так устанавливаются параметры */
        $this->options(&$options);
    }

    private function connect() {
        if(!empty($this->link)) return ;
        echo $this->opt['host'].' '.$this->opt['user'].' '.$this->opt['pass'];
        $this->link = mysql_connect($this->opt['host'], $this->opt['user'], $this->opt['pass']);
       // $this->opt['base']=mysql_real_escape_string($this->opt['base']);
        if(!mysql_select_db($this->opt['base'], $this->link)){
            throw new BackupException('Can\'t use `'.$this->opt['base'].'` : ' . mysql_error(),mysql_errno());
        };
        // empty - значит нинада!!!
        if(!empty($this->opt['code']))
            mysql_query('set NAMES "'.mysql_real_escape_string($this->opt['code']).'";');
    }

    /**
     * просто деструктор
     */
    function __destruct(){
        if(!empty($this->link))
            mysql_close($this->link);
    }

    /**
     * добиваемся прозрачности GZ и обычного чтения файла
     * Заменитель стандартных open-close. read и write остаются fread и fwrite. Just a magic!
     * @param $name - имя файла.
     * @param string $mode - режим открытия файла (w|r)
     * @return resource - вертает результат соответствующей операции
     */
    function open($name,$mode='r'){
        if(preg_match('/\.(sql|sql\.bz2|sql\.gz)$/i', $name, $m))
            $this->method = strtolower($m[1]);
        else if (!empty($this->opt['method']))
            $this->method=$this->opt['method'];
        if($mode == 'w' && $this->method=='sql') { // forcibly change type to gzip
            //$this->method=$this->opt['method'];
            if(!$this->opt['onthefly']){
                if ($this->method=='sql.gz')
                    $name.='.gz';
                else if($this->method=='sql.bz2')
                    $name.='.bz2';
            }
        }
        $this->fsize = 0;

        if($this->opt['sql'] && $mode=='r'){
            $handle=@fopen("php://memory", "w+b");
            if ($handle === FALSE){
                $handle=@fopen("php://temp", "w+b");
                if ($handle === FALSE)
                    throw new BackupException('It\' impossible to use `php://temp`, sorry');
            }
             //memory
            fwrite($handle,preg_replace(
                '~;\s*(insert|create|delete|alter|select|set|drop)~i',";\n\\1",
                $this->opt['sql']
            ));
            fseek($handle,0);
            return $handle;
        }
        else if($this->opt['onthefly'] && $mode=='w'){ // gzzip on-the-fly without file
            $this->opt['progress']=''; // switch off progress  :(
            $handle=@fopen("php://output", "wb");
            if ($handle === FALSE)
                throw new BackupException('It\' impossible to use `gzip-on-the-fly`, sorry');
            header($_SERVER["SERVER_PROTOCOL"] . ' 200 OK');
            header('Content-Type: application/octet-stream');
            header('Connection: keep-alive'); // so it's possible to skip filesize header
            header('Content-Disposition: attachment; filename="' . basename($name.'.gz') . '";');
            // write gzip header
            fwrite($handle, "\x1F\x8B\x08\x08".pack("V", time())."\0\xFF", 10);
            // write the original file name
            $oname = str_replace("\0", "", $name);//TODO: wtf?
            fwrite($handle, $oname."\0", 1+strlen($oname));
            // add the deflate filter using default compression level
            $this->fltr = stream_filter_append($handle, "zlib.deflate", STREAM_FILTER_WRITE, -1);
            $this->hctx = hash_init("crc32b");// set up the CRC32 hashing context
            // turn off the time limit
            if (!ini_get("safe_mode")) set_time_limit(0);
            return $handle;
        }
        else {
            if($mode=='r' && !is_readable($name)) return FALSE;
            if($this->method=='sql.bz2'){
                if(function_exists('bzopen'))
                    return bzopen($name, $mode);
                else {
                    $this->method='sql.gz';
                    $name=preg_replace('/\.bz2$/i','.gz', $name);
                }
            }
            if($this->method=='sql.gz'){
                return gzopen($name,$mode.($mode == 'w' ? $this->opt['compress'] : ''));
            } else {
                return fopen($name,"{$mode}b");
            }
        }
    }

    /**
     * заменитель write - поддержка счетчика записанных байтов.
     * @param $handle
     * @param $str
     * @return int
     */
    function write($handle,$str){
        if(!empty($this->fltr)){
            hash_update($this->hctx, $str);
            $this->fsize+=strlen($str);
        }
        return fwrite($handle,&$str);
    }
    /**
     * заменитель close
     * @param resource $handle
     */
    function close($handle){
        if(!empty($this->fltr)){
            stream_filter_remove($this->fltr);$this->fltr=null;
            // write the original crc and uncompressed file size
            $crc = hash_final($this->hctx, TRUE);
                // need to reverse the hash_final string so it's little endian
            fwrite($handle, $crc[3].$crc[2].$crc[1].$crc[0], 4);
            //fwrite($handle, pack("V", hash_final($this->hctx, TRUE)), 4);
            fwrite($handle, pack("V",$this->fsize), 4);
        }
        // just a magic! No matter a protocol
        fclose($handle);
    }

    /**
     * get tables names matched width include-exclude mask
     */
    public function getTables(){
        $include=array();$exclude=array();
        // делаем регулярки из простой маски
        foreach(array('include','exclude') as $s){
            $$s=explode(',',$this->opt[$s]);
            foreach($$s as &$x){
                $x='~^'.str_replace(array('~','*','?'),array('\~','.*','.'),$x).'$~';
            }
            unset($x);
        }

        $total = array(); // время последнего изменения
        $this->connect();
        $result = mysql_query('SHOW TABLE STATUS FROM `'.$this->opt['base'].'` like "%"');
        if(!$result){
            throw new BackupException('Invalid query: ' . mysql_error() . "\n");
        }
        // запоминаем время модификации таблиц и таблицы, подходящие нам по маске
        while ($row = mysql_fetch_assoc($result))
        {
            foreach($include as $i){
                if(preg_match($i,$row['Name'])){
                    foreach($exclude as $x)
                        if(preg_match($x,$row['Name'])){
                            break 2;
                        }
                    $this->tables[] = $row['Name'];
                    $this->times[$row['Name']] = $row['Update_time'];
                    $total[$row['Name']] = $row['Rows'];
                    break;
                }
            }
            unset($row);
        }
        unset($include,$exclude);
        //var_dump($this->tables);
        mysql_free_result($result);
        return $total;
    }

    /**
     * Читаем дамп и выполняем все Sql найденные в нем.
     * @return bool
     */
    public function restore(){
        $this->log(sprintf('before restore "%s" ',$this->opt['file']));
        $handle=$this->open($this->opt['file']);
        if(!is_resource($handle))
            throw new BackupException('File not found "'.$this->opt['file'].'"');
        $notlast=true;
        $buf='';
        @ignore_user_abort(1); // ибо нефиг
        @set_time_limit(0); // ибо нефиг, again
        //Seek to the end
        /** @var $line - line coudnt to point to error line */
        $line=0;
        if($this->opt['method']=='sql.gz'){
            // find a sizesize
            @gzseek($handle, 0, SEEK_END);
            $total = gztell($handle);
            gzseek($handle, 0, SEEK_SET);
        } else {
            fseek($handle, 0, SEEK_END);
            $total = ftell($handle);
            fseek($handle, 0, SEEK_SET);
        }
        $curptr=0;
        $this->connect();
        $this->progress(array('name'=>'restore','val'=>0,'total'=>$total));
        do{
            $string=fread($handle,self::$MAXBUF);
            $xx=explode(";\n",str_replace("\r","",$buf.$string));

            if(strlen($string)!=self::$MAXBUF){
                $notlast=false;
            } else {
                $buf=array_pop($xx);
            }
            $this->progress($curptr+=strlen($string));

            foreach($xx as $s){
                $clines=0;
                str_replace("\n","\n",$s,$clines);$line+=$clines+1; // point to last string in executing query.
                // устраняем строковые комментарии
                $s=trim(preg_replace('~^\-\-.*?$|^#.*?$~m','',$s));
                if(!empty($s)) {
                    //echo ' x'.strlen($s).' ';
                    $result=mysql_query($s);
                    if(!$result){
                        // let' point to first line
                        str_replace("\n","\n",$s,$clines);
                        throw new BackupException(sprintf(
                            "Invalid query at line %s: %s\nWhole query: %s"
                        , $line-$clines, mysql_error(),str_pad($s,200)));
                    }
                    if(is_resource($result))
                        mysql_free_result($result);
                }
            };

            unset($string,$xx); // очищаем наиболее одиозные хапалки памяти
        }
        while($notlast);
        unset($buf);// очищаем наиболее одиозные хапалки памяти

        $this->close($handle);

        $this->log(sprintf('after restore "%s" ',$this->opt['file']));

        return true;
    }

    /**
     * вызов функции проверки времени модификации таблиц
     * @return bool
     */
    function tableChanged(){
        // не поменялись ли таблицы за время дискотеки?
        $changed=false;
        $result = mysql_query('SHOW TABLE STATUS FROM `'.$this->opt['base'].'` like "%"');
        while ($row = mysql_fetch_assoc($result))
        {
            if(in_array($row['Name'],$this->tables)) {
                if($this->times[$row['Name']] != $row['Update_time']){
                    $this->times[$row['Name']] = $row['Update_time'];
                    $changed=true;
                }
            }
            unset($row);
        }
        mysql_free_result($result);
        return $changed;
    }

    /**
     * изготавливаем бякап
     * @return bool
     */
    public function make_backup()
    {
        $this->log(sprintf('before makebackup "%s" ',$this->opt['file']));
        $total=$this->getTables();
        $this->log(sprintf('1step makebackup "%s" ',$this->opt['file']));
        @ignore_user_abort(1); // ибо нефиг
        @set_time_limit(0); // ибо нефиг, again

        $repeat_cnt = self::$MAX_REPEAT_BACKUP;

        do {
            /** @var array - ключи, которые нужно вставить после основного дампа */
            $postDumpKeys=array();
            if(trim(basename($this->opt['file']))=='') {
                $this->opt['file']=$this->directory(sprintf('db-%s-%s.sql',$this->opt['base'],date('Ymd')));
            }
            $handle = $this->open($this->opt['file'],'w');
            if(!$handle)
                throw new BackupException('Can\'t create file "'.$this->opt['file'].'"');
            $this->write($handle, sprintf("--\n"
                .'-- "%s" database with +"%s"-"%s" tables'."\n"
                .'--     '.implode("\n--     ",$this->tables)."\n"
                .'-- backup created: %s'."\n"
                ."--\n\n"
                ,$this->opt['base'],$this->opt['include'],$this->opt['exclude'],date('j M y H:i:s')));
            $retrow=array();

            //Проходим в цикле по всем таблицам и форматируем данные
            foreach ($this->tables as $table)
            {

                if(isset($notNum)) unset ($notNum);
                $notNum = array();
                $this->log(sprintf('3step makebackup "%s" ',$table));
                // нагло потырено у Simpex Dumper'а
                $r = mysql_query("SHOW COLUMNS FROM `$table`");
                $num_fields = 0;
                while($col = mysql_fetch_array($r)) {
                    $notNum[$num_fields++] = preg_match("/^(tinyint|smallint|mediumint|bigint|int|float|double|real|decimal|numeric|year)/", $col['Type']) ? 0 : 1;
                }
                mysql_free_result($r);
                $this->write($handle,'DROP TABLE IF EXISTS `' . $table . '`;');
                $r=mysql_query('SHOW CREATE TABLE ' . $table);
                $row2 = mysql_fetch_row($r);
                if(is_resource($r)) mysql_free_result($r);
                // обрабатываем CONSTRAINT key
                while(preg_match('/.*?(,$\s*CONSTRAINT.*?$)/m',$row2[1],$m,PREG_OFFSET_CAPTURE)){
                    $postDumpKeys[trim($m[1][0],', ')]=$table;
                    $row2[1]=substr($row2[1],0,$m[1][1]).substr($row2[1],$m[1][1]+strlen($m[1][0]));
                }

                $this->write($handle,"\n\n" . $row2[1] . ";\n");
                $this->write($handle,"\n/*!50111 ALTER table `$table` DISABLE KEYS */;\n\n");

                $result = mysql_unbuffered_query('SELECT * FROM `' . $table.'`',$this->link);
                $rowcnt=0;
                $this->progress(array('name'=>$table,'val'=>0,'total'=>$total[$table]));

                $sql_insert_into="INSERT INTO `" . $table . "` VALUES\n  ";
                $str_len=strlen($sql_insert_into);
                $sql_glue=",\n  ";

                while ($row = mysql_fetch_row($result))
                {
                    $rowcnt++;
                    $this->progress($rowcnt);

                    for ($j = 0; $j < $num_fields; $j++)
                    {
                        if (is_null($row[$j]))
                            $row[$j] =  'NULL' ;
                        elseif($notNum[$j]){
                            $row[$j] =  '\'' . str_replace('\\"','"',mysql_real_escape_string($row[$j])) . '\'';
                        }
                    }
                    $str='('.implode(', ',$row).')';
                    $str_len+=strlen($str)+1; //+str_len($sql_glue);// вместо 1 не надо, иначе на phpMySqlAdmin не будет похоже
                    // Смысл - хочется выполнять не очень здоровые SQL запросы, если есть возможность.
                    if($str_len>self::$MAXBUF){
                        $this->write($handle,$sql_insert_into.implode($sql_glue,$retrow).";\n\n");
                        unset($retrow);
                        $retrow=array();
                        $str_len=strlen($str)+strlen($sql_insert_into);
                    }
                    $retrow[]=$str;
                    unset($row,$str);
                }
                $this->progress('Ok',true);

                if(count($retrow)>0){
                    $this->write($handle,$sql_insert_into.implode($sql_glue,$retrow).";\n\n");
                    unset($retrow);
                    $retrow=array();
                }
                mysql_free_result($result);
                $this->write($handle,"/*!50111 ALTER table `$table` ENABLE KEYS */;\n");
            }
            if(!empty($postDumpKeys)){
                foreach( $postDumpKeys as $v=>$k) {
                    $this->write($handle,sprintf("ALTER table `%s` ADD %s;\n\n",$k,$v));
                }
            }
            //сохраняем файл
            $this->close($handle);

        } while( $this->tableChanged() && ($repeat_cnt--)>0);

        if($repeat_cnt<=0){
            throw new BackupException('Can\'t create backup. Heavy traffic, sorry. Try another day?' . "\n");
        }

        $this->log(sprintf('after makebackup "%s" ',$this->opt['file']));
        return true;
    }
}

/************************************************************************************
 *
 * License agreement
 * =================
 * 
 * follow <http://www.gnu.org/copyleft/lesser.html>  to  see  a  complete  text  of
 * license
 * 
 *
 ***********************************************************************************
 */