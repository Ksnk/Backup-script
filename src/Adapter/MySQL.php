<?php
require_once 'BackUp/src/Adapter.php';
/**
 * Класс реализующий интерфейс работы с базой данных MySQL.
 *
 * @author Alex Lebedev
 */
class Backup_Adapter_MySQL extends Backup_Adapter{
    private $mySQLi;
    public function __construct($host, $user, $password, $DB) {
        $this->mySQLi = new mysqli($host, $user, $password, $DB);
    }
    public function showCreateTable($table) {
        echo 'Запустился метод - ' . __METHOD__ . PHP_EOL;
        $row = $this->mySQLi->query('SHOW CREATE TABLE `' . $table . '`')->fetch_row();
        var_dump($row[1]);
        echo 'Отработал метод - ' . __METHOD__ . PHP_EOL;
    }
}

?>
