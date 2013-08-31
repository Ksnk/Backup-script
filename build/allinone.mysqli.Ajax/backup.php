<?php

/**
 * ----------------------------------------------------------------------------------
 * $Id: Backup-script. All about sql-dump for MySql databases,
 * ver: v1.2-12-g8c9fced, Last build: 1308311428
 * status : draft build.
 * GIT: origin	https://github.com/Ksnk/Backup-script (push)$
 * ----------------------------------------------------------------------------------
 * License GNU/LGPL - Serge Koriakin - Jule 2010-2012, sergekoriakin@gmail.com
 * ----------------------------------------------------------------------------------
 */
/*  --- point::execute --- */

/**
 * This is a first part of ALL-IN-ONE-FILE build of project
 * Main purpose - provide all possible parameters with URI
 * for list of all parameters look at main file options
 */

define('BACKUP_CONFIG',"backup.config.php");

/*  --- point::support_functions --- */
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
function form_helper($html, $opt)
{
    foreach ($opt as $k => $v) {
        if (preg_match('/<(\w+)[^>]*name=([\'"]?)' . preg_quote($k) . '\2[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $type = strtolower($m[1][0]);
            if ($type == 'input') {
                if (!preg_match('/type=([\'"]?)(select|button|submit|radio|checkbox)\1/i', $m[0][0], $mm))
                    $type = 'text';
                else
                    $type = strtolower($mm[2]);
            }
            switch ($type) {
                case 'select':
                    if (!is_array($v)) $v = array($v);
                    foreach ($v as $xx)
                        $html = preg_replace('#(value=([\'"]?)' . preg_quote($xx) . '\2)\s*>#', '\1 selected>', $html);
                    break;
                case 'checkbox':
                case 'radio':
                    $html = preg_replace('#(name=([\'"]?)' . preg_quote($k) . '\2\s+value=([\'"]?)' . preg_quote($v) . '\3)>#'
                        , '\1 checked>'
                        , $html);
                    break;
                case 'text':
                    $html = substr($html, 0, $m[0][1])
                        . preg_replace('#(name=([\'"]?)' . preg_quote($k) . '\2)(?:[^>]*value=([\'"]?).*?\3)?#'
                            , '\1 value="' . htmlspecialchars($v) . '"'
                            , $m[0][0])
                        . substr($html, $m[0][1] + strlen($m[0][0]));
                    break;
            }
        }
    }
    return $html;
}

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
    echo form_helper("<!DOCTYPE html> <html> <head><title>Mysql Backup utility</title> <meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\"><script src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js\" type=\"text/javascript\"></script><script type=\"text/javascript\">\nfunction log(o) {\nif (!o) return;\nif (typeof(o) == 'object') {\nif (o.val + '' === o.val)\no = o.name + ' ' + o.val;\nelse {\ndocument.getElementById('progress').innerHTML = o.name + ' ' + (100 * o.val / o.total) + '%';\nreturn;\n}\n}\nvar x = document.getElementById('log');\ndocument.getElementById('progress').innerHTML = '';\nx.insertBefore(document.createElement('br'), x.firstChild);\nx.insertBefore(document.createTextNode(o), x.firstChild);\n}\nfunction show_log(idx) {\nvar log = $('#log_place');\nif (idx == 1 || (idx == 0 && log.css('z-index') == 1)) log.css('z-index', 3);\nelse if (idx == 2 || (idx == 0 && log.css('z-index') == 3)) log.css('z-index', 1);\n}\nfunction _submit() {\nvar a = $('dt.active'), form = $('form', 'dt.active+dd')[0];\nvar x = false;\nif ($('dt.active').attr('id') != 'setup') {\nx = $('input', $('#setup+dd')).clone().css('display', 'none').appendTo(form);\n}\nform.submit();\nshow_log(1);\nif (x) setTimeout(function () {\nx.remove()\n}, 10);\nreturn false;\n}\nfunction setActive(x) {\nshow_log(2);\nvar id = $(x = (x || '#setup')).attr('id');\ndocument.location.hash = id && (id != 'setup') && id || '';\n$('dt.active').removeClass('active').next('dd:eq(0)').hide();\n$(x).addClass('active').next('dd:eq(0)').show();\n}\n$(function () {\n$('label.replace').each(function () {\n$(this).after($($(this).text()).clone(true).removeAttr('id')).remove()\n});\nfunction LookAtHash() {\nsetActive(document.location.hash);\n}\nLookAtHash();\n$(document.body).on('click',function(event){\nvar x=$(event.target);\nif(x.is('dt')){\nsetActive(x);\n} else if((x=$(event.target).parents('dt')).length>0){\nsetActive(x[0]);\n} else if(!(x=$(event.target).parents('#main')).length>0) {\nshow_log(0)\n}\n})\n$('form select[name=files]').dblclick(_submit);\n})\n</script><style type=\"text/css\"> html { height: 100%; overflow: auto; margin: 0; } body { height: 100%; position: relative; overflow: hidden; background-color: #fcfcfc; margin: 0; } body, input, textarea, button { font-family: tahoma, arial, serif; font-size: 14px; line-height: 1.2em; color: $gray; } dl, dt, fieldset, .round { border-radius: 6px; -webkit-border-radius: 6px; -moz-border-radius: 5px; -khtml-border-radius: 10px; } .shaddow { box-shadow: 1px 2px 4px rgba(0, 0, 0, 0.5); } #main { position: absolute; z-index: 2; left: 50%; top: 50%; width: 280px; text-align: left; cursor: default; margin: -161px 0 0 -150px; padding: 1px; } #log_place { position: absolute; padding: 5px 10px; z-index: 1; left: 50%; top: 50%; width: 600px; height: 300px; overflow: auto; text-align: left; cursor: default; margin: -181px 0 0 -300px; background: white; opacity: 0.90; filter: alpha(opacity = 90); } #progress { position:absolute; top:0; left:0; } #log { margin-top:20px; } dl { position: relative; height: 235px; width: 300px; background: white; border: 1px solid $lgray; } dd { position: absolute; display: none; margin: 0; padding: 5px 10px; } dt { padding: 2px 10px; text-align: center; vertical-align: middle; background: $gray; width: 160px; height: 50px; border: 1px solid transparent; color: white; } dt.active { background: $red; color: white; } dt.one { top: 0px; } dt.two { top: 65px; } dt.three { top: 130px; } dt.four { top: 195px; } dt.left { position: absolute; left: -190px; } dt.right { position: absolute; right: -190px; } fieldset { width: 90%; } fieldset.twicerow label { display: block; float: left; width: 50%; } input.half { width: 50%; } .button, button, #filebutton { position: relative; display: block; margin: 5px auto; padding: 5px 10px; text-align: center; vertical-align: middle; background: $lgray; width: 200px; border: 1px solid transparent; color: $red; } #filebutton input { width: 100%; height: 30px; position: absolute; left: 0; top: 0; opacity: 0; filter: alpha(opacity = 0); } select { width: 270px; } </style></head><body><div id=\"main\"><dl class=\"shaddow\"><dt id=\"restoreupl\" class=\"shaddow left one\">Restore.<br>Upload dump and execute</dt><dd><form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\" onsubmit=\"return _submit();\"> <fieldset id=\"code\" class=\"twicerow\"> <legend>code</legend> <input type=\"hidden\" name=\"type\" value=\"restore\"> <label> <input type=\"radio\" name=\"code_1\" value=\"auto\"> auto </label> <label> <input type=\"radio\" name=\"code_1\" value=\"none\"> none </label> <label> <input type=\"radio\" name=\"code_1\" value=\"utf8\"> utf-8 </label> <label> <input type=\"radio\" name=\"code_1\" value=\"cp1251\"> cp1251 </label> <label style=\"width:90%\"> <input class=\"half\" type=\"text\" name=\"code\" onfocus=\"$('input[name=code_1]:checked').removeAttr('checked');\"> other </label> </fieldset><div id=\"filebutton\" class=\"shaddow round\"><input type=\"file\" value=\"dump\" name=\"filename\" onchange=\"return _submit();\"> Upload file</div>Be carefull. Uploading and execution will start automaticatlly after file been selected.<br><label style=\"width:90%\"> <input type=\"checkbox\" name=\"save\"> save file at server </label> </form></dd><dt id=\"restoreclip\" class=\"shaddow left two\">Restore.<br>Paste sql-dump from clipboard</dt><dd><form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\" onsubmit=\"return _submit();\"> <textarea name=\"sql\" rows=\"12\" style=\"width:270px; height:180px;\"></textarea><br><input type=\"hidden\" name=\"type\" value=\"restore\"> <button id=\"process\" class=\"round shaddow\" onclick=\"return _submit();\">Process</button> </form></dd><dt id=\"restore\" class=\"shaddow left three\">Restore. Select a file from server.</dt><dd><form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\" onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> $filenames<br><label class=\"replace\">#process</label> <input type=\"hidden\" name=\"type\" value=\"restore\"> </form></dd><dt id=\"backupld\" class=\"shaddow right one\">Backup.<br>Download file.</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> <label class=\"replace\">#process</label><div id=\"inexclude\"><label> <input type=\"text\" name=\"include\"> include tables<br></label> <label> <input type=\"text\" name=\"exclude\"> exclude tables<br></label> <input type=\"submit\" name=\"testinclude\" class=\"button round shaddow\" value=\"Test names\"></div><input type=\"hidden\" name=\"onthefly\" value=\"1\"> <input type=\"hidden\" name=\"type\" value=\"backup\"> </form></dd><dt id=\"restorefile\" class=\"shaddow left four\">Restore.<br>from tar-zip files.</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> <input type=\"submit\" name=\"unzip\" class=\"button round shaddow\" value=\"Pack files\"> <label> <input type=\"text\" name=\"frompath\"> into directory<br></label> <label> <input type=\"text\" name=\"datestr\"> before this date<br></label> <input type=\"submit\" name=\"testnames\" class=\"button round shaddow\" value=\"Test files\"> <input type=\"hidden\" name=\"onthefly\" value=\"1\"> <input type=\"hidden\" name=\"type\" value=\"backup\"> </form></dd><dt id=\"backupfile\" class=\"shaddow right four\">Backup.<br>tar-zip files.</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> <input type=\"submit\" name=\"zipit\" class=\"button round shaddow\" value=\"Pack files\"> <label> <input type=\"text\" name=\"frompath\"> from directory<br></label> <label> <input type=\"text\" name=\"datestr\"> before this date<br></label> <input type=\"submit\" name=\"testnames\" class=\"button round shaddow\" value=\"Test files\"> <input type=\"hidden\" name=\"onthefly\" value=\"1\"> <input type=\"hidden\" name=\"type\" value=\"backup\"> </form></dd><dt id=\"backup\" class=\"shaddow right two\">Backup.<br>Save file at server.</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label class=\"replace\">#code</label> <label class=\"replace\">#process</label> <label class=\"replace\">#inexclude</label> <input type=\"hidden\" name=\"type\" value=\"backup\"></form></dd><dt id=\"setup\" class=\"shaddow right three\"><br>Setting</dt><dd><form target=\"myframe\" method='post' action='' onsubmit=\"return _submit();\"> <label> <input type=\"text\" name=\"user\"> - name </label><br><label> <input type=\"password\" name=\"pass\"> - password </label><br><label> <input type=\"text\" name=\"base\"> - base name </label><br><label> <input type=\"text\" name=\"host\"> - host </label><br><fieldset> <legend>method</legend> <label> <input type=\"radio\" name=\"method\" value=\"sql.gz\"> gzip </label> <label> <input type=\"radio\" name=\"method\" value=\"sql\"> sql </label> <label> <input type=\"radio\" name=\"method\" value=\"sql.bz2\"> bz2 </label> </fieldset> <label style=\"display:block;\"> <input class=\"button round shaddow\" type=\"submit\" name=\"saveatserver\" value=\"Save at server\"><br></label> </form></dd></dl></div><iframe name=\"myframe\" style=\"display:none;\" src=\"javascript:void(0)\" id=\"myframe\"></iframe><div id=\"log_place\" class=\"round shaddow\"><div id=\"progress\"></div><div id=\"log\"></div></div></body></html> "
        ,$opt);

} catch (BackupException $e) {
    show($e->getMessage());
}


