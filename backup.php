<?php
/**
 * ----------------------------------------------------------------------------
 * $Id: BACKUP -  MySql backup utility + class , sergekoriakin@gmail.com, Ver : 0.1
 * ----------------------------------------------------------------------------
 * License GNU/LGPL - Serge Koriakin - (C) 2012 $
 * ----------------------------------------------------------------------------
 * При использовании в админке - убрать все отсюда >>>>>
 */
if(basename($_SERVER['SCRIPT_FILENAME'])==basename(__FILE__)){
    // считаем, что это тот случай, когда скрипт вызвали из адресной строки броузера для
    // использования в качестве web-утилиты
    // backup.php?restore=tmp.sql&code=cp1251
    // backup.php?include=darts_*,SESSION&exclude=*_tmp&code=utf8
    function progress($name,$cur,$total){
        static $xname='';
        if($xname!=$name){
            echo "\n";
            $xname=$name;
        }
        if($total==0)$total=1;
        echo '.'.$name.'['.(100*$cur/$total).'%] ';
    }

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
}
/** досюда <<<<< */
/**
 * для определенности - такое будет тикать в случае ошибки
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
    private $options=array(
// настройка на базу
        'host'=>'localhost', // хост
        'user'=>'root', // имя-парол
        'password'=>'',
        'base'=>'tmp',  // имя базы данных
//  backup-only параметры
        'include'=>'*', // маска в DOS стиле со * и ? . backup-only
        'exclude'=>'',  // маска в DOS стиле со * и ? . backup-only
        'dir'=>'./',    // путь со слешем до каталога хранения бякапов . backup-only
        'compress'=>9, // уровень компрессии для gz  . backup-only
//  both-way параметры
        'code'=>'utf8', // set NAMES 'code'
//  restore-only параметры
        'restore'=>'',  // имя файла SQL-дампа для чтения
        'progressdelay'=>0.01, // время между тиками прогресс бара
    );

    /**
     * @var int - ограничение на длину одного запроса (нежесткое, как получится, при первой возможности :))
     * Еще и размер буфера для чтения sql файла
     */
    static private $MAXBUF=32768    ;

    /** @var bool|\resource */
    private $link = false;

    /** @var string - sql|sql.gz - метод работы с файлами */
    private $method = 'file';

    public function options($options,$val=''){
        if(is_array($options))
            $this->options=array_merge($this->options,array_intersect_key($options,$this->options));
        else
            $this->options[$options]=$val;
    }
    /**
     * просто конструктор
     * @param array $options - те параметры, которые отличаются от дефолтных
     */
    public function __construct($options){
        /* вот так устанавливаются параметры */
        $this->options(&$options);
        // so let's go
        $this->link = mysql_connect($this->options['host'], $this->options['user'], $this->options['pass']);
        $this->options['base']=mysql_real_escape_string($this->options['base']);
        if(!mysql_select_db($this->options['base'], $this->link)){
            throw new BackupException('Can\'t use `'.$this->options['base'].'` : ' . mysql_error());
        };
        mysql_query('set NAMES "'.mysql_real_escape_string($this->options['code']).'";');
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
        if($mode == 'r') {
            if(preg_match('/\.(sql|sql\.gz)$/i', $name, $m))
                $this->method = strtolower($m[1]);
        } else {
            $this->method='sql.gz';// todo: проверить оно есть или ненадо!
            $name.='.gz';
        }
        if($this->method=='sql.gz')
            return gzopen($name,$mode.($mode == 'w' ? $this->options['compress'] : ''));
        else
            return fopen($name,"{$mode}b");
    }
    /**
     * @param resource $handle
     */
    function close($handle){
         if($this->method=='sql.gz')
            gzclose($handle);
        else
            fclose($handle);
    }

    /**
     * Читаем дамп и выполняем все Sql найденные в нем.
     * @return bool
     */
    public function restore(){

        $handle=$this->open($this->options['restore']);
        if(!$handle) throw new BackupException('File not found "'.$this->options['restore'].'"');
        $notlast=true;
        $buf='';
        @ignore_user_abort(1); // ибо нефиг
        do{
            $string=fread($handle,self::$MAXBUF);
            $xx=explode(";\n",str_replace("\r","",$buf.$string));

            if(strlen($string)!=self::$MAXBUF){
                $notlast=false;
            } else {
                $buf=array_pop($xx);
            }
            //echo ' !'.strlen($buf).' ';
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

        }
        while($notlast);
        $this->close($handle);

        return true;
    }

    /**
     * изготавливаем бякап
     * @return bool
     */
    public function make_backup()
    {
        $include=array();$exclude=array();
        // делаем регулярки из простой маски
        foreach(array('include','exclude') as $s){
            $$s=explode(',',$this->options[$s]);
            foreach($$s as &$x){
                $x='~^'.str_replace(array('*','?'),array('.*','.'),$x).'$~';
            }
            unset($x);
        }
        //var_dump($include,$exclude);
        $starttime=microtime(true);
        $tables = array(); // список таблиц
        $times = array(); // время последнего изменения
        $total = array(); // время последнего изменения
        $result = mysql_query('SHOW TABLE STATUS FROM `'.$this->options['base'].'` like "%"');
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
        }
        //var_dump($tables);
        mysql_free_result($result);

        do{
            $handle = $this->open($this->options['dir'].'db-backup-' . date('Ymd') . '.sql','w');
            fwrite($handle, sprintf("--\n"
                .'-- "%s" database with +"%s"-"%s" tables'."\n"
                .'-- backup created: %s'."\n"
                ."--\n\n"
                ,$this->options['base'],$this->options['include'],$this->options['exclude'],date('j M y H:i:s')));
            $retrow=array();
            $str_len=0;
            //Проходим в цикле по всем таблицам и форматируем данные
            foreach ($tables as $table)
            {

                $notNum = array();
                // нагло потырено у Simpex Dumper'а
                $r = mysql_query("SHOW COLUMNS FROM `$table`");
                $num_fields = 0;
                while($col = mysql_fetch_array($r)) {
                    $notNum[$num_fields++] = preg_match("/^(tinyint|smallint|mediumint|bigint|int|float|double|real|decimal|numeric|year)/", $col['Type']) ? 0 : 1;
                }
                fwrite($handle,'DROP TABLE IF EXISTS `' . $table . '`;');
                $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE ' . $table));
                fwrite($handle,"\n\n" . $row2[1] . ";\n\n");

                $result = mysql_unbuffered_query('SELECT * FROM `' . $table.'`',$this->link);
                $rowcnt=0;
                if(isset($this->options['progress'])){
                    call_user_func($this->options['progress'],$table,0,$total[$table]);
                    $starttime=microtime(true);
                }
                while ($row = mysql_fetch_row($result))
                {
                    $rowcnt++;
                    if(isset($this->options['progress'])){
                        if((microtime(true)-$starttime)>$this->options['progressdelay']){
                            call_user_func($this->options['progress'],$table,$rowcnt,$total[$table]);
                            $starttime+=$this->options['progressdelay'];
                        }
                    }
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
                        fwrite($handle,"INSERT INTO `" . $table . "` VALUES\n  ".implode(",\n  ",$retrow).";\n\n");
                        $retrow=array();
                        $str_len=strlen($str);
                    }
                    $retrow[]=$str;
                }
                unset($row);
                if(isset($this->options['progress'])){
                    call_user_func($this->options['progress'],$table,$rowcnt,$total[$table]);
                    $starttime=microtime(true);
                }
                if(count($retrow)>0){
                    fwrite($handle,"INSERT INTO `" . $table . "` VALUES\n  ".implode(",\n  ",$retrow).";\n\n");
                    $retrow=array();
                    $str_len=0;
                }
                mysql_free_result($result);
                fwrite($handle,"\n");
            }

            //сохраняем файл
            $this->close($handle);

            // не поменялись ли таблицы за время дискотеки?
            $next_try=false;
            $result = mysql_query('SHOW TABLE STATUS FROM `'.$this->options['base'].'` like "%"');
            while ($row = mysql_fetch_assoc($result))
            {
                if(in_array($row['Name'],$tables)) {
                    if($times[$row['Name']] != $row['Update_time']){
                        $times[$row['Name']] = $row['Update_time'];
                        $next_try=true;
                    }
                }
            }
            mysql_free_result($result);

        } while($next_try);

        return true;
    }
}

