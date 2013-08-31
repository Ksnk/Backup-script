<?php

/**
 * ----------------------------------------------------------------------------------
 * $Id: Backup-script. All about sql-dump for MySql databases,
 * ver: v1.2-13-gcee0e73, Last build: 1308311540
 * GIT: origin	https://github.com/Ksnk/Backup-script (push)$
 * ----------------------------------------------------------------------------------
 * MIT license - Serge Koriakin - Jule 2010-2012, sergekoriakin@gmail.com
 * ----------------------------------------------------------------------------------
 */
/*  --- point::execute --- */

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
     * @param string       $val     значение, если первый параметр строка
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
            mysql_close($this->link);
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
                    $result = mysql_query($s);
                    if (!$result) {
                        // let' point to first line
                        str_replace("\n", "\n", $s, $clines);
                        throw new BackupException(sprintf(
                            "Invalid query at line %s: %s\nWhole query: %s"
                            , $line - $clines, mysql_error(), str_pad($s, 200)));
                    }
                    if (is_resource($result)) {
                        mysql_free_result($result);
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
        $this->link = mysql_connect(
            $this->_opt['host'], $this->_opt['user'], $this->_opt['pass']
        );
        $this->_opt['base'] = mysql_real_escape_string($this->_opt['base']);
        if (!mysql_select_db($this->_opt['base'], $this->link)) {
            throw new BackupException(
                'Can\'t use `' . $this->_opt['base'] . '` : ' . mysql_error(),
                mysql_errno()
            );
        }
        // empty - значит нинада!!!
        if (!empty($this->_opt['code'])) {
            mysql_query(
                'set NAMES "' . mysql_real_escape_string($this->_opt['code']) . '";'
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
                $r = mysql_query("SHOW COLUMNS FROM `$table`");
                $num_fields = 0;
                while ($col = mysql_fetch_array($r)) {
                    $notNum[$num_fields++] = preg_match(
                        "/^(tinyint|smallint|mediumint|bigint|int|"
                            . "float|double|real|decimal|numeric|year)/",
                        $col['Type']
                    ) ? 0 : 1;
                }
                mysql_free_result($r);
                $this->write($handle, 'DROP TABLE IF EXISTS `' . $table . '`;');
                $r = mysql_query('SHOW CREATE TABLE `' . $table . '`');
                $row2 = mysql_fetch_row($r);
                if (is_resource($r)) {
                    mysql_free_result($r);
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

                $result = mysql_unbuffered_query(
                    'SELECT * FROM `' . $table . '`', $this->link
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

                while ($row = mysql_fetch_row($result)) {
                    $rowcnt++;
                    $this->_progress($rowcnt);

                    for ($j = 0; $j < $num_fields; $j++) {
                        if (is_null($row[$j])) {
                            $row[$j] = 'NULL';
                        } elseif ($notNum[$j]) {
                            $row[$j] = '\'' . str_replace(
                                '\\"', '"', mysql_real_escape_string($row[$j])
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
                mysql_free_result($result);
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
        $result = mysql_query(
            'SHOW TABLE STATUS FROM `' . $this->_opt['base'] . '` like "%"'
        );
        if (!$result) {
            throw new BackupException('Invalid query: ' . mysql_error() . "\n");
        }
        // запоминаем время модификации таблиц и таблицы, подходящие нам по маске
        while ($row = mysql_fetch_assoc($result)) {
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
        mysql_free_result($result);
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
     * @return bool
     */
    function tableChanged()
    {
        // не поменялись ли таблицы за время дискотеки?
        $changed = false;
        $result = mysql_query(
            'SHOW TABLE STATUS FROM `' . $this->_opt['base'] . '` like "%"'
        );
        while ($row = mysql_fetch_assoc($result)) {
            if (in_array($row['Name'], $this->tables)) {
                if ($this->times[$row['Name']] != $row['Update_time']) {
                    $this->times[$row['Name']] = $row['Update_time'];
                    $changed = true;
                }
            }
            unset($row);
        }
        mysql_free_result($result);
        return $changed;
    }

}

/***********************************************************************************
 *
 * Лицензионное соглашение.
 * ========================
 * 
 *     Copyright (c) 2012 Serge Koriakin <sergekoriakin@gmail.com>
 * 
 * Данная  лицензия  разрешает  лицам,  получившим   копию   данного   программного
 * обеспечения и сопутствующей документации (в дальнейшем  именуемыми  «Программное
 * Обеспечение»),   безвозмездно   использовать   Программное    Обеспечение    без
 * ограничений,  включая  неограниченное  право  на   использование,   копирование,
 * изменение,  добавление,  публикацию,  распространение,  сублицензирование  и/или
 * продажу  копий  Программного   Обеспечения,   также   как   и   лицам,   которым
 * предоставляется  данное  Программное  Обеспечение,  при   соблюдении   следующих
 * условий:
 * 
 * Указанное выше уведомление об авторском  праве  и  данные  условия  должны  быть
 * включены во все копии или значимые части данного Программного Обеспечения.
 * 
 * ДАННОЕ  ПPОГPАММНОЕ  ОБЕСПЕЧЕНИЕ  ПPЕДОСТАВЛЯЕТСЯ  «КАК  ЕСТЬ»,  БЕЗ  КАКИХ-ЛИБО
 * ГАPАНТИЙ, ЯВНО ВЫPАЖЕННЫХ ИЛИ  ПОДPАЗУМЕВАЕМЫХ,  ВКЛЮЧАЯ,  НО  НЕ  ОГPАНИЧИВАЯСЬ
 * ГАPАНТИЯМИ ТОВАPНОЙ ПPИГОДНОСТИ, СООТВЕТСТВИЯ ПО ЕГО  КОНКPЕТНОМУ  НАЗНАЧЕНИЮ  И
 * ОТСУТСТВИЯ НАPУШЕНИЙ ПPАВ. НИ В  КАКОМ  СЛУЧАЕ  АВТОPЫ  ИЛИ  ПPАВООБЛАДАТЕЛИ  НЕ
 * НЕСУТ  ОТВЕТСТВЕННОСТИ  ПО  ИСКАМ  О  ВОЗМЕЩЕНИИ  УЩЕPБА,  УБЫТКОВ  ИЛИ   ДPУГИХ
 * ТPЕБОВАНИЙ ПО ДЕЙСТВУЮЩИМ КОНТPАКТАМ, ДЕЛИКТАМ ИЛИ ИНОМУ, ВОЗНИКШИМ ИЗ,  ИМЕЮЩИМ
 * ПPИЧИНОЙ  ИЛИ  СВЯЗАННЫМ   С   ПPОГPАММНЫМ   ОБЕСПЕЧЕНИЕМ   ИЛИ   ИСПОЛЬЗОВАНИЕМ
 * ПPОГPАММНОГО ОБЕСПЕЧЕНИЯ ИЛИ ИНЫМИ ДЕЙСТВИЯМИ С ПPОГPАММНЫМ ОБЕСПЕЧЕНИЕМ.
 * 
 *
 **********************************************************************************
 */