/**
 * Exception для определенности - будет тикать в случае ошибки
 */
class BackupException extends Exception
{
}

/**
 * собственно класс бякапа
 * - умеет читать писать GZ
 * - умеет читать большие и страшные дампы SypexDumper'а и
 *      большие и страшные дампы phpMyAdmin'а
 * - При создании дампа проверяет время изменения таблиц и
 *      в случае изменения - переделывает все заново.
 *   Так что можно не лочить базу - что есть неимоверная польза.
 */
class BACKUP
{

    /**
     * @var int - ограничение на длину одного запроса (нежесткое, как получится,
     *          при первой возможности :))
     * Еще и размер буфера для чтения sql файла
     */
    static private $_MAXBUF = 50000; //32768    ;
    /**
     * @var int - ограничение на количество попыток сделать бякап
     */
    static private $_MAX_REPEAT_BACKUP = 5;
    /**
     * Параметры класса - можно и нужно допилить по месту напильником
     */
    private $_opt = array(
        // настройка на базу
        'host' => 'localhost', // хост
        'user' => 'root', // имя-пароль
        'pass' => '',
        'base' => 'tmp', // имя базы данных
        //  backup-only параметры
        'include' => '*', // маска в DOS стиле со * и ? . backup-only
        'exclude' => '', // маска в DOS стиле со * и ? . backup-only
        'compress' => 9, // уровень компрессии для gz  . backup-only
        'method' => 'sql.gz', // 'sql.gz'|'sql.bz2'|'sql' - использовать gz или нет
        'onthefly' => false, // вывод гзипа прямо в броузер. Ошибки, правда, 
        //теряются напрочь...
        //  both-way параметры
        'file' => '', // имя файла SQL-дампа для чтения или каталог (с / на конце) 
        // для создания базы
        'code' => 'utf8', // set NAMES 'code'
        'progress' => '', // функция для calback'а progress bar'a
        'progressdelay' => 1, // время между тиками callback прогресс бара 
        // [0.5== полсекунды]
        //  restore-only параметры
        'sql' => '', // plain sql to execute with backup class.
    );
    /** on-the-fly support */
    private $fsize, $fltr, $hctx;
    /** make backup support */
    private
        /** @var array - to holdhold table names of INCLUDE-EXCLUDE calculations */
        $tables = array()
        /** @var array - hold tables modfication time */
    , $times = array();
    /** @var bool|\resource */
    private $link = false;
    /** @var string - sql|sql.gz - метод работы с файлами */
    private $method = 'file';

