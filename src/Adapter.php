<?php
/**
 * Абстрактный класс описывающий интерфейс работы с БД
 * Необходим для работы с различными базами данных
 *
 * @author Alex
 */

abstract class Backup_Adapter {

    abstract public function showCreateTable($table);
}

?>
