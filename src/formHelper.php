<?php
/* <% POINT::start('support_functions'); %> /*
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

/* <% POINT::finish('support_functions'); %> */

