<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Сергей
 * Date: 09.02.12
 * Time: 9:49
 * To change this template use File | Settings | File Templates.
 */
/**
 * use this class to generaite test sequence
 */
class GENERATOR {

    private static $charbase='abcdefghijklmnopqrstuvwxyz';

    /**
     * genetate random word with $len characters
     * @static
     * @param $len
     * @return string
     */
    static function genWord($len){
        $result='';
        for($i=0;$i<$len;$i++){
            $result.=self::$charbase[rand(0,strlen(self::$charbase)-1)];
        }
        return $result;
    }

    /**
     * Generate 1 line of text with $words words
     * @static
     * @param $words
     * @return string
     */
    static function genLine($words){
        $result='';
        for($i=0;$i<$words-1;$i++){
            $result.=self::genWord(rand(2,10)).str_pad(' ',rand(1,8));
        }
        return $result.self::genWord(rand(2,10));
    }

    /**
     * Generate $lines lines of text
     * @static
     * @param $lines
     * @return string
     */
    static function genLines($lines){
        $result='';
        for($i=0;$i<$lines;$i++){
            $result.=self::genLine(rand(5,7))."\r\n";
        }
        return $result;
    }
}