    /**
     * просто конструктор
     *
     * @param array $options - те параметры, которые отличаются от дефолтных
     */
    public function __construct($options = array())
    {
        /* вот так устанавливаются параметры */
        $this->options(&$options);
    }

    /**
     * парамметры установить
     *
     * @param string|mixed $options имя или парамметры
     * @param string $val     значение, если первый параметр строка
     *
     * @return backup
     */
    public function options($options = '', $val = '')
    {
        if (is_array($options)) {
            $this->_opt = array_merge(
                $this->_opt, array_intersect_key($options, $this->_opt)
            );
        } else {
            $this->_opt[$options] = $val;
        }
        return $this;
    }

    /**
     * функция рекурсивно строит список файлов, начиная с
     *   opt[frompath], используя фильтр  datestr
     *
     * получается массив localname -> realname, который и выводится наружу
     *
     * @return array
     */
    public function getFiles()
    {
        $result = array();
        $pastcwd = getcwd();
        $filter = array(
            'before' => 0, 'after' => 0, 'include' => null, 'exclude' => null
        );
        if (!empty($this->_opt['frompath'])) {
            chdir($this->_opt['frompath']);
        }
        if (!empty($this->_opt['datestr'])) {
            $time = strtotime($this->_opt['datestr']);
            $filter['before'] = $time;
        }
        $this->readDirReqursive(
            '', $result, $filter
        );
        chdir($pastcwd);
        return $result;
    }

