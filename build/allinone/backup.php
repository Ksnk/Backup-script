<?php
/**
 * ----------------------------------------------------------------------------
 * $Id: BACKUP - MySql backup utility + class , sergekoriakin@gmail.com, Ver : 0.1
 * ----------------------------------------------------------------------------
 * License GNU/LGPL - Serge Koriakin - (C) 2012 $
 * ----------------------------------------------------------------------------
 */


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
    static $progress="<!DOCTYPE html> <html> <head><title>Backup utility</title> <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"> <script type=\"text/javascript\"> function progress(o) { var progress = ''; if (o.val + '' === o.val) { var x = document.getElementById('log'); x.insertBefore(document.createElement('br'), x.firstChild); x.insertBefore(document.createTextNode(o.name + ' ' + o.val), x.firstChild); } else { progress = o.name + ' ' + (100 * o.val / o.total) + '%'; } document.getElementById('progress').innerHTML = progress; } </script> </head> <body> <div id=\"progress\"></div> <div id=\"log\">.</div> </body> </html>";
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
                    $backup->options(array(
                        'method'=>'sql','sql'=>&$_POST['sql'],'code'=>'utf8'));
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
            } else
                echo 'Nothing to do!<br>';
           // echo '<a href="http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI'].'"> Press to return back </a>';
           // header('location:http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI']);
            exit;
        }
        header('Content-type: text/html ; charset=utf-8');
        /*if(empty($_GET['file'])) $file='';
        else $file=trim(dirname($_GET['file']));
        if(!empty($file)) $file.='/';*/
        $filenames=select_files();
        echo "<!DOCTYPE html> <html> <head><title>Mysql Backup utility</title> <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"> <style type=\"text/css\"> table td { vertical-align: top; } textarea, select { min-width: 100px; } iframe { width: 100%; } </style> </head> <body> <form target=\"myframe\" method='post' action='' enctype=\"multipart/form-data\"> <center> <fieldset> <legend><input type='radio' name='type' value='restore' checked='checked'>restore</legend> <table> <tr> <td> <input type=\"file\" name=\"filename\"></td> <td> <textarea name=\"sql\" rows=\"6\"></textarea></td> <td>$filenames </td> </tr> </table> </fieldset> <fieldset> <legend><input type='radio' name='type' value='backup'>backup</legend> <input type=\"checkbox\" name=\"onthefly\"> - не сохранять дамп на сервере </fieldset> <input type=\"submit\" value=\"do it\"> </center> </form> <iframe name=\"myframe\" id=\"myframe\"></iframe> </body> </html>";
    }
} catch (BackupException $e) {
    // todo: заменить var_dump на что-нибудь разумное
    var_dump($e->getMessage());
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
        'password'=>'',
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
    static private $MAXBUF=32768    ;

    /** on-the-fly support*/
    private $fsize,$fltr,$hctx ;

    /** @var bool|\resource */
    private $link = false;

    /** @var string - sql|sql.gz - метод работы с файлами */
    private $method = 'file';

    function log($message){
    }

    /**
     *
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
     * просто конструктор
     * @param array $options - те параметры, которые отличаются от дефолтных
     */
    public function __construct($options=array()){
        /* вот так устанавливаются параметры */
        $this->options(&$options);
        // so let's go
        $this->link = mysql_connect($this->opt['host'], $this->opt['user'], $this->opt['pass']);
        $this->opt['base']=mysql_real_escape_string($this->opt['base']);
        if(!mysql_select_db($this->opt['base'], $this->link)){
            throw new BackupException('Can\'t use `'.$this->opt['base'].'` : ' . mysql_error());
        };
        mysql_query('set NAMES "'.mysql_real_escape_string($this->opt['code']).'";');
    }

    /**
     * просто деструктор
     */
    function __destruct(){
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
        if($mode == 'w' && $this->method=='sql') { // forcibly change type to gzip
            $this->method=$this->opt['method'];
            if(!$this->opt['onthefly']){
                if ($this->method=='sql.gz')
                    $name.='.gz';
                else if($this->method=='sql.bz2')
                    $name.='.bz2';
            }
        }
        $this->fsize = 0;

        if($this->opt['sql'] && $mode=='r'){
            $handle=@fopen("php://temp", "w+b");
            if ($handle === FALSE)
                throw new BackupException('It\' impossible to use `php://temp`, sorry');
            fwrite($handle,preg_replace(
                '~;\s*(insert|create|delete|drop)~i',";\n\\1",
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
            if(!is_readable($name)) return FALSE;
            if($this->method=='sql.gz'){
                return gzopen($name,$mode.($mode == 'w' ? $this->opt['compress'] : ''));
            } else if($this->method=='sql.bz2'){
                return bzopen($name, $mode);
            } else {
                return fopen($name,"{$mode}b");
            }
        }
    }

    function write($handle,$str){
        if(!empty($this->fltr)){
            hash_update($this->hctx, $str);
            $this->fsize+=strlen($str);
        }
        return fwrite($handle,&$str);
    }
    /**
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
     * Читаем дамп и выполняем все Sql найденные в нем.
     * @return bool
     */
    public function restore(){
        $this->log(sprintf('Memory before restore "%s" - %d ',$this->opt['file'],memory_get_usage()));
        $handle=$this->open($this->opt['file']);
        if($handle==FALSE) throw new BackupException('File not found "'.$this->opt['file'].'"');
        $notlast=true;
        $buf='';
        @ignore_user_abort(1); // ибо нефиг
        @set_time_limit(0); // ибо нефиг, again
        //Seek to the end
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
                // устраняем строковые комментарии
                $s=trim(preg_replace('~^\-\-.*?$|^#.*?$~m','',$s));
                if(!empty($s)) {
                    //echo ' x'.strlen($s).' ';
                    $result=mysql_query($s);
                    if(!$result){
                        throw new BackupException('Invalid query: ' . mysql_error() . "\n".'Whole query: ' . $s);
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
        $this->progress('Ok',true);
        $this->log(sprintf('Memory after restore "%s" - %d ',$this->opt['file'],memory_get_usage()));

        return true;
    }

    /**
     * изготавливаем бякап
     * @return bool
     */
    public function make_backup()
    {
        $include=array();$exclude=array();
        $this->log(sprintf('Memory before makebackup "%s" - %d ',$this->opt['file'],memory_get_usage()));
        // делаем регулярки из простой маски
        foreach(array('include','exclude') as $s){
            $$s=explode(',',$this->opt[$s]);
            foreach($$s as &$x){
                $x='~^'.str_replace(array('*','?'),array('.*','.'),$x).'$~';
            }
            unset($x);
        }
        //var_dump($include,$exclude);
        $tables = array(); // список таблиц
        $times = array(); // время последнего изменения
        $total = array(); // время последнего изменения
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
                    $tables[] = $row['Name'];
                    $times[$row['Name']] = $row['Update_time'];
                    $total[$row['Name']] = $row['Rows'];
                    break;
                }
            }
            unset($row);
        }
        unset($include,$exclude);
        //var_dump($tables);
        mysql_free_result($result);

        $this->log(sprintf('Memory 1step makebackup "%s" - %d ',$this->opt['file'],memory_get_usage()));
        @ignore_user_abort(1); // ибо нефиг
        @set_time_limit(0); // ибо нефиг, again

        do{
            if(trim(basename($this->opt['file']))=='') {
                if (dirname($this->opt['file'])=='') $this->opt['file']='./';
                $this->opt['file'].='db-'.$this->opt['base'].'-' . date('Ymd') . '.sql';
            }
            $handle = $this->open($this->opt['file'],'w');
            $this->write($handle, sprintf("--\n"
                .'-- "%s" database with +"%s"-"%s" tables'."\n"
                .'--     '.implode("\n--     ",$tables)."\n"
                .'-- backup created: %s'."\n"
                ."--\n\n"
                ,$this->opt['base'],$this->opt['include'],$this->opt['exclude'],date('j M y H:i:s')));
            $retrow=array();
            $str_len=0;
            //Проходим в цикле по всем таблицам и форматируем данные
            foreach ($tables as $table)
            {

                if(isset($notNum)) unset ($notNum);
                $notNum = array();
                $this->log(sprintf('Memory 3step makebackup "%s" - %d ',$table,memory_get_usage()));
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

                $this->write($handle,"\n\n" . $row2[1] . ";\n\n");

                $result = mysql_unbuffered_query('SELECT * FROM `' . $table.'`',$this->link);
                $rowcnt=0;
                $this->progress(array('name'=>$table,'val'=>0,'total'=>$total[$table]));

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
                    $str_len+=strlen($str);
                    // Смысл - хочется выполнять не очень здоровые SQL запросы, если есть возможность.
                    if($str_len>self::$MAXBUF-60){
                        $this->write($handle,"INSERT INTO `" . $table . "` VALUES\n  ".implode(",\n  ",$retrow).";\n\n");
                        unset($retrow);
                        $retrow=array();
                        $str_len=strlen($str);
                    }
                    $retrow[]=$str;
                    unset($row,$str);
                }
                $this->progress('Ok',true);

                if(count($retrow)>0){
                    $this->write($handle,"INSERT INTO `" . $table . "` VALUES\n  ".implode(",\n  ",$retrow).";\n\n");
                    unset($retrow);
                    $retrow=array();
                    $str_len=0;
                }
                mysql_free_result($result);
                $this->write($handle,"\n");
            }
            //сохраняем файл
            $this->close($handle);

            // не поменялись ли таблицы за время дискотеки?
            $next_try=false;
            $result = mysql_query('SHOW TABLE STATUS FROM `'.$this->opt['base'].'` like "%"');
            while ($row = mysql_fetch_assoc($result))
            {
                if(in_array($row['Name'],$tables)) {
                    if($times[$row['Name']] != $row['Update_time']){
                        $times[$row['Name']] = $row['Update_time'];
                        $next_try=true;
                    }
                }
                unset($row);
            }
            mysql_free_result($result);

        } while($next_try);

        $this->log(sprintf('Memory after makebackup "%s" - %d ',$this->opt['file'],memory_get_usage()));
        return true;
    }
}