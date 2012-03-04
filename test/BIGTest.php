<?php

define('TESTBASESIZE',30);// 30~ about 3mb sql log, 3000 - 300mb as well

    require_once dirname(__FILE__).'\..\src\backup.php';
    require_once dirname(__FILE__).'\..\src\BackupException.php';
    require_once 'PHPUnit/Extensions/Database/TestCase.php';
    require_once 'GENERATOR.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    require 'PHPUnit/Autoload.php' ;
}

class BIGTest extends PHPUnit_Extensions_Database_TestCase
    {
        /** @var BACKUP */
        protected $object,
        /** @var array - test options */
            $options=array(
            'host'=>'localhost',
            'user'=>'root',
            'password'=>'',
            'base'=>'test',
        );
        /** @var PDO */
        protected $pdo=null;

        protected function getConnection()
        {
            $this->pdo = new PDO("mysql:dbname=".$this->options['base'].";host=".$this->options['host'], $this->options['user'], $this->options['password']);
            return $this->createDefaultDBConnection($this->pdo, $this->options['base']);
        }

        protected function getDataSet()
        {
            return $this->createXMLDataSet(dirname(__FILE__)."/Zodiak.xml");
        }

        /**
         * 30x10000 ~~ 300m of sql dump
         * @return array
         */
        function testCreateBigSqlDump(){
            $name=dirname(__FILE__).'\xxx.sql.gz';
            if(!file_exists($name)){
                $sql="DROP TABLE IF EXISTS `ZodiakX`;
CREATE TABLE `ZodiakX` (
  `IdZodiak` int(11) NOT NULL AUTO_INCREMENT,
  `Zodiak` text NOT NULL DEFAULT '',
  PRIMARY KEY (`IdZodiak`)
) ENGINE=MyISAM  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=13 ;
";
                $handle=gzopen($name,'w8');
                fwrite($handle,$sql);
                for($j=0;$j<TESTBASESIZE;$j++){
                    fwrite($handle,'INSERT INTO `ZodiakX` ( `Zodiak`) VALUES
');
                    for($i=0;$i<100;$i++){
                        fwrite($handle,'  ("'. mysql_real_escape_string(GENERATOR::genLines(rand(10,20))).'"),
');
                    }
                    fwrite($handle,'  ("'. mysql_real_escape_string(GENERATOR::genLines(rand(10,20))).'");
');
                }
                fclose($handle);
            }
            $this->assertTrue(is_readable($name));
            return $name;
        }

        /**
         * @cover BACKUP::restore
         * @depends  testCreateBigSqlDump
         */
        public function testBigSqlRestore($name){
            // so let's generate a truly BIG sql dump
            $this->object = new BACKUP($this->options);
            $this->object->options('file',$name);
            $this->object->restore();
            $this->assertTrue(is_file($name));
        }

        /**
         * @cover BACKUP::restore
         * @depends  testCreateBigSqlDump
         */
        public function testBigSqlDump($name){
            // so let's generate a truly BIG sql dump
            $this->object = new BACKUP($this->options);
            $name=preg_replace('/\.sql\.gz/','1.sql.gz',$name);
            $this->object->options('file',$name);
            $this->object->make_backup();
            $this->assertTrue(is_file($name));
        }
    static function main() {

        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    BIGTest::main();
}

?>