    /**
     * рекурсивное чтение каталога
     *
     * @param string $dir
     * @param array $result
     * @param array $filter
     *
     *    include - preg's array to check it's possible
     *    exclude - preg's array to check it's impossible
     *    after -  filemtime of file after this time
     *    before - filemtime of file before this time
     */

    private function readDirReqursive($dir, &$result, $filter)
    {
        if ($handle = opendir(empty($dir) ? '.' : $dir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $entry = empty($dir) ? $entry : $dir . '/' . $entry;
                    $stat = stat($entry);
                    $mode = $stat['mode'] & 0170000;
                    if (040000 == $mode) { // it's a directory
                        $this->readDirReqursive($entry, $result, $filter);
                    } else if (0100000 == $mode) {
                        if (0 != $filter['before']
                            && $stat['mtime'] < $filter['before']
                        ) {
                            continue;
                        }
                        if (0 != $filter['after']
                            && $stat['mtime'] > $filter['after']
                        ) {
                            continue;
                        }
                        if (!empty($filter['include'])) {
                            $possible = false;
                            foreach ($filter['include'] as $reg) {
                                if (preg_match($reg, $entry)) {
                                    $possible = true;
                                    break;
                                }
                            }
                            if (!$possible) {
                                continue;
                            }
                        }
                        if (!empty($filter['exclude'])) {
                            foreach ($filter['exclude'] as $reg) {
                                if (preg_match($reg, $entry)) {
                                    continue 2;
                                }
                            }
                        }

                        $result[$entry] = realpath($entry);
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * дайти опции народу
     *
     * @param string $option название опции
     *
     * @return array
     */
    public function getOption($option)
    {
        return isset($this->_opt[$option]) ? $this->_opt[$option] : null;
    }

    /**
     * просто деструктор
     */
    function __destruct()
    {
        if (!empty($this->link)) {
            mysqli_close($this->link);
        }
    }

    /**
     * Читаем дамп и выполняем все Sql найденные в нем.
     *
     * @return bool
     *
     * @throws BackupException file not found
     */
    public function restore()
    {
        $this->log(sprintf('before restore "%s" ', $this->_opt['file']));
        $handle = $this->open($this->_opt['file']);
        if (!is_resource($handle)) {
            throw new BackupException(
                'File not found "' . $this->_opt['file'] . '"'
            );
        }
        $notlast = true;
        $buf = '';
        @ignore_user_abort(1); // ибо нефиг
        @set_time_limit(0); // ибо нефиг, again
        //Seek to the end
        /** @var $line - line coudnt to point to error line */
        $line = 0;
        if ($this->_opt['method'] == 'sql.gz') {
            // find a sizesize
            @gzseek($handle, 0, SEEK_END);
            $total = gztell($handle);
            gzseek($handle, 0, SEEK_SET);
        } else {
            fseek($handle, 0, SEEK_END);
            $total = ftell($handle);
            fseek($handle, 0, SEEK_SET);
        }
        $curptr = 0;
        $this->connect();
        $this->_progress(array('name' => 'restore', 'val' => 0, 'total' => $total));
        do {
            $string = fread($handle, self::$_MAXBUF);
            $xx = explode(";\n", str_replace("\r", "", $buf . $string));

            if (strlen($string) != self::$_MAXBUF) {
                $notlast = false;
            } else {
                $buf = array_pop($xx);
            }
            $this->_progress($curptr += strlen($string));

            foreach ($xx as $s) {
                $clines = 0;
                str_replace("\n", "\n", $s, $clines);
                $line += $clines + 1; // point to last string in executing query.
                // устраняем строковые комментарии
                $s = trim(preg_replace('~^\s*\-\-.*?$|^\s*#.*?$~m', '', $s));
                if (!empty($s)) {
                    //echo ' x'.strlen($s).' ';
                    $result = mysqli_query($this->link, $s);
                    if (!$result) {
                        // let' point to first line
                        str_replace("\n", "\n", $s, $clines);
                        throw new BackupException(sprintf(
                            "Invalid query at line %s: %s\nWhole query: %s"
                            , $line - $clines, mysqli_error($this->link)
                            , str_pad($s, 200)));
                    }
                    if (is_resource($result)) {
                        mysqli_free_result($result);
                    }
                }
            }
            unset($string, $xx); // очищаем наиболее одиозные хапалки памяти
        } while ($notlast);
        unset($buf); // очищаем наиболее одиозные хапалки памяти

        $this->close($handle);

        $this->log(sprintf('after restore "%s" ', $this->_opt['file']));

        return true;
    }

    /**
     * Внутренняя отладочная функция. Имеет содержимое только для специального
     * варианта сборки или для тестовых прогонов
     *
     * @param string $message
     *
     */
    private function log($message)
    {

    }

    /**
     * добиваемся прозрачности GZ и обычного чтения файла
     * Заменитель стандартных open-close.
     * read и write остаются fread и fwrite. Just a magic!
     *
     * @param string $name - имя файла.
     * @param string $mode - режим открытия файла (w|r)
     *
     * @throws BackupException
     *
     * @return resource - вертает результат соответствующей операции
     */
    function open($name, $mode = 'r')
    {
        if (preg_match('/\.(sql|sql\.bz2|sql\.gz)$/i', $name, $m)) {
            $this->method = strtolower($m[1]);
        } else if (!empty($this->_opt['method'])) {
            $this->method = $this->_opt['method'];
        }
        if ($mode == 'w' && $this->method == 'sql') { // forcibly change type to gzip
            //$this->method=$this->_opt['method'];
            if (!$this->_opt['onthefly']) {
                if ($this->method == 'sql.gz') {
                    $name .= '.gz';
                } else if ($this->method == 'sql.bz2') {
                    $name .= '.bz2';
                }
            }
        }
        $this->fsize = 0;

        if ($this->_opt['sql'] && $mode == 'r') {
            $handle = @fopen("php://memory", "w+b");
            if ($handle === FALSE) {
                $handle = @fopen("php://temp", "w+b");
                if ($handle === false) {
                    throw new BackupException(
                        'It\' impossible to use `php://temp`, sorry'
                    );
                }
            }
            //memory
            fwrite($handle, preg_replace(
                '~;\s*(insert|create|delete|add|alter|select|set|drop)~i',
                ";\n\\1", $this->_opt['sql']
            ));
            fseek($handle, 0);
            return $handle;
        } else if ($this->_opt['onthefly'] && $mode == 'w') {
            // gzzip on-the-fly without file
            $this->_opt['progress'] = ''; // switch off progress  :(
            $handle = @fopen("php://output", "wb");
            if ($handle === false) {
                throw new BackupException(
                    'It\' impossible to use `gzip-on-the-fly`, sorry'
                );
            }
            header($_SERVER["SERVER_PROTOCOL"] . ' 200 OK');
            header('Content-Type: application/octet-stream');
            // header means it's possible to skip filesize header
            header('Connection: keep-alive');
            header(
                'Content-Disposition: attachment; filename="' .
                    basename($name . '.gz') . '";'
            );
            // write gzip header
            fwrite($handle, "\x1F\x8B\x08\x08" . pack("V", time()) . "\0\xFF", 10);
            // write the original file name
            $oname = str_replace("\0", "", $name); //TODO: wtf?
            fwrite($handle, $oname . "\0", 1 + strlen($oname));
            // add the deflate filter using default compression level
            $this->fltr = stream_filter_append(
                $handle, "zlib.deflate", STREAM_FILTER_WRITE, -1
            );
            $this->hctx = hash_init("crc32b"); // set up the CRC32 hashing context
            // turn off the time limit
            if (!ini_get("safe_mode")) {
                set_time_limit(0);
            }
            return $handle;
        } else {
            if ($mode == 'r' && !is_readable($name)) {
                return false;
            }
            if ($this->method == 'sql.bz2') {
                if (function_exists('bzopen')) {
                    return bzopen($name, $mode);
                } else {
                    $this->method = 'sql.gz';
                    $name = preg_replace('/\.bz2$/i', '.gz', $name);
                }
            }
            if ($this->method == 'sql.gz') {
                return gzopen(
                    $name, $mode . ($mode == 'w' ? $this->_opt['compress'] : '')
                );
            } else {
                return fopen($name, "{$mode}b");
            }
        }
    }

    private function connect()
    {
        if (!empty($this->link)) {
            return;
        }
        $this->link = mysqli_connect(
            $this->_opt['host'], $this->_opt['user'], $this->_opt['pass']
        );
        if (mysqli_connect_error()) {
            throw new BackupException(
                'Connect Error (' . mysqli_connect_errno() . ') '
                . mysqli_connect_error()
            );
        }

        // empty - значит нинада!!!
        if (!empty($this->_opt['base'])
            && !mysqli_select_db(
                $this->link,
                mysqli_escape_string($this->link,
                    $this->_opt['base'])
            )
        ) {
            throw new BackupException(
                'Can\'t use `' . $this->_opt['base'] . '` : '
                    . mysqli_error($this->link),
                mysqli_errno($this->link)
            );
        }
        // empty - значит нинада!!!
        if (!empty($this->_opt['code'])) {
            mysqli_query(
                $this->link,
                'set NAMES "' . mysqli_escape_string($this->link, $this->_opt['code']) . '";'
            );
        }
    }

    /**
     * внутренняя функция вывод прогресса операции.
     *
     * @param callable $name хандлер длявызова функции прогресса
     * @param bool $call вызывать, али нет
     *
     * @return mixed
     */
    private function _progress($name, $call = false)
    {
        static $starttime, $param = array();
        if (!is_callable($this->_opt['progress'])) {
            return;
        }
        if (is_array($name)) {
            $param = array_merge($param, $name);
        } else {
            $param['val'] = $name;
        }

        if (!isset($starttime)
            || $call
            || (microtime(true) - $starttime) > $this->_opt['progressdelay']
        ) {
            call_user_func($this->_opt['progress'], &$param);
            $starttime = microtime(true);
        }
    }

    /**
     * заменитель close
     * @param resource $handle
     */
    function close($handle)
    {
        if (!empty($this->fltr)) {
            stream_filter_remove($this->fltr);
            $this->fltr = null;
            // write the original crc and uncompressed file size
            $crc = hash_final($this->hctx, TRUE);
            // need to reverse the hash_final string so it's little endian
            fwrite($handle, $crc[3] . $crc[2] . $crc[1] . $crc[0], 4);
            //fwrite($handle, pack("V", hash_final($this->hctx, TRUE)), 4);
            fwrite($handle, pack("V", $this->fsize), 4);
        }
        // just a magic! No matter a protocol
        fclose($handle);
    }

    /**
     * изготавливаем бякап
     *
     * @throws BackupException
     *
     * @return bool
     */
    public function make_backup()
    {
        $this->log(sprintf('before makebackup "%s" ', $this->_opt['file']));
        $total = $this->getTables();
        $this->log(sprintf('1step makebackup "%s" ', $this->_opt['file']));
        @ignore_user_abort(1); // ибо нефиг
        @set_time_limit(0); // ибо нефиг, again

        $repeat_cnt = self::$_MAX_REPEAT_BACKUP;

        do {
            /** @var array - ключи, которые нужно вставить после основного дампа */
            $postDumpKeys = array();
            if (trim(basename($this->_opt['file'])) == '') {
                $this->_opt['file'] = $this->directory(
                    sprintf('db-%s-%s.sql', $this->_opt['base'], date('Ymd'))
                );
            }
            $handle = $this->open($this->_opt['file'], 'w');
            if (!$handle) {
                throw new BackupException(
                    'Can\'t create file "' . $this->_opt['file'] . '"'
                );
            }
            $this->write(
                $handle, sprintf(
                    "--\n"
                        . '-- "%s" database with +"%s"-"%s" tables' . "\n"
                        . '--     ' . implode("\n--     ", $this->tables) . "\n"
                        . '-- backup created: %s' . "\n"
                        . "--\n\n",
                    $this->_opt['base'], trim($this->_opt['include']),
                    trim($this->_opt['exclude']), date('j M y H:i:s')
                )
            );
            $retrow = array();

            //Проходим в цикле по всем таблицам и форматируем данные
            foreach ($this->tables as $table) {

                if (isset($notNum)) {
                    unset($notNum);
                }
                $notNum = array();
                $this->log(sprintf('3step makebackup "%s" ', $table));
                // нагло потырено у Sipex Dumper'а
                $r = mysqli_query($this->link, "SHOW COLUMNS FROM `$table`");
                $num_fields = 0;
                while ($col = mysqli_fetch_array($r)) {
                    $notNum[$num_fields++] = preg_match(
                        "/^(tinyint|smallint|mediumint|bigint|int|"
                            . "float|double|real|decimal|numeric|year)/",
                        $col['Type']
                    ) ? 0 : 1;
                }
                mysqli_free_result($r);
                $this->write($handle, 'DROP TABLE IF EXISTS `' . $table . '`;');
                $r = mysqli_query($this->link, 'SHOW CREATE TABLE `' . $table . '`');
                $row2 = mysqli_fetch_row($r);
                if (is_resource($r)) {
                    mysqli_free_result($r);
                }
                // обрабатываем CONSTRAINT key
                while (
                    preg_match(
                        '/.*?(,$\s*CONSTRAINT.*?$)/m',
                        $row2[1], $m, PREG_OFFSET_CAPTURE
                    )
                ) {
                    $postDumpKeys[trim($m[1][0], ', ')] = $table;
                    $row2[1] = substr($row2[1], 0, $m[1][1])
                        . substr($row2[1], $m[1][1] + strlen($m[1][0]));
                }

                $this->write($handle, "\n\n" . $row2[1] . ";\n");
                $this->write($handle,
                    "\n/*!50111 ALTER table `$table` DISABLE KEYS */;\n\n"
                );

                $result = mysqli_real_query(
                    $this->link,
                    'SELECT * FROM `' . $table . '`'
                );
                $rowcnt = 0;
                $this->_progress(
                    array(
                        'name' => $table, 'val' => 0, 'total' => $total[$table]
                    )
                );

                $sql_insert_into = "INSERT INTO `" . $table . "` VALUES\n  ";
                $str_len = strlen($sql_insert_into);
                $sql_glue = ",\n  ";
                $result=mysqli_use_result($this->link);
                while ($row = mysqli_fetch_row($result)) {
                    $rowcnt++;
                    $this->_progress($rowcnt);

                    for ($j = 0; $j < $num_fields; $j++) {
                        if (is_null($row[$j])) {
                            $row[$j] = 'NULL';
                        } elseif ($notNum[$j]) {
                            $row[$j] = '\'' . str_replace(
                                '\\"', '"', mysqli_escape_string($this->link, $row[$j])
                            ) . '\'';
                        }
                    }
                    $str = '(' . implode(', ', $row) . ')';
                    $str_len += strlen($str) + 1; //+str_len($sql_glue);
                    // вместо 1 не надо, иначе на phpMySqlAdmin не будет похоже
                    // Смысл - хочется выполнять не очень здоровые SQL запросы,
                    // если есть возможность.
                    if ($str_len > self::$_MAXBUF) {
                        $this->write(
                            $handle, $sql_insert_into
                            . implode($sql_glue, $retrow) . ";\n\n"
                        );
                        unset($retrow);
                        $retrow = array();
                        $str_len = strlen($str) + strlen($sql_insert_into);
                    }
                    $retrow[] = $str;
                    unset($row, $str);
                }
                $this->_progress('Ok', true);

                if (count($retrow) > 0) {
                    $this->write(
                        $handle, $sql_insert_into . implode($sql_glue, $retrow) .
                        ";\n\n"
                    );
                    unset($retrow);
                    $retrow = array();
                }
                mysqli_free_result($result);
                $this->write(
                    $handle, "/*!50111 ALTER table `$table` ENABLE KEYS */;\n"
                );
            }
            if (!empty($postDumpKeys)) {
                foreach ($postDumpKeys as $v => $k) {
                    $this->write(
                        $handle, sprintf("ALTER table `%s` ADD %s;\n\n", $k, $v
                    ));
                }
            }
            //сохраняем файл
            $this->close($handle);
        } while ($this->tableChanged() && ($repeat_cnt--) > 0);

        if ($repeat_cnt <= 0) {
            throw new BackupException(
                'Can\'t create backup. Heavy traffic, sorry. Try another day?' . "\n"
            );
        }

        $this->log(sprintf('after makebackup "%s" ', $this->_opt['file']));
        return true;
    }

    /**
     * get tables names matched width include-exclude mask
     */
    public function getTables()
    {
        $include = array();
        $exclude = array();
        // делаем регулярки из простой маски
        foreach (array('include', 'exclude') as $s) {
            $$s = explode(',', $this->_opt[$s]);
            foreach ($$s as &$x) {
                $x = '~^' . str_replace(
                    array('~', '*', '?'), array('\~', '.*', '.'), trim($x)
                ) . '$~';
            }
            unset($x);
        }

        $total = array(); // время последнего изменения
        $this->connect();
        $result = mysqli_query(
            $this->link,
            'SHOW TABLE STATUS FROM `' . $this->_opt['base'] . '` like "%"'
        );
        if (!$result) {
            throw new BackupException('Invalid query: ' . mysqli_error($this->link) . "\n");
        }
        // запоминаем время модификации таблиц и таблицы, подходящие нам по маске
        while ($row = mysqli_fetch_assoc($result)) {
            foreach ($include as $i) {
                if (preg_match($i, $row['Name'])) {
                    foreach ($exclude as $x) {
                        if (preg_match($x, $row['Name'])) {
                            break 2;
                        }
                    }
                    $this->tables[] = $row['Name'];
                    $this->times[$row['Name']] = $row['Update_time'];
                    $total[$row['Name']] = $row['Rows'];
                    break;
                }
            }
            unset($row);
        }
        unset($include, $exclude);
        //var_dump($this->tables);
        mysqli_free_result($result);
        return $total;
    }

    /**
     * построить имя файла с помощью каталога из параметра opt['files']
     * @param $name
     * @return string
     */
    public function directory($name = '')
    {
        if (empty($this->_opt['file'])) {
            $file = '';
        } else if (is_dir($this->_opt['file'])) {
            $file = rtrim($this->_opt['file'], ' \/');
        } else {
            $file = trim(dirname($this->_opt['file']));
        }
        if (!empty($file)) {
            $file .= '/';
        }
        return $file . $name;
    }

    /**
     * заменитель write - поддержка счетчика записанных байтов.
     * @param $handle
     * @param $str
     * @return int
     */
    function write($handle, $str)
    {
        if (!empty($this->fltr)) {
            hash_update($this->hctx, $str);
            $this->fsize += strlen($str);
        }
        return fwrite($handle, &$str);
    }

    /**
     * вызов функции проверки времени модификации таблиц
     *
     * @return bool
     */
    function tableChanged()
    {
        // не поменялись ли таблицы за время дискотеки?
        $changed = false;
        $result = mysqli_query(
            $this->link,
            'SHOW TABLE STATUS FROM `' . $this->_opt['base'] . '` like "%"'
        );
        while ($row = mysqli_fetch_assoc($result)) {
            if (in_array($row['Name'], $this->tables)) {
                if ($this->times[$row['Name']] != $row['Update_time']) {
                    $this->times[$row['Name']] = $row['Update_time'];
                    $changed = true;
                }
            }
            unset($row);
        }
        mysqli_free_result($result);
        return $changed;
    }

}

/***********************************************************************************
 *
 * License agreement
 * =================
 * 
 * follow <http://www.gnu.org/copyleft/lesser.html>  to  see  a  complete  text  of
 * license
 * 
 *
 **********************************************************************************
 */